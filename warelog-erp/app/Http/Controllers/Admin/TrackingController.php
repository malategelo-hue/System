<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\SystemSetting;
use App\Models\TrackingEvent;
use App\Services\TrackingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Admin "control tower": live map of every active delivery plus an item trace lookup.
 * The page polls /tracking/live every few seconds.
 */
class TrackingController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        return view('admin.tracking', [
            'systemSettings' => SystemSetting::values(),
            'snapshot' => $this->snapshot(),
        ]);
    }

    public function live(): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json($this->snapshot())->header('Cache-Control', 'private, no-store');
    }

    public function item(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $code = trim(Str::before((string) $request->query('code'), '|'));
        if ($code === '') {
            return response()->json(['message' => 'Enter or scan an item code.'], 422);
        }

        $item = Item::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();
        if (! $item) {
            return response()->json(['message' => "No item found for code {$code}."], 404);
        }

        $deliveries = Delivery::with('item')->where('item_id', $item->id)->latest('id')->get();
        $inTransit = $deliveries->where('status', 'in_transit');

        return response()->json([
            'item' => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'category' => $item->category,
                'location' => $item->location,
                'unit' => $item->unit,
                'on_hand' => (int) $item->qty,
            ],
            'in_transit_qty' => (int) $inTransit->sum('qty'),
            'delivered_qty' => (int) $deliveries->where('status', 'delivered')->sum('qty'),
            'active' => $inTransit->values()->map(fn (Delivery $d) => TrackingPresenter::delivery($d))->all(),
            'events' => TrackingEvent::with(['user', 'delivery'])->where('item_id', $item->id)->latest('id')->take(40)->get()
                ->map(fn (TrackingEvent $event) => TrackingPresenter::event($event))->all(),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function snapshot(): array
    {
        $timezone = SystemSetting::values()['timezone'];
        $startOfDay = now($timezone)->startOfDay()->utc();

        $active = Delivery::with('item')->where('status', 'in_transit')->orderBy('scheduled_at')->get();
        $deliveredToday = Delivery::with('item')->where('status', 'delivered')
            ->where('delivered_at', '>=', $startOfDay)->latest('delivered_at')->get();

        return [
            'server_time' => now()->toIso8601String(),
            'counts' => [
                'ready' => $active->where('stage', 'ready')->count(),
                'on_the_way' => $active->where('stage', 'on_the_way')->count(),
                'awaiting_payment' => $active->where('stage', 'awaiting_payment')->count(),
                'delivered_today' => $deliveredToday->count(),
            ],
            'deliveries' => $active->concat($deliveredToday)->values()
                ->map(fn (Delivery $delivery) => TrackingPresenter::delivery($delivery))->all(),
            'events' => TrackingEvent::with(['user', 'delivery'])->latest('id')->take(40)->get()
                ->map(fn (TrackingEvent $event) => TrackingPresenter::event($event))->all(),
        ];
    }

    private function authorizeAdmin(): void
    {
        abort_if(Auth::user()->role !== 'admin', 403, 'Admin access only');
    }
}
