<?php

namespace App\Models;

use App\Notifications\PortalPasswordReset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    // ✅ TINANGGAL ANG HasApiTokens — wala kasing Sanctum!

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // ✅ ITO ANG MAHALAGA
        'phone',
        'vehicle',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function deliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Delivery::class, 'assigned_to');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PortalPasswordReset($token));
    }
}