<?php

use App\Http\Middleware\DualAuthentication;
use App\Http\Middleware\NoCacheApi;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // INFRA-11: the framework's /up health page renders a branded
        // HTML doc that leaks "Laravel" and loads external fonts/CDN
        // scripts. A minimal JSON /up is defined in routes/web.php
        // instead (inside the web group so it also gets SecurityHeaders).
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RequireRole::class,
            'spa.auth' => 'auth:sanctum',
            'dual.auth' => DualAuthentication::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'oauth/token',
            'oauth/register',
            'oauth/revoke',
        ]);

        // INFRA-02: defense-in-depth security headers on every web
        // response. Internally skips /api/*, /oauth/*, /.well-known/*.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api(append: [
            NoCacheApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
