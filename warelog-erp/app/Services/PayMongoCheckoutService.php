<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\PaymentTransaction;
use App\Models\TrackingEvent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PayMongoCheckoutService
{
    public function createCheckout(Delivery $delivery): array
    {
        $amountInCentavos = (int) round((float) $delivery->amount * 100);
        if ($amountInCentavos < 1) {
            throw new RuntimeException('Online payment requires a delivery amount greater than zero.');
        }

        $response = $this->request()
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->post('/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'cancel_url' => route('logistics.paymongo.return', $delivery, true),
                        'description' => "Delivery {$delivery->reference}",
                        'line_items' => [[
                            'currency' => 'PHP',
                            'amount' => $amountInCentavos,
                            'name' => "{$delivery->item->name} delivery",
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => config('services.paymongo.payment_method_types'),
                        'reference_number' => $delivery->reference,
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'success_url' => route('logistics.paymongo.return', $delivery, true),
                    ],
                ],
            ])
            ->throw()
            ->json('data');

        $sessionId = $response['id'] ?? null;
        $checkoutUrl = $response['attributes']['checkout_url'] ?? null;
        $checkoutHost = is_string($checkoutUrl) ? parse_url($checkoutUrl, PHP_URL_HOST) : null;

        if (
            ! is_string($sessionId)
            || ! is_string($checkoutUrl)
            || ! str_starts_with($checkoutUrl, 'https://')
            || $checkoutHost !== 'checkout.paymongo.com'
        ) {
            throw new RuntimeException('PayMongo returned an invalid checkout session.');
        }

        return ['id' => $sessionId, 'url' => $checkoutUrl];
    }

    public function retrieveCheckout(string $sessionId): array
    {
        return $this->request()
            ->get('/checkout_sessions/'.rawurlencode($sessionId))
            ->throw()
            ->json('data');
    }

    public function isPaid(array $checkout): bool
    {
        return ($checkout['attributes']['status'] ?? null) === 'paid';
    }

    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = config('services.paymongo.webhook_secret');
        if (! is_string($secret) || $secret === '' || ! is_string($signatureHeader)) {
            return false;
        }

        $signatureParts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key !== null && $value !== null) {
                $signatureParts[$key] = $value;
            }
        }

        $timestamp = $signatureParts['t'] ?? null;
        if (! is_string($timestamp) || ! ctype_digit($timestamp)) {
            return false;
        }

        $tolerance = (int) config('services.paymongo.webhook_tolerance', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return collect(['te', 'li'])->contains(
            fn (string $key) => isset($signatureParts[$key]) && hash_equals($expected, $signatureParts[$key])
        );
    }

    public function confirmPaidDelivery(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery) {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_method !== 'paymongo') {
                return $locked;
            }

            if ($locked->payment_status !== 'paid') {
                $locked->update(['payment_status' => 'paid', 'paid_at' => now()]);
            }
            PaymentTransaction::firstOrCreate(
                ['delivery_id' => $locked->id],
                [
                    'payment_method' => 'paymongo',
                    'amount' => $locked->amount,
                    'currency' => $locked->currency,
                    'status' => 'received',
                    'payment_reference' => $locked->paymongo_checkout_session_id,
                    'collected_at' => $locked->paid_at ?? now(),
                ],
            );
            if ($locked->scanned_at && $locked->status !== 'delivered') {
                $locked->update(['status' => 'delivered', 'delivered_at' => now()]);
                TrackingEvent::record($locked, null, 'delivered', "{$locked->reference} delivered · online payment confirmed");
            }

            return $locked;
        });
    }

    private function request(): PendingRequest
    {
        $secretKey = config('services.paymongo.secret_key');
        if (! is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('PayMongo is not configured. Set PAYMONGO_SECRET_KEY in the application environment.');
        }

        return Http::baseUrl('https://api.paymongo.com/v1')
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($secretKey, '')
            ->timeout(15);
    }
}
