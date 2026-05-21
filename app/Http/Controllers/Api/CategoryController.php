<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(Category::withCount('prompts')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isEditor()) {
            return $this->error('Insufficient permissions.', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:30',
        ]);

        $category = Category::create($validated);

        return $this->success($category, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isEditor()) {
            return $this->error('Editor access required.', 403);
        }

        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'color' => 'sometimes|required|string|max:30',
        ]);

        $category->update($validated);

        return $this->success($category->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isEditor()) {
            return $this->error('Editor access required.', 403);
        }

        $category = Category::findOrFail($id);
        $category->prompts()->update(['category_id' => null]);
        $category->delete();

        return $this->success(['message' => 'Category deleted.']);
    }
}
