<?php

namespace App\Http\Controllers\Api;

use App\Services\TemplateEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RenderController extends ApiController
{
    use ResolvesPrompts;

    public function __construct(private TemplateEngine $templateEngine) {}

    public function render(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $validated = $request->validate([
            'version'   => 'nullable|integer|min:1',
            'variables' => 'nullable|array',
        ]);

        if (!empty($validated['version'])) {
            $version = $prompt->versions()
                ->where('version_number', $validated['version'])
                ->firstOrFail();
        } else {
            $version = $prompt->active_version;
            if (!$version) {
                return $this->error('Prompt has no versions.', 404);
            }
        }

        $variables = $validated['variables'] ?? [];
        $strict = $request->boolean('strict', true);

        try {
            $result = $this->templateEngine->render(
                $version->content,
                $variables,
                $version->variable_metadata,
                $request->user(),
                strict: $strict,
            );
        } catch (\InvalidArgumentException $e) {
            // Non-scalar variable value, missing required variable (strict).
            return $this->error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            // Include depth/budget/size guard or circular include (TPL-04).
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'rendered'           => $result['rendered'],
            'variables_used'     => $result['variables_used'],
            'variables_missing'  => $result['variables_missing'],
            'includes_resolved'  => $result['includes_resolved'],
            'prompt'             => $prompt->slug,
            'version'            => $version->version_number,
        ]);
    }
}
