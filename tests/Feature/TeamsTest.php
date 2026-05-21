<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_teams_list_returns_user_teams(): void
    {
        $user = User::factory()->create();
        $team = Team::create(['name' => 'My Team', 'created_by' => $user->id]);
        $team->members()->attach($user->id, ['role' => 'owner']);

        $otherTeam = Team::create(['name' => 'Other Team', 'created_by' => $user->id]);
        // Not a member of otherTeam

        $response = $this->actingAs($user)->getJson('/api/v1/teams');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('My Team'));
        $this->assertFalse($names->contains('Other Team'));
    }

    public function test_create_team(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/teams', [
            'name' => 'New Team',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'New Team');

        $this->assertDatabaseHas('teams', ['name' => 'New Team', 'created_by' => $user->id]);

        $team = Team::where('name', 'New Team')->first();
        $this->assertTrue($team->members()->where('users.id', $user->id)->wherePivot('role', 'owner')->exists());
    }

    public function test_team_detail_shows_members(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);

        $team = Team::create(['name' => 'Test Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($owner)->getJson('/api/v1/teams/'.$team->slug);

        $response->assertOk();
        $memberNames = collect($response->json('data.members'))->pluck('name');
        $this->assertTrue($memberNames->contains('Owner'));
        $this->assertTrue($memberNames->contains('Member'));
    }

    public function test_non_member_cannot_view_team(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create(['role' => 'user']);

        $team = Team::create(['name' => 'Private Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($outsider)->getJson('/api/v1/teams/'.$team->slug);

        $response->assertStatus(403);
    }

    public function test_invite_member(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create(['name' => 'New Guy', 'email' => 'newguy@example.com']);

        $team = Team::create(['name' => 'Test Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->postJson('/api/v1/teams/'.$team->slug.'/members', [
            'email' => 'newguy@example.com',
        ]);

        $response->assertStatus(201);
        $this->assertTrue($team->members()->where('users.id', $newMember->id)->exists());
    }

    public function test_remove_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::create(['name' => 'Test Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($owner)->deleteJson('/api/v1/teams/'.$team->slug.'/members/'.$member->id);

        $response->assertOk();
        $this->assertFalse($team->members()->where('users.id', $member->id)->exists());
    }

    public function test_sole_owner_cannot_leave(): void
    {
        $owner = User::factory()->create();

        $team = Team::create(['name' => 'Test Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->postJson('/api/v1/teams/'.$team->slug.'/leave');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Cannot leave as sole owner. Transfer ownership first.');
        $this->assertTrue($team->members()->where('users.id', $owner->id)->exists());
    }

    public function test_delete_team(): void
    {
        $owner = User::factory()->create();

        $team = Team::create(['name' => 'Doomed Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $teamId = $team->id;

        $response = $this->actingAs($owner)->deleteJson('/api/v1/teams/'.$team->slug);

        $response->assertOk();
        $this->assertDatabaseMissing('teams', ['id' => $teamId]);
    }
}
