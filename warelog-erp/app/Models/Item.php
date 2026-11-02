<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'category', 'qty', 'unit',
        'price', 'location', 'reorder_level', 'description'
    ];

    /**
     * The text encoded in this item's QR label. Scanners only need the item code;
     * older labels ("CODE | name | location") still resolve because only the part before "|" is used.
     */
    public function getQrPayloadAttribute(): string
    {
        return $this->code;
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }
}
