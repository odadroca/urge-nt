<?php

namespace Tests\Feature\Api;

use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NamespaceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;
    private User $otherUser;
    private array $userHeaders;
    private array $otherHeaders;
    private array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        // First user auto-becomes admin
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $adminKey = app(ApiKeyService::class)->generateKey($this->admin, 'Admin Key');
        $this->adminHeaders = ['Authorization' => "Bearer {$adminKey['key']}"];

        $this->user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);
        $userKey = app(ApiKeyService::class)->generateKey($this->user, 'User Key');
        $this->userHeaders = ['Authorization' => "Bearer {$userKey['key']}"];

        $this->otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);
        $otherKey = app(ApiKeyService::class)->generateKey($this->otherUser, 'Other Key');
        $this->otherHeaders = ['Authorization' => "Bearer {$otherKey['key']}"];
    }

    public function test_api_list_prompts_respects_visibility(): void
    {
        // User's own prompt (private)
        Prompt::create(['name' => 'My Prompt', 'type' => 'prompt', 'visibility' => 'private', 'created_by' => $this->user->id]);

        // Other user's private prompt
        Prompt::create(['name' => 'Other Private', 'type' => 'prompt', 'visibility' => 'private', 'created_by' => $this->otherUser->id]);

        // Regular user should only see their own
        $response = $this->getJson('/api/v1/prompts', $this->userHeaders);
        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'My Prompt');

        // Admin sees all
        $response = $this->getJson('/api/v1/prompts', $this->adminHeaders);
        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_api_get_prompt_with_namespace(): void
    {
        $prompt = Prompt::create(['name' => 'Namespaced', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}", $this->userHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Namespaced');
    }

    public function test_api_create_prompt_defaults_to_private(): void
    {
        $response = $this->postJson('/api/v1/prompts', [
            'name' => 'Default Vis',
            'type' => 'prompt',
        ], $this->userHeaders);

        $response->assertStatus(201);
        $this->assertDatabaseHas('prompts', [
            'name' => 'Default Vis',
            'visibility' => 'private',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_api_update_prompt_owner_only(): void
    {
        $prompt = Prompt::create(['name' => 'Owner Test', 'type' => 'prompt', 'created_by' => $this->user->id]);

        // Other non-admin user cannot update
        $response = $this->patchJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}", [
            'name' => 'Hacked',
        ], $this->otherHeaders);

        // Other user can't even see it (visibility scoping returns 404)
        $response->assertStatus(404);

        // Owner can update
        $response = $this->patchJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}", [
            'name' => 'Updated By Owner',
        ], $this->userHeaders);
        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated By Owner');
    }

    public function test_api_delete_prompt_owner_only(): void
    {
        $prompt = Prompt::create(['name' => 'Delete Test', 'type' => 'prompt', 'created_by' => $this->user->id]);

        // Other non-admin user cannot delete (404 due to visibility)
        $response = $this->deleteJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}", [], $this->otherHeaders);
        $response->assertStatus(404);

        // Owner can delete
        $response = $this->deleteJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}", [], $this->userHeaders);
        $response->assertStatus(200);
    }

    public function test_api_legacy_slug_redirect(): void
    {
        $prompt = Prompt::create(['name' => 'Legacy Test', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $response = $this->getJson("/api/v1/prompts/{$prompt->slug}", $this->userHeaders);

        $response->assertStatus(301)
            ->assertJsonPath('redirect', "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}");
    }

    public function test_api_share_prompt(): void
    {
        $prompt = Prompt::create(['name' => 'Share Me', 'type' => 'prompt', 'visibility' => 'private', 'created_by' => $this->user->id]);

        $team = Team::create(['name' => 'Share Team', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);
        $team->members()->attach($this->otherUser->id, ['role' => 'member']);

        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/share", [
            'team_id' => $team->id,
        ], $this->userHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Prompt shared with team.');

        $this->assertDatabaseHas('prompts', [
            'id' => $prompt->id,
            'visibility' => 'shared',
        ]);

        // Other team member can now see the prompt
        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}", $this->otherHeaders);
        $response->assertStatus(200);
    }

    public function test_api_unshare_prompt(): void
    {
        $prompt = Prompt::create(['name' => 'Unshare Me', 'type' => 'prompt', 'visibility' => 'shared', 'created_by' => $this->user->id]);

        $team = Team::create(['name' => 'Unshare Team', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);
        $prompt->teams()->attach($team->id);

        $response = $this->deleteJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/share/{$team->id}", [], $this->userHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Prompt unshared from team.');

        // Visibility should revert to private (no teams left)
        $this->assertDatabaseHas('prompts', [
            'id' => $prompt->id,
            'visibility' => 'private',
        ]);
    }
}
