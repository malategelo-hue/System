<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\SystemSetting;
use App\Models\Delivery;
use App\Models\PaymentTransaction;
use App\Services\PayMongoCheckoutService;
use App\Models\TrackingEvent;
use App\Http\Controllers\DriverDeliveryController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\DeliveryController as AdminDeliveryController;
use App\Http\Controllers\Admin\TrackingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

Route::get('/', fn() => redirect()->route('login'))->name('home');
require __DIR__.'/auth.php';

Route::get('/user', function () {
    if (Auth::check() && Auth::user()->role === 'user') {
        return redirect()->route('logistics');
    }

    return redirect()->route('login');
})->name('user.portal');

Route::get('/logistics/paymongo/return/{delivery}', function (
    Delivery $delivery,
    PayMongoCheckoutService $payMongo,
) {
    abort_unless(
        $delivery->payment_method === 'paymongo'
        && $delivery->paymongo_checkout_session_id,
        404,
    );

    $checkout = $payMongo->retrieveCheckout($delivery->paymongo_checkout_session_id);
    abort_unless(($checkout['id'] ?? null) === $delivery->paymongo_checkout_session_id, 502);

    if ($payMongo->isPaid($checkout)) {
        $payMongo->confirmPaidDelivery($delivery);

        return redirect()->route('logistics')->with(
            $delivery->scanned_at ? 'success' : 'error',
            $delivery->scanned_at
                ? "Payment received and delivery {$delivery->reference} confirmed."
                : "Payment received for {$delivery->reference}. Scan the item QR at delivery to complete it.",
        );
    }

    if (($checkout['attributes']['status'] ?? null) === 'expired') {
        $delivery->update([
            'paymongo_checkout_session_id' => null,
            'paymongo_checkout_url' => null,
            'payment_status' => 'pending',
        ]);
    }

    return redirect()->route('logistics')->with(
        'error',
        'Payment is not confirmed yet. The delivery remains in transit until PayMongo confirms payment.',
    );
})->name('logistics.paymongo.return');

Route::post('/webhooks/paymongo', function (
    Request $request,
    PayMongoCheckoutService $payMongo,
) {
    abort_unless($payMongo->verifyWebhookSignature(
        $request->getContent(),
        $request->header('Paymongo-Signature'),
    ), 403, 'Invalid PayMongo webhook signature.');

    $event = $request->json('data.attributes');
    if (($event['type'] ?? null) !== 'checkout_session.payment.paid') {
        return response()->noContent();
    }

    $sessionId = $event['data']['id'] ?? null;
    if (! is_string($sessionId)) {
        return response()->noContent();
    }

    $delivery = Delivery::where('paymongo_checkout_session_id', $sessionId)->first();
    if (! $delivery) {
        return response()->noContent();
    }

    $checkout = $payMongo->retrieveCheckout($sessionId);
    abort_unless(($checkout['id'] ?? null) === $sessionId, 502);

    if ($payMongo->isPaid($checkout)) {
        $payMongo->confirmPaidDelivery($delivery);
    }

    return response()->noContent();
})->name('webhooks.paymongo');

Route::middleware(['auth'])->group(function () {

    // ==========================================
    // DASHBOARD
    // ==========================================
    Route::get('/dashboard', function () {
        $user = Auth::user();
        abort_if($user->role !== 'admin', 403, 'Admin access only');
        $items = Item::all();
        $systemSettings = SystemSetting::values();
        $firstMonth = now($systemSettings['timezone'])->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->map(
            fn (int $offset) => $firstMonth->copy()->addMonths($offset)
        );
        $monthlyTotals = $months->map(function ($month) {
            $start = $month->copy()->startOfMonth()->utc();
            $end = $month->copy()->endOfMonth()->utc();

            return [
                'in' => StockMovement::where('type', 'in')->whereBetween('created_at', [$start, $end])->sum('qty'),
                'out' => StockMovement::where('type', 'out')->whereBetween('created_at', [$start, $end])->sum('qty'),
            ];
        });
        $stockInData = $monthlyTotals->pluck('in')->all();
        $stockOutData = $monthlyTotals->pluck('out')->all();

        return view('dashboard', [
            'role' => $user->role,
            'items' => $items,
            'months' => $months->map(fn ($month) => $month->format('M'))->all(),
            'stockInData' => $stockInData,
            'stockOutData' => $stockOutData,
            'systemSettings' => $systemSettings,
            'totalValue' => $items->sum(fn($i) => $i->qty * $i->price),
            'lowStock' => $items->whereBetween('qty', [1, (int) $systemSettings['low_stock_threshold']])->count(),
            'outOfStock' => $items->where('qty', '<=', 0)->count(),
            'totalProducts' => $items->count(),
            'recentMovements' => StockMovement::orderByDesc('created_at')->orderByDesc('id')->take(5)->get(),
            'liveCounts' => [
                'ready' => Delivery::where('status', 'in_transit')->whereNull('trip_started_at')->count(),
                'on_the_way' => Delivery::where('status', 'in_transit')->whereNotNull('trip_started_at')->count(),
                'unassigned' => Delivery::where('status', 'in_transit')->whereNull('assigned_to')->count(),
            ],
        ]);
    })->name('dashboard');

    Route::get('/dashboard/total-stock-value', function () {
        abort_if(Auth::user()->role !== 'admin', 403, 'Admin access only');

        $systemSettings = SystemSetting::values();
        $totalValue = (float) Item::query()->sum(DB::raw('qty * price'));
        $currencySymbol = $systemSettings['currency'] === 'USD' ? '$' : '₱';
        $decimalPlaces = $systemSettings['show_decimals'] === '1' ? 2 : 0;

        return response()
            ->json([
                'display' => $currencySymbol.number_format($totalValue, $decimalPlaces),
            ])
            ->header('Cache-Control', 'private, no-store');
    })->name('dashboard.total-stock-value');

    Route::get('/payments', function () {
        abort_if(Auth::user()->role !== 'admin', 403, 'Admin access only');

        $payments = PaymentTransaction::with(['delivery.item', 'remittedBy'])
            ->latest('collected_at')
            ->paginate(20);
        $totals = PaymentTransaction::query()
            ->select('currency')
            ->selectRaw(
                'SUM(CASE WHEN payment_method = ? AND status = ? THEN amount ELSE 0 END) AS cash_wallet',
                ['cod', 'received'],
            )
            ->selectRaw(
                'SUM(CASE WHEN payment_method = ? AND status = ? THEN amount ELSE 0 END) AS cod_pending',
                ['cod', 'pending_remittance'],
            )
            ->selectRaw(
                'SUM(CASE WHEN payment_method = ? AND status = ? THEN amount ELSE 0 END) AS online_received',
                ['paymongo', 'received'],
            )
            ->selectRaw('SUM(amount) AS total_collected')
            ->groupBy('currency')
            ->get();

        return view('payments', [
            'payments' => $payments,
            'totals' => $totals,
        ]);
    })->name('payments.index');

    Route::post('/payments/{payment}/receive-cod', function (PaymentTransaction $payment) {
        abort_if(Auth::user()->role !== 'admin', 403, 'Admin access only');

        DB::transaction(function () use ($payment) {
            $payment = PaymentTransaction::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $payment->payment_method === 'cod' && $payment->status === 'pending_remittance',
                409,
                'Only collected COD payments awaiting remittance can be received.',
            );
            $payment->update([
                'status' => 'received',
                'remitted_at' => now(),
                'remitted_by' => Auth::id(),
            ]);
        });

        return redirect()->route('payments.index')->with('success', 'COD payment marked as received by admin.');
    })->name('payments.receive-cod');

    // ==========================================
    // INVENTORY — ADMIN LANG: Add/Edit/Delete ✅ WALANG STOCK IN/OUT
    // ==========================================
    Route::get('/inventory', function () {
        abort_if(Auth::user()->role !== 'admin', 403, 'Admin access only');
        $items = Item::latest()->get();
        return view('inventory', ['items' => $items, 'systemSettings' => SystemSetting::values()]);
    })->name('inventory');

    Route::get('/inventory/code-check', function (Request $request) {
        abort_if(Auth::user()->role !== 'admin', 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'except' => ['nullable', 'integer', 'exists:items,id'],
        ]);
        $candidate = mb_strtolower(trim($data['code']));
        $items = Item::query()
            ->when(isset($data['except']), fn ($query) => $query->where('id', '<>', $data['except']))
            ->get(['id', 'code']);
        $matches = $items->map(function (Item $item) use ($candidate) {
            $code = mb_strtolower(trim($item->code));
            similar_text($candidate, $code, $similarity);

            return [
                'code' => $item->code,
                'similarity' => $similarity,
                'distance' => levenshtein($candidate, $code),
            ];
        });
        $duplicate = $matches->first(fn (array $match) => mb_strtolower($match['code']) === $candidate);
        $similar = $matches
            ->filter(fn (array $match) => $match['similarity'] >= 75 || $match['distance'] <= 2)
            ->sortByDesc('similarity')
            ->take(5)
            ->pluck('code')
            ->values();

        return response()->json([
            'duplicate' => $duplicate !== null,
            'similar' => $similar,
        ]);
    })->name('inventory.code-check');

    Route::post('/inventory/add', function (Request $req) {
        abort_if(Auth::user()->role !== 'admin', 403);
        $code = trim((string) $req->input('code'));
        if ($code === '' && SystemSetting::values()['auto_codes'] === '1') {
            $usedCodes = Item::where('code', 'like', 'ITM-%')->pluck('code');
            $nextCode = $usedCodes->map(fn (string $usedCode) => preg_match('/^ITM-(\d+)$/i', $usedCode, $matches)
                ? (int) $matches[1]
                : 0)->max() + 1;
            $code = sprintf('ITM-%05d', $nextCode);
            while (Item::where('code', $code)->exists()) {
                $nextCode++;
                $code = sprintf('ITM-%05d', $nextCode);
            }
        }
        $req->merge(['code' => $code]);
        $data = $req->validate([
            'code' => [
                'required', 'string', 'max:255',
                Rule::unique('items', 'code'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && Item::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($value))])->exists()) {
                        $fail('This item code already exists.');
                    }
                },
            ],
            'name' => 'required',
            'category' => 'required',
            'qty' => 'required|integer|min:0',
            'unit' => 'required',
            'price' => 'required|numeric|min:0',
            'location' => 'required',
        ]);
        $item = DB::transaction(function () use ($data) {
            $item = Item::create($data);
            if ($item->qty > 0) {
                StockMovement::create([
                    'type' => 'in',
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'qty' => $item->qty,
                    'supplier' => 'Opening inventory',
                    'user_id' => Auth::id(),
                ]);
            }
            TrackingEvent::record(null, $item, 'stock_in', "Registered {$item->name} ({$item->code}) with {$item->qty} {$item->unit} in {$item->location}");

            return $item;
        });
        return redirect()->route('inventory')
            ->with('success', 'Item added and opening stock recorded.')
            ->with('labels_for', ['item_id' => $item->id, 'qty' => max(1, (int) $item->qty)]);
    })->name('inventory.add');

    Route::get('/inventory/edit/{id}', function ($id) {
        abort_if(Auth::user()->role !== 'admin', 403);
        $item = Item::findOrFail($id);
        $items = Item::latest()->get();
        return view('inventory', ['item' => $item, 'items' => $items, 'systemSettings' => SystemSetting::values()]);
    })->name('inventory.edit');

    Route::post('/inventory/update/{id}', function (Request $req, $id) {
        abort_if(Auth::user()->role !== 'admin', 403);
        $item = Item::findOrFail($id);
        $data = $req->validate([
            'code' => [
                'required', 'string', 'max:255',
                Rule::unique('items', 'code')->ignore($id),
                function (string $attribute, mixed $value, \Closure $fail) use ($id): void {
                    if (is_string($value) && Item::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($value))])
                        ->where('id', '<>', $id)
                        ->exists()) {
                        $fail('This item code already exists.');
                    }
                },
            ],
            'name' => 'required',
            'category' => 'required',
            'qty' => 'required|integer|min:0',
            'unit' => 'required',
            'price' => 'required|numeric|min:0',
            'location' => 'required',
        ]);
        DB::transaction(function () use ($item, $data) {
            $current = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $oldQty = (int) $current->qty;
            $newQty = (int) $data['qty'];
            $current->update($data);

            if ($oldQty !== $newQty) {
                StockMovement::create([
                    'type' => $newQty > $oldQty ? 'in' : 'out',
                    'item_id' => $current->id,
                    'item_name' => $current->name,
                    'qty' => abs($newQty - $oldQty),
                    'supplier' => $newQty > $oldQty ? 'Admin inventory adjustment' : null,
                    'issued_to' => $newQty < $oldQty ? 'Admin inventory adjustment' : null,
                    'user_id' => Auth::id(),
                ]);
                TrackingEvent::record(null, $current, 'adjusted', "Stock of {$current->name} adjusted from {$oldQty} to {$newQty} {$current->unit}");
            }
        });
        return redirect('/inventory')->with('success', '✅ Item Updated!');
    })->name('inventory.update');

    Route::post('/inventory/delete/{id}', function ($id) {
        abort_if(Auth::user()->role !== 'admin', 403);
        Item::findOrFail($id)->delete();
        return redirect('/inventory')->with('success', '✅ Item Deleted!');
    })->name('inventory.delete');

    // ==========================================
    // STOCK IN / OUT — admin only
    // ==========================================
    Route::get('/inventory/stock-in', function () {
        abort_if(Auth::user()->role !== 'admin', 403, 'Admin access only');
        $items = Item::orderBy('name')->get();
        $movements = StockMovement::where('type', 'in')->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        return view('stockin', ['items' => $items, 'movements' => $movements, 'systemSettings' => SystemSetting::values()]);
    })->name('inventory.stockin');

    Route::post('/inventory/stock-in', function (Request $req) {
        abort_if(Auth::user()->role !== 'admin', 403);
        $data = $req->validate([
            'item_id' => 'required|exists:items,id',
            'qty' => 'required|integer|min:1',
            'supplier' => 'nullable|string|max:255',
        ]);
        $item = DB::transaction(function () use ($data) {
            $item = Item::whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            $item->increment('qty', (int) $data['qty']);
            StockMovement::create([
                'type' => 'in', 'item_id' => $item->id, 'item_name' => $item->name,
                'qty' => (int) $data['qty'], 'supplier' => $data['supplier'] ?? null, 'user_id' => Auth::id(),
            ]);
            TrackingEvent::record(null, $item, 'stock_in', "Received {$data['qty']} {$item->unit} of {$item->name}".(! empty($data['supplier']) ? " from {$data['supplier']}" : ''));
            return $item;
        });
        return redirect()->route('inventory.stockin')
            ->with('success', "Stock-in recorded: {$item->name} +{$data['qty']}.")
            ->with('labels_for', ['item_id' => $item->id, 'qty' => (int) $data['qty']]);
    })->name('inventory.stockin.store');

    Route::get('/inventory/stock-out', function () {
        abort_if(Auth::user()->role !== 'admin', 403, 'Admin access only');
        $items = Item::where('qty','>',0)->orderBy('name')->get();
        $movements = StockMovement::where('type', 'out')->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        return view('stockout', ['items' => $items, 'movements' => $movements, 'systemSettings' => SystemSetting::values()]);
    })->name('inventory.stockout');

    Route::post('/inventory/stock-out', function (Request $req) {
        abort_if(Auth::user()->role !== 'admin', 403);
        $data = $req->validate([
            'item_id' => 'required|exists:items,id',
            'qty' => 'required|integer|min:1',
            'issued_to' => 'required|string|max:255',
        ]);
        $item = DB::transaction(function () use ($data) {
            $item = Item::whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            if ($item->qty < $data['qty']) {
                throw ValidationException::withMessages([
                    'qty' => "Only {$item->qty} available.",
                ]);
            }
            $item->decrement('qty', (int) $data['qty']);
            StockMovement::create([
                'type' => 'out', 'item_id' => $item->id, 'item_name' => $item->name,
                'qty' => (int) $data['qty'], 'issued_to' => $data['issued_to'], 'user_id' => Auth::id(),
            ]);
            TrackingEvent::record(null, $item, 'stock_out', "Issued {$data['qty']} {$item->unit} of {$item->name} to {$data['issued_to']}");
            return $item;
        });
        return redirect()->route('inventory.stockout')->with('success', "Stock-out recorded: {$item->name} -{$data['qty']}.");
    })->name('inventory.stockout.store');

    // ==========================================
    // REPORTS — MAY LAMAN NA!
    // ==========================================
    $reportsHandler = function () {
        abort_if(Auth::user()->role !== 'admin', 403, 'Admin access only');
        $items = Item::all();
        $movements = StockMovement::orderByDesc('created_at')->orderByDesc('id')->get();
        $systemSettings = SystemSetting::values();
        
        // Summary data
        $byCategory = [
            'Raw Materials' => $items->where('category','Raw Materials')->count(),
            'Supplies' => $items->where('category','Supplies')->count(),
            'Finished Goods' => $items->where('category','Finished Goods')->count(),
            'Equipment' => $items->where('category','Equipment')->count(),
        ];
        
        $byLocation = [
            'Warehouse A' => $items->where('location','Warehouse A')->count(),
            'Warehouse B' => $items->where('location','Warehouse B')->count(),
            'Warehouse C' => $items->where('location','Warehouse C')->count(),
        ];
        
        $totalStockIn = StockMovement::where('type','in')->sum('qty');
        $totalStockOut = StockMovement::where('type','out')->sum('qty');
        $totalValue = $items->sum(fn($i) => $i->qty * $i->price);
        $deliveries = Delivery::with('item')->orderByDesc('created_at')->get();
        $completedDeliveries = $deliveries->where('status', 'delivered');
        $onTimeDeliveries = $completedDeliveries->filter(
            fn (Delivery $delivery) => $delivery->delivered_at
                && $delivery->delivered_at->lessThanOrEqualTo($delivery->scheduled_at)
        );
        $pendingDeliveries = $deliveries->where('status', 'in_transit');
        $onTimeRate = $completedDeliveries->isEmpty()
            ? 0
            : (int) round(($onTimeDeliveries->count() / $completedDeliveries->count()) * 100);
        $deliverySummary = [
            'pending' => $pendingDeliveries->count(),
            'completed' => $completedDeliveries->count(),
            'on_time' => $onTimeDeliveries->count(),
            'late' => $completedDeliveries->count() - $onTimeDeliveries->count(),
            'on_time_rate' => $onTimeRate,
        ];

        return view('reports', compact(
            'items','movements','byCategory','byLocation','systemSettings',
            'totalStockIn','totalStockOut','totalValue','deliveries','deliverySummary'
        ));
    };

    Route::get('/reports', $reportsHandler)->name('reports');

    // ==========================================
    // SETTINGS — SYSTEM PREFERENCES
    // ==========================================
    Route::get('/settings', function () {
        abort_if(Auth::user()->role !== 'admin', 403);
        return view('settings', ['systemSettings' => SystemSetting::values()]);
    })->name('settings');

    Route::post('/settings/save', function (Request $req) {
        abort_if(Auth::user()->role !== 'admin', 403);
        $data = $req->validate([
            'system_name' => 'required|string|max:50',
            'company_name' => 'required|string|max:100',
            'warehouse_name' => 'required|string|max:50',
            'address' => 'nullable|string|max:255',
            'contact_no' => 'nullable|string|max:50',
            'low_stock_threshold' => 'required|integer|min:1|max:1000000',
            'currency' => ['required', Rule::in(['PHP', 'USD'])],
            'date_format' => ['required', Rule::in(['m/d/Y', 'd/m/Y', 'Y-m-d'])],
            'timezone' => ['required', 'timezone'],
            'session_timeout' => ['required', Rule::in(['15', '30', '60', '120', '240'])],
        ]);
        foreach (['low_stock_alerts', 'auto_codes', 'show_decimals', 'dark_mode', 'auto_logout', 'backup_daily', 'require_gps_proof'] as $toggle) {
            $data[$toggle] = $req->boolean($toggle) ? '1' : '0';
        }
        $data['low_stock_threshold'] = (string) $data['low_stock_threshold'];
        SystemSetting::saveValues($data);

        return redirect()->route('settings')->with('success', 'Settings saved successfully.');
    })->name('settings.save');

    Route::post('/settings/reset', function () {
        abort_if(Auth::user()->role !== 'admin', 403);
        SystemSetting::resetToDefaults();

        return redirect()->route('settings')->with('success', 'Settings restored to their defaults.');
    })->name('settings.reset');

    Route::post('/settings/password', function (Request $request) {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);
        $request->user()->update(['password' => Hash::make($data['new_password'])]);

        return redirect()->route('settings')->with('success', 'Password changed successfully.');
    })->name('settings.password');

    Route::post('/settings/clear-cache', function () {
        abort_if(Auth::user()->role !== 'admin', 403);
        $exitCode = Artisan::call('optimize:clear');
        if ($exitCode !== 0) {
            throw new \RuntimeException('The application cache could not be cleared.');
        }

        return redirect()->route('settings')->with('success', 'Application caches cleared.');
    })->name('settings.clear-cache');

    Route::get('/settings/export', function () {
        abort_if(Auth::user()->role !== 'admin', 403);
        abort_unless(DB::connection()->getDriverName() === 'sqlite', 501, 'Database export is currently supported for SQLite only.');
        $databasePath = DB::connection()->getDatabaseName();
        abort_unless(is_file($databasePath), 404, 'The SQLite database file was not found.');

        return response()->download($databasePath, 'warelog-backup.sqlite');
    })->name('settings.export');
    // ==========================================
    // DRIVER LOGISTICS: assigned deliveries, live trip, QR + photo + GPS proof
    // ==========================================
    Route::get('/logistics', [DriverDeliveryController::class, 'index'])->name('logistics');
    Route::get('/logistics/assigned-notifications', [DriverDeliveryController::class, 'assignedNotifications'])
        ->name('logistics.notifications');
    Route::post('/logistics/verify', [DriverDeliveryController::class, 'verify'])->name('logistics.verify');
    Route::get('/logistics/{delivery}', [DriverDeliveryController::class, 'show'])->whereNumber('delivery')->name('logistics.show');
    Route::post('/logistics/{delivery}/claim', [DriverDeliveryController::class, 'claim'])->whereNumber('delivery')->name('logistics.claim');
    Route::post('/logistics/{delivery}/start', [DriverDeliveryController::class, 'start'])->whereNumber('delivery')->name('logistics.start');
    Route::post('/logistics/{delivery}/ping', [DriverDeliveryController::class, 'ping'])->whereNumber('delivery')->name('logistics.ping');
    Route::post('/logistics/{delivery}/pay', [DriverDeliveryController::class, 'pay'])->whereNumber('delivery')->name('logistics.pay');
    Route::get('/logistics/{delivery}/proof', [DriverDeliveryController::class, 'proof'])->whereNumber('delivery')->name('logistics.proof');

    // ==========================================
    // ADMIN — control tower: live tracking, deliveries, drivers, QR labels, receiving
    // ==========================================
    Route::get('/tracking', [TrackingController::class, 'index'])->name('tracking.index');
    Route::get('/tracking/live', [TrackingController::class, 'live'])->name('tracking.live');
    Route::get('/tracking/item', [TrackingController::class, 'item'])->name('tracking.item');

    Route::get('/deliveries', [AdminDeliveryController::class, 'index'])->name('admin.deliveries.index');
    Route::post('/deliveries', [AdminDeliveryController::class, 'store'])->name('admin.deliveries.store');
    Route::get('/deliveries/{delivery}', [AdminDeliveryController::class, 'show'])->whereNumber('delivery')->name('admin.deliveries.show');
    Route::post('/deliveries/{delivery}/assign', [AdminDeliveryController::class, 'assign'])->whereNumber('delivery')->name('admin.deliveries.assign');
    Route::get('/deliveries/{delivery}/live', [AdminDeliveryController::class, 'live'])->whereNumber('delivery')->name('admin.deliveries.live');
    Route::get('/deliveries/{delivery}/proof', [AdminDeliveryController::class, 'proof'])->whereNumber('delivery')->name('admin.deliveries.proof');

    Route::get('/team', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/team/{user}', [UserController::class, 'update'])->whereNumber('user')->name('admin.users.update');
    Route::post('/team/{user}/toggle', [UserController::class, 'toggle'])->whereNumber('user')->name('admin.users.toggle');
    Route::post('/team/{user}/delete', [UserController::class, 'destroy'])->whereNumber('user')->name('admin.users.destroy');

    Route::get('/inventory/{item}/labels', [LabelController::class, 'show'])->whereNumber('item')->name('inventory.labels');
    Route::post('/inventory/{item}/receive', function (Request $req, Item $item) {
        abort_if(Auth::user()->role !== 'admin', 403, 'Admin access only');
        $data = $req->validate([
            'qty' => 'required|integer|min:1',
            'supplier' => 'nullable|string|max:255',
        ]);
        $item = DB::transaction(function () use ($item, $data) {
            $item = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $item->increment('qty', (int) $data['qty']);
            StockMovement::create([
                'type' => 'in', 'item_id' => $item->id, 'item_name' => $item->name,
                'qty' => (int) $data['qty'], 'supplier' => $data['supplier'] ?? null, 'user_id' => Auth::id(),
            ]);
            TrackingEvent::record(null, $item, 'stock_in', "Received {$data['qty']} {$item->unit} of {$item->name}".(! empty($data['supplier']) ? " from {$data['supplier']}" : ''));

            return $item;
        });

        return redirect()->route('inventory')
            ->with('success', "Stock-in recorded: {$item->name} +{$data['qty']}.")
            ->with('labels_for', ['item_id' => $item->id, 'qty' => (int) $data['qty']]);
    })->whereNumber('item')->name('inventory.receive');
});

Route::get('/setup-accounts', function () {
    abort(404);
    $a = new User(); $a->name='Admin'; $a->email='admin@warelog.com'; $a->password=bcrypt('admin123'); $a->role='admin'; $a->save();
    $u = new User(); $u->name='Staff'; $u->email='user@warelog.com'; $u->password=bcrypt('user123'); $u->role='user'; $u->save();
    return "✅ Accounts Ready!<br>🔐 ADMIN: admin@warelog.com / admin123<br>👤 USER: user@warelog.com / user123<br><a href='/login'>→ Login</a>";
});