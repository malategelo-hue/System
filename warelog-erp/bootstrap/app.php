<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ApplySystemSettings;
use App\Http\Middleware\UsePortalSessionCookie;
use App\Http\Middleware\EnsureAccountIsActive;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', UsePortalSessionCookie::class);
        $middleware->prependToGroup('web', ApplySystemSettings::class);
        $middleware->appendToGroup('web', EnsureAccountIsActive::class);
        $middleware->validateCsrfTokens(except: ['webhooks/paymongo']);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
