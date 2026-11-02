<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('system_settings')) {
            return $next($request);
        }

        $settings = SystemSetting::values();
        config([
            'session.lifetime' => $settings['auto_logout'] === '1'
                ? (int) $settings['session_timeout']
                : 10080,
        ]);

        return $next($request);
    }
}
