<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'delivery_id',
        'payment_method',
        'amount',
        'currency',
        'status',
        'payment_reference',
        'collected_at',
        'remitted_at',
        'remitted_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'collected_at' => 'datetime',
            'remitted_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function remittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitted_by');
    }
}
