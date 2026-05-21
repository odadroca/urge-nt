<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            'role'     => \App\Http\Middleware\RequireRole::class,
            'api.auth' => \App\Http\Middleware\ApiKeyAuthentication::class,
            'spa.auth'  => 'auth:sanctum',
            'dual.auth' => \App\Http\Middleware\DualAuthentication::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'oauth/token',
            'oauth/register',
            'oauth/revoke',
        ]);

        // INFRA-02: defense-in-depth security headers on every web
        // response. Internally skips /api/*, /oauth/*, /.well-known/*.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\NoCacheApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
