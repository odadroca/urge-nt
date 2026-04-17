<?php

namespace App\Http\Controllers\Api;

use App\Models\LlmProvider;
use App\Services\LlmDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlmProviderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = LlmProvider::orderBy('name');

        if (!$request->user()->isAdmin()) {
            $query->where('is_active', true);
        }

        $providers = $query->get(['id', 'name', 'driver', 'model', 'endpoint', 'is_active']);

        return $this->success($providers);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'driver'   => 'required|in:openai,anthropic,mistral,gemini,ollama,openrouter',
            'api_key'  => 'nullable|string',
            'model'    => 'required|string|max:255',
            'endpoint' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $provider = LlmProvider::create([
            'name'      => $validated['name'],
            'driver'    => $validated['driver'],
            'api_key'   => $validated['api_key'] ?? null,
            'model'     => $validated['model'],
            'endpoint'  => $validated['endpoint'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->success($provider, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $provider = LlmProvider::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'driver'    => 'sometimes|required|in:openai,anthropic,mistral,gemini,ollama,openrouter',
            'api_key'   => 'nullable|string',
            'model'     => 'sometimes|required|string|max:255',
            'endpoint'  => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        if (array_key_exists('api_key', $validated) && ($validated['api_key'] === null || $validated['api_key'] === '')) {
            unset($validated['api_key']);
        }

        $provider->update($validated);

        return $this->success($provider->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        LlmProvider::findOrFail($id)->delete();

        return $this->success(['message' => 'Provider deleted.']);
    }

    public function test(Request $request, int $id, LlmDispatchService $dispatchService): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $provider = LlmProvider::findOrFail($id);

        try {
            $result = $dispatchService->dispatch($provider, 'Say "Connection successful" in exactly two words.');

            if ($result->success) {
                return $this->success([
                    'status'  => 'success',
                    'message' => "Connected: {$result->modelUsed} ({$result->durationMs}ms)",
                ]);
            }

            return $this->success([
                'status'  => 'error',
                'message' => "Failed: {$result->error}",
            ]);
        } catch (\Throwable $e) {
            return $this->success([
                'status'  => 'error',
                'message' => "Error: {$e->getMessage()}",
            ]);
        }
    }
}
