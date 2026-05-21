<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        return $this->success(User::orderBy('name')->get(['id', 'name', 'slug', 'email', 'role', 'created_at']));
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,editor,viewer',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return $this->success($user->only(['id', 'name', 'slug', 'email', 'role', 'created_at']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return $this->error('Cannot change your own role.', 422);
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,editor,viewer',
        ]);

        $user->update($validated);

        return $this->success($user->fresh()->only(['id', 'name', 'slug', 'email', 'role', 'created_at']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return $this->error('Cannot delete yourself.', 422);
        }

        $user->delete();

        return $this->success(['message' => 'User deleted.']);
    }
}
