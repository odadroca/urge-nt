<?php

use App\Http\Controllers\InternalApiController;
use App\Http\Controllers\ShareController;
use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/app/browse'));

// API documentation (public, no auth)
Route::get('/docs', fn () => view('docs'));

// Public share routes (no auth required) — throttled per IP (TPL-06)
Route::get('/share/{token}', [ShareController::class, 'show'])
    ->name('share.show')
    ->where('token', '[a-f0-9]{64}')
    ->middleware('throttle:30,1');

Route::middleware(['auth'])->group(function () {
    Route::get('/browse', fn () => redirect('/app/browse'))->name('browse');
    Route::get('/dashboard', fn () => redirect('/app/browse'))->name('dashboard');
    Route::get('/prompts/{username}/{slug}', fn (string $username, string $slug) => redirect("/app/workspace/{$username}/{$slug}"))->name('workspace');

    // Legacy redirect: /prompts/{slug} → /app/workspace/{owner}/{slug}
    Route::get('/prompts/{slug}', function (string $slug) {
        $prompt = Prompt::where('slug', $slug)
            ->where('created_by', auth()->id())
            ->first();

        if (! $prompt) {
            $prompt = Prompt::where('slug', $slug)->oldest()->first();
        }

        if (! $prompt) {
            abort(404);
        }

        return redirect($prompt->workspaceUrl());
    });
    Route::get('/teams', fn () => redirect('/app/teams'))->name('teams');
    Route::get('/teams/{team:slug}', fn (Team $team) => redirect("/app/teams/{$team->slug}"))->name('team.detail');
    Route::get('/settings', fn () => redirect('/app/settings'))->name('settings');

    // Internal API for autocomplete
    Route::get('/internal/variables', [InternalApiController::class, 'variables'])->name('internal.variables');
    Route::get('/internal/fragments', [InternalApiController::class, 'fragments'])->name('internal.fragments');

    // Profile is now a tab inside the SPA Settings page.
    Route::get('/profile', fn () => redirect('/app/settings?tab=profile'))->name('profile.edit');

    // OAuth 2.1 authorization (requires login)
    Route::get('/oauth/authorize', [App\Http\Controllers\OAuthController::class, 'showAuthorize']);
    Route::post('/oauth/authorize', [App\Http\Controllers\OAuthController::class, 'handleAuthorize']);

    // GitHub OAuth — redirect (stores MCP params in session, then goes to GitHub)
    Route::get('/oauth/github', [App\Http\Controllers\OAuthGitHubController::class, 'redirect']);
});

// GitHub OAuth callback (no auth — user is not logged in yet)
Route::get('/oauth/github/callback', [App\Http\Controllers\OAuthGitHubController::class, 'callback']);

// OAuth 2.1 endpoints (public, CORS-enabled for browser-based MCP clients)
Route::middleware(App\Http\Middleware\OAuthCors::class)->group(function () {
    Route::get('/.well-known/oauth-protected-resource', [App\Http\Controllers\WellKnownController::class, 'protectedResource']);
    Route::get('/.well-known/oauth-authorization-server', [App\Http\Controllers\WellKnownController::class, 'authorizationServer']);
    Route::get('/.well-known/openid-configuration', [App\Http\Controllers\WellKnownController::class, 'openIdConfiguration']);

    // CSRF-exempt per OAuth spec; throttled per AUTH-02 (was: unthrottled).
    Route::middleware('throttle:20,1')->post('/oauth/token', [App\Http\Controllers\OAuthController::class, 'token']);
    Route::middleware('throttle:5,1')->post('/oauth/register', [App\Http\Controllers\OAuthController::class, 'register']);
    Route::middleware('throttle:20,1')->post('/oauth/revoke', [App\Http\Controllers\OAuthController::class, 'revoke']);

    Route::options('/oauth/token', fn () => response('', 204));
    Route::options('/oauth/register', fn () => response('', 204));
    Route::options('/oauth/revoke', fn () => response('', 204));
});

require __DIR__.'/auth.php';

// SPA catch-all — serves React app at /app/*
Route::get('/app/{any?}', function () {
    return view('spa');
})->where('any', '.*')->middleware('auth')->name('spa');
