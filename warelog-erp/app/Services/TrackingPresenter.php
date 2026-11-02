<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\TrackingEvent;

/**
 * Turns deliveries / timeline rows into the small JSON structures the live-tracking screens poll.
 */
class TrackingPresenter
{
    public const STALE_AFTER_SECONDS = 120;

    public static function delivery(Delivery $delivery): array
    {
        $seen = $delivery->last_seen_at;

        return [
            'id' => $delivery->id,
            'reference' => $delivery->reference,
            'item' => $delivery->item?->name,
            'item_code' => $delivery->item?->code,
            'qty' => $delivery->qty,
            'unit' => $delivery->item?->unit,
            'destination' => $delivery->destination,
            'driver' => $delivery->driver,
            'vehicle' => $delivery->vehicle,
            'stage' => $delivery->stage,
            'stage_label' => $delivery->stage_label,
            'lat' => $delivery->last_lat,
            'lng' => $delivery->last_lng,
            'accuracy' => $delivery->last_accuracy,
            'last_seen_at' => $seen?->toIso8601String(),
            'last_seen_human' => $seen?->diffForHumans(),
            'stale' => $seen === null || $seen->diffInSeconds(now(), true) > self::STALE_AFTER_SECONDS,
            'has_proof' => $delivery->hasProof(),
            'proof_lat' => $delivery->proof_lat,
            'proof_lng' => $delivery->proof_lng,
            'scheduled_at' => $delivery->scheduled_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'url' => route('admin.deliveries.show', $delivery),
        ];
    }

    public static function event(TrackingEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type,
            'message' => $event->message,
            'reference' => $event->delivery?->reference,
            'user' => $event->user?->name,
            'lat' => $event->lat,
            'lng' => $event->lng,
            'created_at' => $event->created_at?->toIso8601String(),
            'human' => $event->created_at?->diffForHumans(),
        ];
    }
}
