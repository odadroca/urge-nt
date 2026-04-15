<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\Result;
use App\Models\Team;
use App\Models\User;
use App\Services\McpToolHandler;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpToolHandlerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private McpToolHandler $handler;
    private Prompt $prompt;

    protected function setUp(): void
    {
        parent::setUp();
        // First user auto-becomes admin, so create a throwaway admin first
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->handler = app(McpToolHandler::class);
        $this->prompt = Prompt::create(['name' => 'MCP Test', 'type' => 'prompt', 'created_by' => $this->user->id]);
        app(VersioningService::class)->createVersion($this->prompt, [
            'content' => 'Hello {{name}}, you are {{role}}.',
        ], $this->user);
    }

    public function test_get_prompt(): void
    {
        $result = $this->handler->callTool('get_prompt', ['slug' => $this->prompt->slug], $this->user);

        $this->assertEquals($this->prompt->slug, $result['slug']);
        $this->assertEquals('Hello {{name}}, you are {{role}}.', $result['content']);
        $this->assertEquals(['name', 'role'], $result['variables']);
        $this->assertEquals($this->user->slug, $result['owner']);
    }

    public function test_get_prompt_not_found(): void
    {
        $result = $this->handler->callTool('get_prompt', ['slug' => 'nonexistent'], $this->user);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_list_prompts(): void
    {
        $result = $this->handler->callTool('list_prompts', [], $this->user);

        $this->assertCount(1, $result);
        $this->assertEquals($this->prompt->slug, $result[0]['slug']);
    }

    public function test_list_prompts_with_type_filter(): void
    {
        Prompt::create(['name' => 'Fragment', 'type' => 'fragment', 'created_by' => $this->user->id]);

        $result = $this->handler->callTool('list_prompts', ['type' => 'fragment'], $this->user);

        $this->assertCount(1, $result);
        $this->assertEquals('fragment', $result[0]['type']);
    }

    public function test_render_prompt(): void
    {
        $result = $this->handler->callTool('render_prompt', [
            'slug' => $this->prompt->slug,
            'variables' => ['name' => 'Claude', 'role' => 'assistant'],
        ], $this->user);

        $this->assertEquals('Hello Claude, you are assistant.', $result['rendered']);
    }

    public function test_save_version(): void
    {
        $result = $this->handler->callTool('save_version', [
            'slug' => $this->prompt->slug,
            'content' => 'Updated: {{name}}',
            'commit_message' => 'Update via MCP',
        ], $this->user);

        $this->assertEquals(2, $result['version_number']);
        $this->assertEquals(['name'], $result['variables']);
    }

    public function test_store_result(): void
    {
        $result = $this->handler->callTool('store_result', [
            'slug' => $this->prompt->slug,
            'version' => 1,
            'response_text' => 'MCP result',
            'provider' => 'Anthropic',
            'model' => 'claude-3.5',
        ], $this->user);

        $this->assertTrue($result['created']);
        $this->assertDatabaseHas('results', [
            'response_text' => 'MCP result',
            'source' => 'mcp',
        ]);
    }

    public function test_get_results(): void
    {
        $version = $this->prompt->versions()->first();
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'mcp',
            'response_text' => 'Result 1',
            'created_by' => $this->user->id,
        ]);

        $result = $this->handler->callTool('get_results', ['slug' => $this->prompt->slug], $this->user);

        $this->assertCount(1, $result);
        $this->assertEquals('Result 1', $result[0]['response_text']);
    }

    public function test_unknown_tool(): void
    {
        $result = $this->handler->callTool('unknown_tool', []);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_read_resource_prompts_list(): void
    {
        $result = $this->handler->readResource('urge://prompts', $this->user);

        $this->assertEquals('application/json', $result['mimeType']);
        $decoded = json_decode($result['text'], true);
        $this->assertCount(1, $decoded);
        $this->assertEquals($this->user->slug, $decoded[0]['owner']);
    }

    public function test_read_resource_prompt_content(): void
    {
        $result = $this->handler->readResource("urge://prompts/{$this->prompt->slug}", $this->user);

        $this->assertEquals('text/plain', $result['mimeType']);
        $this->assertEquals('Hello {{name}}, you are {{role}}.', $result['text']);
    }

    public function test_read_resource_version_content(): void
    {
        $result = $this->handler->readResource("urge://prompts/{$this->prompt->slug}/v/1", $this->user);

        $this->assertEquals('text/plain', $result['mimeType']);
        $this->assertEquals('Hello {{name}}, you are {{role}}.', $result['text']);
    }

    public function test_get_tool_definitions(): void
    {
        $tools = $this->handler->getToolDefinitions();

        $this->assertCount(20, $tools);
        $names = array_column($tools, 'name');
        $this->assertContains('get_prompt', $names);
        $this->assertContains('render_prompt', $names);
        $this->assertContains('share_prompt', $names);
        $this->assertContains('list_teams', $names);
        $this->assertContains('list_branches', $names);
        $this->assertContains('create_branch', $names);
        $this->assertContains('list_templates', $names);
        $this->assertContains('run_template', $names);
    }

    public function test_get_server_info(): void
    {
        $info = $this->handler->getServerInfo();

        $this->assertEquals('urge', $info['name']);
        $this->assertEquals('2.0.0', $info['version']);
    }

    // --- Namespace & Visibility Tests ---

    public function test_get_prompt_with_owner_namespace(): void
    {
        $result = $this->handler->callTool('get_prompt', [
            'slug' => $this->prompt->slug,
            'owner' => $this->user->slug,
        ], $this->user);

        $this->assertEquals($this->prompt->slug, $result['slug']);
        $this->assertEquals($this->user->slug, $result['owner']);
    }

    public function test_get_prompt_without_owner_prefers_own(): void
    {
        // Create a prompt with the same slug for the other user
        $otherPrompt = Prompt::create([
            'name' => 'MCP Test',
            'type' => 'prompt',
            'created_by' => $this->otherUser->id,
        ]);
        app(VersioningService::class)->createVersion($otherPrompt, [
            'content' => 'Other user content',
        ], $this->otherUser);

        // Without owner, should prefer current user's prompt
        $result = $this->handler->callTool('get_prompt', ['slug' => $this->prompt->slug], $this->user);

        $this->assertEquals($this->user->slug, $result['owner']);
        $this->assertStringContainsString('Hello {{name}}', $result['content']);
    }

    public function test_get_prompt_respects_visibility(): void
    {
        // Other user's private prompt
        $privatePrompt = Prompt::create([
            'name' => 'Private Prompt',
            'type' => 'prompt',
            'visibility' => 'private',
            'created_by' => $this->otherUser->id,
        ]);
        app(VersioningService::class)->createVersion($privatePrompt, ['content' => 'Secret'], $this->otherUser);

        // Current user cannot see it
        $result = $this->handler->callTool('get_prompt', [
            'slug' => $privatePrompt->slug,
            'owner' => $this->otherUser->slug,
        ], $this->user);
        $this->assertArrayHasKey('error', $result);

        // Share via team
        $team = Team::create(['name' => 'Test Team', 'created_by' => $this->otherUser->id]);
        $team->members()->attach($this->otherUser->id, ['role' => 'owner']);
        $team->members()->attach($this->user->id, ['role' => 'member']);
        $privatePrompt->teams()->attach($team->id);
        $privatePrompt->update(['visibility' => 'shared']);

        // Now current user can see it
        $result = $this->handler->callTool('get_prompt', [
            'slug' => $privatePrompt->slug,
            'owner' => $this->otherUser->slug,
        ], $this->user);
        $this->assertEquals($privatePrompt->slug, $result['slug']);
    }

    public function test_list_prompts_scope_mine(): void
    {
        Prompt::create(['name' => 'Other Prompt', 'type' => 'prompt', 'created_by' => $this->otherUser->id]);

        $result = $this->handler->callTool('list_prompts', ['scope' => 'mine'], $this->user);

        $this->assertCount(1, $result);
        $this->assertEquals($this->user->slug, $result[0]['owner']);
    }

    public function test_list_prompts_scope_shared(): void
    {
        $team = Team::create(['name' => 'Shared Team', 'created_by' => $this->otherUser->id]);
        $team->members()->attach($this->otherUser->id, ['role' => 'owner']);
        $team->members()->attach($this->user->id, ['role' => 'member']);

        $sharedPrompt = Prompt::create([
            'name' => 'Shared Prompt',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $this->otherUser->id,
        ]);
        $sharedPrompt->teams()->attach($team->id);

        $result = $this->handler->callTool('list_prompts', ['scope' => 'shared'], $this->user);

        $this->assertCount(1, $result);
        $this->assertEquals($this->otherUser->slug, $result[0]['owner']);
    }

    public function test_list_prompts_scope_team(): void
    {
        $team = Team::create(['name' => 'My Team', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);
        $team->members()->attach($this->otherUser->id, ['role' => 'member']);

        $teamPrompt = Prompt::create([
            'name' => 'Team Prompt',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $this->user->id,
        ]);
        $teamPrompt->teams()->attach($team->id);

        $result = $this->handler->callTool('list_prompts', ['scope' => 'team:my-team'], $this->user);

        $this->assertCount(1, $result);
        $this->assertEquals('team-prompt', $result[0]['slug']);
    }

    public function test_list_prompts_scope_all(): void
    {
        $team = Team::create(['name' => 'All Team', 'created_by' => $this->otherUser->id]);
        $team->members()->attach($this->otherUser->id, ['role' => 'owner']);
        $team->members()->attach($this->user->id, ['role' => 'member']);

        $sharedPrompt = Prompt::create([
            'name' => 'All Shared',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $this->otherUser->id,
        ]);
        $sharedPrompt->teams()->attach($team->id);

        $result = $this->handler->callTool('list_prompts', ['scope' => 'all'], $this->user);

        // Should include own prompt + shared prompt
        $this->assertCount(2, $result);
    }

    public function test_list_prompts_returns_owner_field(): void
    {
        $result = $this->handler->callTool('list_prompts', [], $this->user);

        $this->assertArrayHasKey('owner', $result[0]);
        $this->assertEquals($this->user->slug, $result[0]['owner']);
    }

    public function test_delete_prompt_owner_only(): void
    {
        $team = Team::create(['name' => 'Del Team', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);
        $team->members()->attach($this->otherUser->id, ['role' => 'member']);

        $this->prompt->teams()->attach($team->id);
        $this->prompt->update(['visibility' => 'shared']);

        // Other user (not owner, not admin) cannot delete
        $result = $this->handler->callTool('delete_prompt', [
            'slug' => $this->prompt->slug,
            'owner' => $this->user->slug,
        ], $this->otherUser);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('owner', $result['error']);

        // Owner can delete
        $result = $this->handler->callTool('delete_prompt', [
            'slug' => $this->prompt->slug,
        ], $this->user);
        $this->assertTrue($result['deleted']);
    }

    public function test_delete_prompt_admin_can_delete_any(): void
    {
        $admin = User::create([
            'name' => 'Admin Two',
            'email' => 'admin2@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $otherPrompt = Prompt::create([
            'name' => 'Admin Delete Test',
            'type' => 'prompt',
            'created_by' => $this->otherUser->id,
        ]);

        $result = $this->handler->callTool('delete_prompt', [
            'slug' => $otherPrompt->slug,
            'owner' => $this->otherUser->slug,
        ], $admin);
        $this->assertTrue($result['deleted']);
    }

    public function test_share_prompt(): void
    {
        $team = Team::create(['name' => 'Share Team', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);

        $result = $this->handler->callTool('share_prompt', [
            'slug' => $this->prompt->slug,
            'team_slug' => $team->slug,
        ], $this->user);

        $this->assertTrue($result['shared']);
        $this->assertEquals($team->slug, $result['team']);
        $this->assertTrue($this->prompt->fresh()->teams()->where('teams.id', $team->id)->exists());
        $this->assertEquals('shared', $this->prompt->fresh()->visibility);
    }

    public function test_share_prompt_non_owner_rejected(): void
    {
        $team = Team::create(['name' => 'Share Reject Team', 'created_by' => $this->otherUser->id]);
        $team->members()->attach($this->otherUser->id, ['role' => 'owner']);
        $team->members()->attach($this->user->id, ['role' => 'member']);

        // Make the prompt visible to otherUser via team
        $this->prompt->teams()->attach($team->id);
        $this->prompt->update(['visibility' => 'shared']);

        // Other user tries to share — not the owner
        $result = $this->handler->callTool('share_prompt', [
            'slug' => $this->prompt->slug,
            'team_slug' => $team->slug,
        ], $this->otherUser);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('owner', $result['error']);
    }

    public function test_list_teams(): void
    {
        $team = Team::create(['name' => 'List Team', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);

        $result = $this->handler->callTool('list_teams', [], $this->user);

        $this->assertCount(1, $result);
        $this->assertEquals($team->slug, $result[0]['slug']);
        $this->assertEquals('owner', $result[0]['role']);
        $this->assertArrayHasKey('member_count', $result[0]);
        $this->assertArrayHasKey('prompt_count', $result[0]);
    }

    public function test_list_teams_no_user_returns_error(): void
    {
        $result = $this->handler->callTool('list_teams', []);

        $this->assertArrayHasKey('error', $result);
    }

    // --- Resource Namespace Tests ---

    public function test_resource_prompts_respects_visibility(): void
    {
        // Other user's private prompt should not appear
        Prompt::create([
            'name' => 'Invisible',
            'type' => 'prompt',
            'visibility' => 'private',
            'created_by' => $this->otherUser->id,
        ]);

        $result = $this->handler->readResource('urge://prompts', $this->user);
        $decoded = json_decode($result['text'], true);

        // Only user's own prompt should appear
        $this->assertCount(1, $decoded);
        $this->assertEquals($this->user->slug, $decoded[0]['owner']);
    }

    public function test_resource_namespaced_prompt(): void
    {
        $result = $this->handler->readResource(
            "urge://prompts/{$this->user->slug}/{$this->prompt->slug}",
            $this->user
        );

        $this->assertEquals('text/plain', $result['mimeType']);
        $this->assertEquals('Hello {{name}}, you are {{role}}.', $result['text']);
    }

    public function test_resource_namespaced_version(): void
    {
        $result = $this->handler->readResource(
            "urge://prompts/{$this->user->slug}/{$this->prompt->slug}/v/1",
            $this->user
        );

        $this->assertEquals('text/plain', $result['mimeType']);
        $this->assertEquals('Hello {{name}}, you are {{role}}.', $result['text']);
    }

    public function test_resource_legacy_fallback(): void
    {
        // Legacy single-slug URI should still work
        $result = $this->handler->readResource("urge://prompts/{$this->prompt->slug}", $this->user);

        $this->assertEquals('text/plain', $result['mimeType']);
        $this->assertEquals('Hello {{name}}, you are {{role}}.', $result['text']);
    }

    public function test_resource_teams(): void
    {
        $team = Team::create(['name' => 'Resource Team', 'created_by' => $this->user->id]);
        $team->members()->attach($this->user->id, ['role' => 'owner']);

        $result = $this->handler->readResource('urge://teams', $this->user);

        $this->assertEquals('application/json', $result['mimeType']);
        $decoded = json_decode($result['text'], true);
        $this->assertCount(1, $decoded);
        $this->assertEquals($team->slug, $decoded[0]['slug']);
    }

    public function test_resource_teams_requires_user(): void
    {
        $result = $this->handler->readResource('urge://teams');

        $this->assertArrayHasKey('error', $result);
    }
}
