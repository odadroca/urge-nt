<?php

namespace App\Http\Controllers\Api;

use App\Models\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends ApiController
{
    use ResolvesPrompts;

    public function index(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $query = $prompt->results()->with('promptVersion');

        if ($version = $request->input('version')) {
            $promptVersion = $prompt->versions()
                ->where('version_number', $version)
                ->first();
            if ($promptVersion) {
                $query->where('prompt_version_id', $promptVersion->id);
            }
        }

        if ($request->has('starred')) {
            $query->where('starred', filter_var($request->input('starred'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderByDesc('created_at');

        return $this->paginated($query, $request);
    }

    public function store(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $validated = $request->validate([
            'version'          => 'required|integer|min:1',
            'response_text'    => 'required|string',
            'source'           => 'in:api,manual,import,mcp',
            'provider_name'    => 'nullable|string|max:100',
            'model_name'       => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
            'rating'           => 'nullable|integer|min:1|max:5',
            'starred'          => 'nullable|boolean',
            'rendered_content' => 'nullable|string',
            'variables_used'   => 'nullable|array',
            'input_tokens'     => 'nullable|integer|min:0',
            'output_tokens'    => 'nullable|integer|min:0',
            'duration_ms'      => 'nullable|integer|min:0',
        ]);

        $promptVersion = $prompt->versions()
            ->where('version_number', $validated['version'])
            ->firstOrFail();

        $result = Result::create([
            'prompt_id'         => $prompt->id,
            'prompt_version_id' => $promptVersion->id,
            'source'            => $validated['source'] ?? 'api',
            'provider_name'     => $validated['provider_name'] ?? null,
            'model_name'        => $validated['model_name'] ?? null,
            'response_text'     => $validated['response_text'],
            'notes'             => $validated['notes'] ?? null,
            'rating'            => $validated['rating'] ?? null,
            'starred'           => $validated['starred'] ?? false,
            'rendered_content'  => $validated['rendered_content'] ?? null,
            'variables_used'    => $validated['variables_used'] ?? null,
            'input_tokens'      => $validated['input_tokens'] ?? null,
            'output_tokens'     => $validated['output_tokens'] ?? null,
            'duration_ms'       => $validated['duration_ms'] ?? null,
            'created_by'        => $request->user()->id,
        ]);

        return $this->success($result, 201);
    }

    public function starred(Request $request): JsonResponse
    {
        $query = Result::where('starred', true)
            ->whereHas('prompt', function ($q) use ($request) {
                $q->visibleTo($request->user());
            })
            ->with(['prompt.creator', 'promptVersion'])
            ->orderByDesc('created_at');

        return $this->paginated($query, $request);
    }

    public function destroy(Result $result): JsonResponse
    {
        $result->delete();

        return $this->success(['message' => 'Result deleted.']);
    }

    public function show(Result $result): JsonResponse
    {
        $result->load(['prompt', 'promptVersion']);
        return $this->success($result);
    }

    public function update(Request $request, Result $result): JsonResponse
    {
        $validated = $request->validate([
            'rating'  => 'nullable|integer|min:1|max:5',
            'starred' => 'nullable|boolean',
            'notes'   => 'nullable|string',
        ]);

        $result->update($validated);

        return $this->success($result->fresh());
    }
}
