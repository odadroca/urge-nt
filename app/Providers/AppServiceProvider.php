<?php

namespace App\Providers;

use App\Models\Collection;
use App\Models\PromptVersion;
use App\Models\Result;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'prompt_version' => PromptVersion::class,
            'result' => Result::class,
            'collection' => Collection::class,
        ]);
    }
}
