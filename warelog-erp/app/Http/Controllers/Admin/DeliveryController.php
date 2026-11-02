<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DriverDeliveryController;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\TrackingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin side of logistics: dispatch, assign drivers, monitor and review proof of delivery.
 */
class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries)
    {
    }

    public function index(Request $request)
    {
        $this->admin();
        $filter = (string) $request->query('stage', 'all');
        $query = Delivery::with(['item', 'assignee'])->latest('id');

        if ($filter === 'delivered') {
            $query->where('status', 'delivered');
        } elseif ($filter === 'active') {
            $query->where('status', 'in_transit');
        } elseif ($filter === 'unassigned') {
            $query->where('status', 'in_transit')->whereNull('assigned_to');
        }

        return view('admin.deliveries.index', [
            'deliveries' => $query->paginate(25)->withQueryString(),
            'filter' => $filter,
            'counts' => [
                'all' => Delivery::count(),
                'active' => Delivery::where('status', 'in_transit')->count(),
                'unassigned' => Delivery::where('status', 'in_transit')->whereNull('assigned_to')->count(),
                'delivered' => Delivery::where('status', 'delivered')->count(),
            ],
            'items' => Item::where('qty', '>', 0)->orderBy('name')->get(),
            'drivers' => $this->drivers(),
            'systemSettings' => SystemSetting::values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $this->admin();
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'qty' => 'required|integer|min:1',
            'destination' => 'required|string|max:255',
            'driver_id' => 'nullable|integer',
            'vehicle' => 'nullable|string|max:120',
            'scheduled_at' => 'required|date',
            'payment_method' => ['required', Rule::in(['cod', 'paymongo'])],
        ]);

        $driver = $this->findDriver($data['driver_id'] ?? null);
        $delivery = $this->deliveries->dispatch($data, $admin, $driver);

        return redirect()->route('admin.deliveries.show', $delivery)
            ->with('success', "{$delivery->reference} dispatched".($driver ? " and assigned to {$driver->name}." : '. Assign a driver so it shows up in their app.'));
    }

    public function show(Delivery $delivery)
    {
        $this->admin();
        $delivery->load(['item', 'assignee', 'paymentTransaction']);

        return view('admin.deliveries.show', [
            'delivery' => $delivery,
            'drivers' => $this->drivers(),
            'events' => $delivery->events()->with('user')->latest('id')->take(50)->get(),
            'trail' => $delivery->locations()->orderBy('id')->take(500)->get(['lat', 'lng', 'created_at']),
            'systemSettings' => SystemSetting::values(),
        ]);
    }

    public function assign(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->admin();
        $data = $request->validate(['driver_id' => 'required|integer']);
        $driver = $this->findDriver($data['driver_id']);
        if (! $driver) {
            throw ValidationException::withMessages(['driver_id' => 'Choose an active driver account.']);
        }

        $this->deliveries->assign($delivery, $driver);

        return redirect()->route('admin.deliveries.show', $delivery)->with('success', "{$delivery->reference} assigned to {$driver->name}.");
    }

    public function live(Delivery $delivery): JsonResponse
    {
        $this->admin();
        $delivery->load('item');

        return response()->json([
            'delivery' => TrackingPresenter::delivery($delivery),
            'trail' => $delivery->locations()->orderBy('id')->take(500)->get(['lat', 'lng'])
                ->map(fn ($point) => [$point->lat, $point->lng])->all(),
            'events' => $delivery->events()->with(['user', 'delivery'])->latest('id')->take(50)->get()
                ->map(fn ($event) => TrackingPresenter::event($event))->all(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function proof(Delivery $delivery): Response
    {
        $this->admin();

        return DriverDeliveryController::proofResponse($delivery);
    }

    private function admin(): User
    {
        $user = Auth::user();
        abort_if($user->role !== 'admin', 403, 'Admin access only');

        return $user;
    }

    private function drivers()
    {
        return User::where('role', 'user')->where('is_active', true)->orderBy('name')->get();
    }

    private function findDriver(mixed $id): ?User
    {
        if ($id === null || $id === '') {
            return null;
        }

        $driver = User::where('role', 'user')->where('is_active', true)->find($id);
        if (! $driver) {
            throw ValidationException::withMessages(['driver_id' => 'Choose an active driver account.']);
        }

        return $driver;
    }
}
