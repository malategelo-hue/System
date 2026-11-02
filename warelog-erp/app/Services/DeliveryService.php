<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\Item;
use App\Models\PaymentTransaction;
use App\Models\StockMovement;
use App\Models\SystemSetting;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    /**
     * Create a dispatch: reduce stock, record the stock-out and open the delivery timeline.
     *
     * @param  array{item_id:int|string, qty:int|string, destination:string, scheduled_at:mixed, payment_method:string, driver?:?string, vehicle?:?string}  $data
     */
    public function dispatch(array $data, User $actor, ?User $driver = null): Delivery
    {
        $currency = SystemSetting::values()['currency'];
        if ($data['payment_method'] === 'paymongo' && $currency !== 'PHP') {
            throw ValidationException::withMessages([
                'payment_method' => 'PayMongo online payment currently supports PHP pricing only. Change the system currency to PHP or use COD.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $driver, $currency) {
            $item = Item::whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            $qty = (int) $data['qty'];
            if ($item->qty < $qty) {
                throw ValidationException::withMessages([
                    'qty' => "Only {$item->qty} {$item->unit} available.",
                ]);
            }
            $amount = round((float) $item->price * $qty, 2);
            if ($data['payment_method'] === 'paymongo' && $amount < 0.01) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Online payment requires an item price greater than zero.',
                ]);
            }

            $item->decrement('qty', $qty);

            $delivery = Delivery::create([
                'reference' => 'DISP-'.now()->format('Y').'-'.strtoupper(bin2hex(random_bytes(3))),
                'item_id' => $item->id,
                'qty' => $qty,
                'destination' => $data['destination'],
                'driver' => $driver?->name ?? ($data['driver'] ?? null),
                'vehicle' => ($data['vehicle'] ?? null) ?: $driver?->vehicle,
                'scheduled_at' => $data['scheduled_at'],
                'payment_method' => $data['payment_method'],
                'status' => 'in_transit',
                'amount' => $amount,
                'currency' => $currency,
                'payment_status' => 'pending',
                'assigned_to' => $driver?->id,
            ]);

            StockMovement::create([
                'type' => 'out',
                'item_id' => $item->id,
                'item_name' => $item->name,
                'qty' => $qty,
                'issued_to' => $data['destination'],
                'user_id' => $actor->id,
            ]);

            $message = "Dispatched {$qty} {$item->unit} of {$item->name} to {$delivery->destination}";
            if ($driver) {
                $message .= " · assigned to {$driver->name}";
            }
            TrackingEvent::record($delivery, $item, 'dispatched', $message);

            return $delivery;
        });
    }

    public function assign(Delivery $delivery, User $driver): Delivery
    {
        if ($delivery->status === 'delivered') {
            throw ValidationException::withMessages(['driver_id' => 'This delivery is already completed.']);
        }

        $delivery->update([
            'assigned_to' => $driver->id,
            'driver' => $driver->name,
            'vehicle' => $delivery->vehicle ?: $driver->vehicle,
        ]);
        TrackingEvent::record($delivery, null, 'assigned', "{$delivery->reference} assigned to {$driver->name}");

        return $delivery;
    }

    public function claim(Delivery $delivery, User $driver): Delivery
    {
        return DB::transaction(function () use ($delivery, $driver) {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($locked->assigned_to !== null && (int) $locked->assigned_to !== (int) $driver->id) {
                throw ValidationException::withMessages(['delivery_id' => 'This delivery was already assigned to another driver.']);
            }

            return $locked->assigned_to === null ? $this->assign($locked, $driver) : $locked;
        });
    }

    public function startTrip(Delivery $delivery, User $driver, ?float $lat = null, ?float $lng = null, ?float $accuracy = null): Delivery
    {
        if ($delivery->status === 'delivered') {
            throw ValidationException::withMessages(['delivery_id' => 'This delivery is already completed.']);
        }

        if ($delivery->trip_started_at) {
            return $delivery;
        }

        $attributes = ['trip_started_at' => now()];
        if ($delivery->assigned_to === null) {
            $attributes['assigned_to'] = $driver->id;
            $attributes['driver'] = $driver->name;
        }
        if ($lat !== null && $lng !== null) {
            $attributes += ['last_lat' => $lat, 'last_lng' => $lng, 'last_accuracy' => $accuracy, 'last_seen_at' => now()];
        }
        $delivery->update($attributes);

        TrackingEvent::record($delivery, null, 'trip_started', "{$driver->name} started the trip for {$delivery->reference}", $lat, $lng);

        return $delivery;
    }

    /**
     * Store a live GPS position sent from the driver's phone. Returns false when the ping is ignored.
     */
    public function recordPing(Delivery $delivery, User $driver, float $lat, float $lng, ?float $accuracy = null, ?float $speed = null): bool
    {
        if ($delivery->status === 'delivered' || ! $delivery->trip_started_at) {
            return false;
        }
        // Ignore bursts: one stored position every ~3 seconds at most.
        if ($delivery->last_seen_at && $delivery->last_seen_at->diffInSeconds(now(), true) < 3) {
            return false;
        }

        $delivery->update([
            'last_lat' => $lat,
            'last_lng' => $lng,
            'last_accuracy' => $accuracy,
            'last_seen_at' => now(),
        ]);
        DeliveryLocation::create([
            'delivery_id' => $delivery->id,
            'user_id' => $driver->id,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => $accuracy,
            'speed' => $speed,
        ]);

        return true;
    }

    /**
     * Proof of delivery: the scanned item QR must match, and a photo + GPS fix are stored.
     * COD is recorded immediately; online payments stay "awaiting payment" until PayMongo confirms.
     */
    public function complete(
        Delivery $delivery,
        User $driver,
        string $scannedCode,
        UploadedFile $photo,
        ?float $lat,
        ?float $lng,
        ?float $accuracy = null,
    ): Delivery {
        $scannedCode = trim(Str::before($scannedCode, '|'));

        return DB::transaction(function () use ($delivery, $driver, $scannedCode, $photo, $lat, $lng, $accuracy) {
            $locked = Delivery::with('item')->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'delivered') {
                throw ValidationException::withMessages(['delivery_id' => 'This delivery was already confirmed.']);
            }
            if ($locked->hasProof()) {
                throw ValidationException::withMessages(['delivery_id' => 'Proof was already submitted. Complete the online payment to finish this delivery.']);
            }
            if (strcasecmp($locked->item->code, $scannedCode) !== 0) {
                throw ValidationException::withMessages(['delivery_id' => 'The scanned item does not match this delivery.']);
            }

            $path = $photo->store('delivery-proofs/'.now()->format('Y/m'), 'local');
            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages(['proof_photo' => 'The proof photo could not be saved. Please try again.']);
            }

            $now = now();
            $attributes = [
                'scanned_at' => $now,
                'scanned_code' => $scannedCode,
                'proof_photo_path' => $path,
                'proof_lat' => $lat,
                'proof_lng' => $lng,
                'proof_accuracy' => $accuracy,
                'proof_taken_at' => $now,
                'trip_started_at' => $locked->trip_started_at ?? $now,
            ];
            if ($locked->assigned_to === null) {
                $attributes['assigned_to'] = $driver->id;
                $attributes['driver'] = $driver->name;
            }
            if ($lat !== null && $lng !== null) {
                $attributes += ['last_lat' => $lat, 'last_lng' => $lng, 'last_accuracy' => $accuracy, 'last_seen_at' => $now];
            }

            try {
                $locked->update($attributes);

                TrackingEvent::record($locked, null, 'qr_scanned', "{$driver->name} scanned the item QR ({$scannedCode}) for {$locked->reference}", $lat, $lng);
                TrackingEvent::record($locked, null, 'proof_submitted', "Proof photo and location submitted for {$locked->reference}", $lat, $lng);

                $symbol = $locked->currency === 'USD' ? '$' : '₱';

                if ($locked->payment_method === 'cod') {
                    $locked->update([
                        'payment_status' => 'paid',
                        'paid_at' => $now,
                        'status' => 'delivered',
                        'delivered_at' => $now,
                    ]);
                    PaymentTransaction::firstOrCreate(
                        ['delivery_id' => $locked->id],
                        [
                            'payment_method' => 'cod',
                            'amount' => $locked->amount,
                            'currency' => $locked->currency,
                            'status' => 'pending_remittance',
                            'payment_reference' => $locked->reference,
                            'collected_at' => $locked->paid_at,
                        ],
                    );
                    TrackingEvent::record($locked, null, 'delivered', "{$locked->reference} delivered · COD {$symbol}".number_format((float) $locked->amount, 2).' collected', $lat, $lng);
                } elseif ($locked->payment_status === 'paid') {
                    $locked->update(['status' => 'delivered', 'delivered_at' => $now]);
                    TrackingEvent::record($locked, null, 'delivered', "{$locked->reference} delivered (already paid online)", $lat, $lng);
                } else {
                    TrackingEvent::record($locked, null, 'payment_pending', "{$locked->reference} waiting for online payment confirmation");
                }
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete($path);

                throw $exception;
            }

            return $locked;
        });
    }

    /**
     * Start (or reuse) the PayMongo checkout and return its URL.
     */
    public function checkoutUrl(Delivery $delivery): string
    {
        if ($delivery->paymongo_checkout_session_id && $delivery->paymongo_checkout_url) {
            return $delivery->paymongo_checkout_url;
        }

        $checkout = app(PayMongoCheckoutService::class)->createCheckout($delivery);
        $delivery->update([
            'paymongo_checkout_session_id' => $checkout['id'],
            'paymongo_checkout_url' => $checkout['url'],
        ]);

        return $checkout['url'];
    }
}
