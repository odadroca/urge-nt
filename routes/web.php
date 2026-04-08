<?php

use App\Http\Controllers\InternalApiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShareController;
use App\Livewire\Browse;
use App\Livewire\Dashboard;
use App\Livewire\Settings;
use App\Livewire\TeamDetail;
use App\Livewire\Teams;
use App\Livewire\Workspace\WorkspacePage;
use App\Models\Prompt;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('browse'));

// Public share routes (no auth required)
Route::get('/share/{token}', [ShareController::class, 'show'])
    ->name('share.show')
    ->where('token', '[a-f0-9]{64}');

Route::middleware(['auth'])->group(function () {
    Route::get('/browse', Browse::class)->name('browse');
    Route::get('/dashboard', fn () => redirect()->route('browse'))->name('dashboard');
    Route::get('/prompts/{username}/{slug}', WorkspacePage::class)->name('workspace');

    // Legacy redirect: /prompts/{slug} → /prompts/{owner}/{slug}
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

        return redirect()->to($prompt->workspaceUrl());
    });
    Route::get('/teams', Teams::class)->name('teams');
    Route::get('/teams/{team:slug}', TeamDetail::class)->name('team.detail');
    Route::get('/settings', Settings::class)->name('settings');

    // Internal API for autocomplete
    Route::get('/internal/variables', [InternalApiController::class, 'variables'])->name('internal.variables');
    Route::get('/internal/fragments', [InternalApiController::class, 'fragments'])->name('internal.fragments');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// SPA catch-all — serves React app at /app/*
Route::get('/app/{any?}', function () {
    return view('spa');
})->where('any', '.*')->middleware('auth')->name('spa');
