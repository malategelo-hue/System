<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

class PortalPasswordReset extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        $route = $notifiable instanceof User && $notifiable->role === 'admin'
            ? 'password.reset'
            : 'user.password.reset';

        return route($route, [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
