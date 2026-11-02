<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $role = Auth::user()->role;
        if (! in_array($role, ['admin', 'user'], true)) {
            Auth::logout();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'This account does not have permission to access the application.',
            ]);
        }

        if (Auth::user()->is_active === false) {
            Auth::logout();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'This account is awaiting administrator approval or has been deactivated. Contact the administrator.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->setName(
            $role === 'user'
                ? config('session.user_cookie')
                : config('session.admin_cookie'),
        );

        $response = redirect()->route($role === 'user' ? 'logistics' : 'dashboard');
        $otherPortalCookie = $role === 'user'
            ? config('session.admin_cookie')
            : config('session.user_cookie');

        if ($otherPortalCookie !== $request->session()->getName()) {
            $sessionConfig = config('session');
            $response->headers->clearCookie(
                $otherPortalCookie,
                $sessionConfig['path'],
                $sessionConfig['domain'],
                (bool) $sessionConfig['secure'],
                $sessionConfig['http_only'] ?? true,
                $sessionConfig['same_site'] ?? null,
            );
        }

        return $response;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
