<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\PromptVersion;
use Illuminate\Routing\Controller;

class InternalApiController extends Controller
{
    public function variables()
    {
        $user = auth()->user();

        $visiblePromptIds = Prompt::visibleTo($user)->pluck('id');

        $variables = PromptVersion::whereIn('prompt_id', $visiblePromptIds)
            ->whereNotNull('variables')
            ->pluck('variables')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return response()->json($variables);
    }

    public function fragments()
    {
        $user = auth()->user();

        $fragments = Prompt::visibleTo($user)
            ->whereNull('deleted_at')
            ->where('type', 'fragment')
            ->where(function ($q) {
                $q->whereNotNull('pinned_version_id')
                    ->orWhereHas('versions');
            })
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        return response()->json($fragments);
    }
}
