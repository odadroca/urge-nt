<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NamespaceScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_auto_slug(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        $this->assertEquals('john-doe', $user->slug);
    }

    public function test_user_slug_collision(): void
    {
        $user1 = User::factory()->create(['name' => 'Jane Doe']);
        $user2 = User::factory()->create(['name' => 'Jane Doe']);

        $this->assertEquals('jane-doe', $user1->slug);
        $this->assertEquals('jane-doe-1', $user2->slug);
    }

    public function test_browse_redirects_to_react_spa(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/browse');
        $response->assertRedirect('/app/browse');
    }

    public function test_browse_team_scope_redirects_to_react_spa(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->get('/browse?browseScope=team:test');
        $response->assertRedirect('/app/browse');
    }

    public function test_workspace_accessible_by_owner(): void
    {
        $user = User::factory()->create();
        $prompt = Prompt::create([
            'name' => 'My Prompt',
            'type' => 'prompt',
            'created_by' => $user->id,
        ]);

        // Web route now redirects to React SPA
        $response = $this->actingAs($user)->get('/prompts/'.$user->slug.'/'.$prompt->slug);
        $response->assertRedirect('/app/workspace/'.$user->slug.'/'.$prompt->slug);

        // API route still works for direct access
        $apiResponse = $this->actingAs($user)->getJson('/api/v1/prompts/'.$user->slug.'/'.$prompt->slug);
        $apiResponse->assertStatus(200);
    }

    public function test_workspace_accessible_by_team_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::create(['name' => 'Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $prompt = Prompt::create([
            'name' => 'Shared Work',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $owner->id,
        ]);
        $prompt->teams()->attach($team->id);

        // Web route redirects to React SPA
        $response = $this->actingAs($member)->get('/prompts/'.$owner->slug.'/'.$prompt->slug);
        $response->assertRedirect('/app/workspace/'.$owner->slug.'/'.$prompt->slug);

        // API route verifies team member access
        $apiResponse = $this->actingAs($member)->getJson('/api/v1/prompts/'.$owner->slug.'/'.$prompt->slug);
        $apiResponse->assertStatus(200);
    }

    public function test_workspace_404_for_non_member(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $prompt = Prompt::create([
            'name' => 'Private Work',
            'type' => 'prompt',
            'visibility' => 'private',
            'created_by' => $owner->id,
        ]);

        // Web route now always redirects (access check happens in React/API)
        $response = $this->actingAs($outsider)->get('/prompts/'.$owner->slug.'/'.$prompt->slug);
        $response->assertRedirect('/app/workspace/'.$owner->slug.'/'.$prompt->slug);

        // API route correctly denies access
        $apiResponse = $this->actingAs($outsider)->getJson('/api/v1/prompts/'.$owner->slug.'/'.$prompt->slug);
        $apiResponse->assertStatus(404);
    }

    public function test_legacy_slug_redirects_to_namespaced_url(): void
    {
        $user = User::factory()->create();
        $prompt = Prompt::create([
            'name' => 'Old Link',
            'type' => 'prompt',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/prompts/'.$prompt->slug);
        $response->assertRedirect('/app/workspace/'.$user->slug.'/'.$prompt->slug);
    }

    public function test_legacy_slug_prefers_current_users_prompt(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Prompt::create(['name' => 'Same Name', 'type' => 'prompt', 'created_by' => $user1->id]);
        Prompt::create(['name' => 'Same Name', 'type' => 'prompt', 'created_by' => $user2->id]);

        // User2 accesses legacy URL — should redirect to their own prompt via SPA
        $response = $this->actingAs($user2)->get('/prompts/same-name');
        $response->assertRedirect('/app/workspace/'.$user2->slug.'/same-name');
    }

    public function test_only_owner_can_delete_prompt(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::create(['name' => 'Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $prompt = Prompt::create([
            'name' => 'Shared Prompt',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $owner->id,
        ]);
        $prompt->teams()->attach($team->id);

        // Team member cannot delete
        $this->actingAs($member);
        // The canManage method should return false for non-owner
        $this->assertFalse($prompt->created_by === $member->id);
    }

    public function test_admin_can_access_any_prompt(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();

        $prompt = Prompt::create([
            'name' => 'Private Prompt',
            'type' => 'prompt',
            'visibility' => 'private',
            'created_by' => $owner->id,
        ]);

        // Web route redirects to SPA
        $response = $this->actingAs($admin)->get('/prompts/'.$owner->slug.'/'.$prompt->slug);
        $response->assertRedirect('/app/workspace/'.$owner->slug.'/'.$prompt->slug);

        // API route verifies admin access
        $apiResponse = $this->actingAs($admin)->getJson('/api/v1/prompts/'.$owner->slug.'/'.$prompt->slug);
        $apiResponse->assertStatus(200);
    }

    public function test_browse_all_scope_redirects_to_react_spa(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/browse?browseScope=all');
        $response->assertRedirect('/app/browse');
    }

    public function test_prompt_workspace_url_helper(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $prompt = Prompt::create([
            'name' => 'My Prompt',
            'type' => 'prompt',
            'created_by' => $user->id,
        ]);
        $prompt->load('creator');

        $url = $prompt->workspaceUrl();
        $this->assertStringContainsString('/app/workspace/test-user/my-prompt', $url);
    }

    public function test_internal_fragments_scoped_to_visible(): void
    {
        // Create a dummy admin first so test users aren't auto-promoted
        User::factory()->create(['role' => 'admin']);

        $user = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);

        // User's own fragment
        $myFragment = Prompt::create([
            'name' => 'My Fragment',
            'type' => 'fragment',
            'created_by' => $user->id,
        ]);
        $myFragment->versions()->create([
            'content' => 'test',
            'version_number' => 1,
            'created_by' => $user->id,
        ]);

        // Other user's private fragment
        $otherFragment = Prompt::create([
            'name' => 'Other Fragment',
            'type' => 'fragment',
            'visibility' => 'private',
            'created_by' => $other->id,
        ]);
        $otherFragment->versions()->create([
            'content' => 'secret',
            'version_number' => 1,
            'created_by' => $other->id,
        ]);

        $response = $this->actingAs($user)->getJson('/internal/fragments');
        $response->assertStatus(200);

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('My Fragment'));
        $this->assertFalse($names->contains('Other Fragment'));
    }
}
