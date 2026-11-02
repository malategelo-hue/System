<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryLocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['delivery_id', 'user_id', 'lat', 'lng', 'accuracy', 'speed'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'accuracy' => 'float',
            'speed' => 'float',
            'created_at' => 'datetime',
        ];
    }
}
