<?php

namespace App\Http\Controllers\Api;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $teams = $user->teams()
            ->withCount(['members', 'prompts'])
            ->get()
            ->map(function (Team $team) {
                return [
                    'id'           => $team->id,
                    'name'         => $team->name,
                    'slug'         => $team->slug,
                    'created_by'   => $team->created_by,
                    'member_count' => $team->members_count,
                    'prompt_count' => $team->prompts_count,
                    'role'         => $team->pivot->role,
                    'created_at'   => $team->created_at,
                    'updated_at'   => $team->updated_at,
                ];
            });

        return $this->success($teams);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team = Team::create([
            'name'       => $validated['name'],
            'created_by' => $request->user()->id,
        ]);

        // Add creator as owner
        $team->members()->attach($request->user()->id, ['role' => 'owner']);

        $team->loadCount(['members', 'prompts']);

        return $this->success([
            'id'           => $team->id,
            'name'         => $team->name,
            'slug'         => $team->slug,
            'created_by'   => $team->created_by,
            'member_count' => $team->members_count,
            'prompt_count' => $team->prompts_count,
            'created_at'   => $team->created_at,
            'updated_at'   => $team->updated_at,
        ], 201);
    }

    public function show(Request $request, Team $team): JsonResponse
    {
        $this->authorizeMembershipOrAdmin($team, $request);

        $team->load('members');
        $team->loadCount('prompts');

        return $this->success([
            'id'           => $team->id,
            'name'         => $team->name,
            'slug'         => $team->slug,
            'created_by'   => $team->created_by,
            'prompt_count' => $team->prompts_count,
            'members'      => $team->members->map(fn (User $m) => [
                'id'    => $m->id,
                'name'  => $m->name,
                'email' => $m->email,
                'role'  => $m->pivot->role,
            ]),
            'created_at'   => $team->created_at,
            'updated_at'   => $team->updated_at,
        ]);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $this->authorizeOwnerOrAdmin($team, $request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team->update($validated);

        return $this->success([
            'id'         => $team->id,
            'name'       => $team->name,
            'slug'       => $team->slug,
            'created_by' => $team->created_by,
            'created_at' => $team->created_at,
            'updated_at' => $team->updated_at,
        ]);
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->authorizeOwnerOrAdmin($team, $request);

        $team->delete();

        return $this->success(['message' => 'Team deleted.']);
    }

    public function addMember(Request $request, Team $team): JsonResponse
    {
        $this->authorizeOwnerOrAdmin($team, $request);

        $validated = $request->validate([
            'email'   => 'required_without:user_id|nullable|email|exists:users,email',
            'user_id' => 'required_without:email|nullable|exists:users,id',
        ]);

        if (!empty($validated['user_id'])) {
            $user = User::findOrFail($validated['user_id']);
        } else {
            $user = User::where('email', $validated['email'])->firstOrFail();
        }

        // Don't add if already a member
        if ($team->members()->where('users.id', $user->id)->exists()) {
            return $this->error('User is already a member of this team.', 409);
        }

        $team->members()->attach($user->id, ['role' => 'member']);

        return $this->success([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => 'member',
        ], 201);
    }

    public function removeMember(Request $request, Team $team, User $user): JsonResponse
    {
        $this->authorizeOwnerOrAdmin($team, $request);

        if (!$team->members()->where('users.id', $user->id)->exists()) {
            return $this->error('User is not a member of this team.', 404);
        }

        $team->members()->detach($user->id);

        return $this->success(['message' => 'Member removed from team.']);
    }

    private function authorizeMembershipOrAdmin(Team $team, Request $request): void
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$team->members()->where('users.id', $user->id)->exists()) {
            abort(403, 'You are not a member of this team.');
        }
    }

    private function authorizeOwnerOrAdmin(Team $team, Request $request): void
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }

        $membership = $team->members()->where('users.id', $user->id)->first();
        if (!$membership || $membership->pivot->role !== 'owner') {
            abort(403, 'Only team owners can perform this action.');
        }
    }
}
