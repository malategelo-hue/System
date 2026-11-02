<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'item_id', 'qty', 'destination', 'driver', 'vehicle',
        'scheduled_at', 'status', 'delivered_at', 'payment_method', 'payment_status',
        'amount', 'currency', 'paymongo_checkout_session_id', 'paymongo_checkout_url', 'scanned_at', 'paid_at',
        'assigned_to', 'trip_started_at',
        'last_lat', 'last_lng', 'last_accuracy', 'last_seen_at',
        'scanned_code', 'proof_photo_path', 'proof_lat', 'proof_lng', 'proof_accuracy', 'proof_taken_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'delivered_at' => 'datetime',
            'amount' => 'decimal:2',
            'scanned_at' => 'datetime',
            'paid_at' => 'datetime',
            'trip_started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'proof_taken_at' => 'datetime',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'proof_lat' => 'float',
            'proof_lng' => 'float',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function paymentTransaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(DeliveryLocation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    /**
     * ready -> on_the_way -> awaiting_payment (proof done, online payment pending) -> delivered
     */
    public function getStageAttribute(): string
    {
        if ($this->status === 'delivered') {
            return 'delivered';
        }
        if ($this->scanned_at && $this->proof_photo_path) {
            return 'awaiting_payment';
        }
        if ($this->trip_started_at) {
            return 'on_the_way';
        }

        return 'ready';
    }

    public function getStageLabelAttribute(): string
    {
        return match ($this->stage) {
            'delivered' => 'Delivered',
            'awaiting_payment' => 'Awaiting payment',
            'on_the_way' => 'On the way',
            default => 'Ready for pickup',
        };
    }

    /**
     * Drivers only see deliveries assigned to them plus unassigned ones they can claim.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('assigned_to', $user->id)->orWhereNull('assigned_to');
        });
    }

    public function hasProof(): bool
    {
        return $this->proof_photo_path !== null && $this->scanned_at !== null;
    }
}
