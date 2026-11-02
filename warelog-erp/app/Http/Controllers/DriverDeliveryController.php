<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Driver logistics: assigned deliveries, live trip, QR scan + photo + GPS proof.
 */
class DriverDeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries)
    {
    }

    public function index()
    {
        $user = $this->driver();
        $all = Delivery::with('item')->visibleTo($user)->latest()->get();

        $mine = $all->filter(fn (Delivery $d) => (int) $d->assigned_to === (int) $user->id && $d->status !== 'delivered')->values();
        $unassigned = $all->filter(fn (Delivery $d) => $d->assigned_to === null && $d->status !== 'delivered')->values();
        $completed = $all->filter(fn (Delivery $d) => $d->status === 'delivered')->take(15)->values();

        return view('logistics', [
            'deliveries' => $all,
            'mine' => $mine,
            'unassigned' => $unassigned,
            'completed' => $completed,
            'systemSettings' => SystemSetting::values(),
            'pendingOrders' => $mine->count(),
            'assignedWaitingCount' => $mine->whereNull('trip_started_at')->count(),
            'deliveredOrders' => $all->where('status', 'delivered')->count(),
        ]);
    }

    public function assignedNotifications(): JsonResponse
    {
        $user = $this->driver();
        $deliveries = Delivery::where('assigned_to', $user->id)
            ->where('status', '!=', 'delivered')
            ->whereNull('trip_started_at')
            ->latest('id')
            ->get(['id', 'reference'])
            ->map(fn (Delivery $delivery) => [
                'id' => $delivery->id,
                'reference' => $delivery->reference,
            ])
            ->values();

        return response()->json(['deliveries' => $deliveries])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Delivery $delivery)
    {
        $user = $this->driver();
        $this->authorizeDelivery($delivery, $user);
        $delivery->load('item');

        return view('logistics-show', [
            'delivery' => $delivery,
            'systemSettings' => SystemSetting::values(),
            'requireGps' => SystemSetting::values()['require_gps_proof'] === '1',
            'isMine' => (int) $delivery->assigned_to === (int) $user->id,
        ]);
    }

    public function claim(Delivery $delivery): RedirectResponse
    {
        $user = $this->driver();
        $this->authorizeDelivery($delivery, $user);
        $this->deliveries->claim($delivery, $user);

        return redirect()->route('logistics.show', $delivery)->with('success', "{$delivery->reference} is now assigned to you.");
    }

    public function start(Request $request, Delivery $delivery): RedirectResponse|JsonResponse
    {
        $user = $this->driver();
        $this->authorizeDelivery($delivery, $user);
        $data = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        $this->deliveries->startTrip(
            $delivery,
            $user,
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            isset($data['accuracy']) ? (float) $data['accuracy'] : null,
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('logistics.show', $delivery)->with('success', 'Trip started. Your live location is now shared with the admin.');
    }

    public function ping(Request $request, Delivery $delivery): JsonResponse
    {
        $user = $this->driver();
        abort_unless((int) $delivery->assigned_to === (int) $user->id, 403, 'This delivery is not assigned to you.');
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
        ]);

        $stored = $this->deliveries->recordPing(
            $delivery,
            $user,
            (float) $data['latitude'],
            (float) $data['longitude'],
            isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            isset($data['speed']) ? (float) $data['speed'] : null,
        );

        return response()->json(['ok' => true, 'stored' => $stored])->header('Cache-Control', 'no-store');
    }

    public function verify(Request $request): RedirectResponse|Response
    {
        $user = $this->driver();
        $requireGps = SystemSetting::values()['require_gps_proof'] === '1';
        $data = $request->validate([
            'delivery_id' => 'required|integer|exists:deliveries,id',
            'scanned_item_code' => 'required|string|max:255',
            'proof_photo' => ['required', 'file', 'image', 'max:10240'],
            'latitude' => [$requireGps ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [$requireGps ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
            'accuracy' => 'nullable|numeric|min:0',
        ], [
            'proof_photo.required' => 'Take a photo of the delivery location as proof.',
            'proof_photo.image' => 'The proof must be a photo (JPG, PNG or WebP).',
            'proof_photo.max' => 'The proof photo is too large (max 10 MB).',
            'latitude.required' => 'Your GPS location is required. Allow location access and try again.',
            'longitude.required' => 'Your GPS location is required. Allow location access and try again.',
        ]);

        $delivery = Delivery::with('item')->findOrFail($data['delivery_id']);
        $this->authorizeDelivery($delivery, $user);

        $delivery = $this->deliveries->complete(
            $delivery,
            $user,
            $data['scanned_item_code'],
            $request->file('proof_photo'),
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            isset($data['accuracy']) ? (float) $data['accuracy'] : null,
        );

        if ($delivery->status === 'delivered') {
            return redirect()->route('logistics')->with('success', "{$delivery->reference} delivered. Proof saved.");
        }

        return $this->continueToPayment($delivery);
    }

    /**
     * Proof is already saved but the online payment is still unpaid: reopen the PayMongo checkout.
     */
    public function pay(Delivery $delivery): RedirectResponse|Response
    {
        $user = $this->driver();
        $this->authorizeDelivery($delivery, $user);
        abort_unless(
            $delivery->payment_method === 'paymongo' && $delivery->hasProof() && $delivery->status !== 'delivered',
            409,
            'This delivery is not waiting for an online payment.',
        );

        return $this->continueToPayment($delivery);
    }

    public function proof(Delivery $delivery): Response
    {
        $user = $this->driver();
        $this->authorizeDelivery($delivery, $user);

        return self::proofResponse($delivery);
    }

    /**
     * Shared by the driver and admin proof routes.
     */
    public static function proofResponse(Delivery $delivery): Response
    {
        abort_unless($delivery->proof_photo_path && Storage::disk('local')->exists($delivery->proof_photo_path), 404, 'No proof photo for this delivery.');

        return Storage::disk('local')->response($delivery->proof_photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function continueToPayment(Delivery $delivery): RedirectResponse
    {
        try {
            return redirect()->away($this->deliveries->checkoutUrl($delivery));
        } catch (\Illuminate\Http\Client\RequestException|\RuntimeException $exception) {
            report($exception);

            return redirect()->route('logistics.show', $delivery)->withErrors([
                'payment' => 'Proof saved, but PayMongo checkout could not start. Check the payment configuration and tap "Pay online" again.',
            ]);
        }
    }

    private function driver(): User
    {
        $user = Auth::user();
        abort_if($user->role !== 'user', 403, 'User access only');
        abort_if($user->is_active === false, 403, 'This account has been deactivated.');

        return $user;
    }

    private function authorizeDelivery(Delivery $delivery, User $user): void
    {
        abort_unless(
            $delivery->assigned_to === null || (int) $delivery->assigned_to === (int) $user->id,
            403,
            'This delivery is assigned to another driver.',
        );
    }
}
