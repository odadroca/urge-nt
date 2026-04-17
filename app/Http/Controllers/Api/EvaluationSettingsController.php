<?php

namespace App\Http\Controllers\Api;

use App\Models\EvaluationSetting;
use App\Models\LlmProvider;
use App\Models\Prompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationSettingsController extends ApiController
{
    public function show(): JsonResponse
    {
        return $this->success([
            'enabled'             => (bool) EvaluationSetting::get('enabled', false),
            'auto_evaluate'       => (bool) EvaluationSetting::get('auto_evaluate', false),
            'default_provider_id' => EvaluationSetting::get('default_provider_id'),
            'prompt_slug'         => EvaluationSetting::get('prompt_slug', 'system-evaluation-template'),
            'dimensions'          => EvaluationSetting::get('dimensions', config('urge.evaluation.default_dimensions')),
            'providers'           => LlmProvider::where('is_active', true)->get(['id', 'name', 'model']),
            'eval_prompts'        => Prompt::where('type', 'fragment')->get(['id', 'slug', 'name']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled'             => 'boolean',
            'auto_evaluate'       => 'boolean',
            'default_provider_id' => 'nullable|integer',
            'prompt_slug'         => 'nullable|string',
            'dimensions'          => 'nullable|array',
        ]);

        foreach ($validated as $key => $value) {
            EvaluationSetting::set($key, $value);
        }

        return $this->success(['message' => 'Evaluation settings saved.']);
    }
}
