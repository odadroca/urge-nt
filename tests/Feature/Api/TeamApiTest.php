<?php

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        // First user auto-becomes admin
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $result = app(ApiKeyService::class)->generateKey($this->user, 'Test Key');
        $this->headers = ['Authorization' => "Bearer {$result['key']}"];
    }

    public function test_list_teams(): void
    {
        $team = Team::create(['name' => 'My Team', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);

        $response = $this->getJson('/api/v1/teams', $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'My Team')
            ->assertJsonPath('data.0.role', 'owner');
    }

    public function test_create_team(): void
    {
        $response = $this->postJson('/api/v1/teams', [
            'name' => 'New Team',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Team')
            ->assertJsonPath('data.slug', 'new-team')
            ->assertJsonPath('data.member_count', 1);

        $this->assertDatabaseHas('teams', ['name' => 'New Team']);
    }

    public function test_show_team(): void
    {
        $team = Team::create(['name' => 'Show Team', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);

        $response = $this->getJson("/api/v1/teams/{$team->slug}", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Show Team')
            ->assertJsonPath('data.members.0.id', $this->user->id);
    }

    public function test_add_member(): void
    {
        $team = Team::create(['name' => 'Team X', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);

        $newMember = User::create([
            'name' => 'New Member',
            'email' => 'member@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson("/api/v1/teams/{$team->slug}/members", [
            'email' => 'member@example.com',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'member@example.com')
            ->assertJsonPath('data.role', 'member');
    }

    public function test_remove_member(): void
    {
        $team = Team::create(['name' => 'Team Y', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);

        $member = User::create([
            'name' => 'Remove Me',
            'email' => 'remove@example.com',
            'password' => bcrypt('password'),
        ]);
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->deleteJson("/api/v1/teams/{$team->slug}/members/{$member->id}", [], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Member removed from team.');

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_delete_team(): void
    {
        $team = Team::create(['name' => 'Delete Me', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);

        $response = $this->deleteJson("/api/v1/teams/{$team->slug}", [], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Team deleted.');

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_non_member_cannot_see_team(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);
        $team = Team::create(['name' => 'Secret Team', 'created_by' => $otherUser->id]);
        $team->members()->attach($otherUser->id, ['role' => 'owner']);

        $response = $this->getJson("/api/v1/teams/{$team->slug}", $this->headers);

        $response->assertStatus(403);
    }
}
