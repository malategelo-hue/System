<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class TrackingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['item_id', 'delivery_id', 'user_id', 'type', 'message', 'lat', 'lng'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Append one entry to the item / delivery timeline.
     */
    public static function record(?Delivery $delivery, ?Item $item, string $type, string $message, ?float $lat = null, ?float $lng = null): self
    {
        return self::create([
            'item_id' => $item?->id ?? $delivery?->item_id,
            'delivery_id' => $delivery?->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'message' => $message,
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
