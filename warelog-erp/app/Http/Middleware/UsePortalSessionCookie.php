<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UsePortalSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        $routeName = $request->route()?->getName();
        $isUserPortal = ($routeName && (
            str_starts_with($routeName, 'user.')
            || str_starts_with($routeName, 'stockin')
            || str_starts_with($routeName, 'stockout')
            || str_starts_with($routeName, 'logistics')
        )) || str_starts_with($path, 'user/')
            || in_array($path, ['stockin', 'stockout', 'logistics'], true)
            || preg_match('/^(stockin|stockout|logistics)\//', $path) === 1;

        $portalCookie = config($isUserPortal ? 'session.user_cookie' : 'session.admin_cookie');
        $portalChanged = config('session.cookie') !== $portalCookie;

        if (Auth::hasResolvedGuards()) {
            $currentUser = Auth::user();
            if ($currentUser && (($currentUser->role === 'user') !== $isUserPortal)) {
                Auth::forgetGuards();
            }
        }

        config(['session.cookie' => $portalCookie]);
        if ($portalChanged) {
            app('session')->forgetDrivers();
            app()->forgetInstance('session.store');
        }

        return $next($request);
    }
}
