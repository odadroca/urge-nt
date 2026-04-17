<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiKey;
use App\Models\Prompt;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $keys = ApiKey::where('user_id', $request->user()->id)
            ->with('prompts:id,name,slug')
            ->withCount('prompts')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($keys);
    }

    public function store(Request $request, ApiKeyService $service): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'prompt_ids'  => 'nullable|array',
            'prompt_ids.*' => 'integer|exists:prompts,id',
        ]);

        $result = $service->generateKey(
            $request->user(),
            $validated['name'],
            $validated['prompt_ids'] ?? []
        );

        return $this->success([
            'key'   => $result['key'],
            'model' => $result['model']->load('prompts:id,name,slug'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $key = ApiKey::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $key->update($validated);

        return $this->success($key->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $key = ApiKey::where('user_id', $request->user()->id)->findOrFail($id);
        $key->delete();

        return $this->success(['message' => 'API key deleted.']);
    }
}
