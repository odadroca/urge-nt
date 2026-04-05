<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(Category::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isEditor()) {
            return $this->error('Insufficient permissions.', 403);
        }

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'color' => 'nullable|string|max:30',
        ]);

        $category = Category::create($validated);

        return $this->success($category, 201);
    }
}
