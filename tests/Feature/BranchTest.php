<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptBranch;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\McpToolHandler;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Prompt $prompt;
    private VersioningService $versioningService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->versioningService = app(VersioningService::class);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Branch Test',
            'created_by' => $this->user->id,
        ]);
    }

    // --- Branch Creation ---

    public function test_first_version_auto_creates_main_branch(): void
    {
        $version = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );

        $this->prompt->refresh();
        $this->assertNotNull($this->prompt->default_branch_id);

        $branch = $this->prompt->defaultBranch;
        $this->assertEquals('main', $branch->name);
        $this->assertTrue($branch->is_default);
        $this->assertEquals($version->id, $branch->head_version_id);
    }

    public function test_create_branch_from_version(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );

        $branch = $this->versioningService->createBranch(
            $this->prompt, 'experiment', $this->user, $v1
        );

        $this->assertEquals('experiment', $branch->name);
        $this->assertFalse($branch->is_default);
        $this->assertEquals($v1->id, $branch->head_version_id);
        $this->assertEquals($v1->id, $branch->forked_from_version_id);
    }

    public function test_create_empty_branch(): void
    {
        $branch = $this->versioningService->createBranch(
            $this->prompt, 'empty-branch', $this->user
        );

        $this->assertEquals('empty-branch', $branch->name);
        $this->assertNull($branch->head_version_id);
        $this->assertNull($branch->forked_from_version_id);
    }

    public function test_branch_name_is_slugified(): void
    {
        $branch = $this->versioningService->createBranch(
            $this->prompt, 'My Feature Branch!', $this->user
        );

        $this->assertEquals('my-feature-branch', $branch->name);
    }

    // --- Versioning on Branches ---

    public function test_save_version_to_branch_updates_head(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );

        $branch = $this->versioningService->createBranch(
            $this->prompt, 'feature', $this->user, $v1
        );

        $v2 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v2 on feature'], $this->user, $branch
        );

        $branch->refresh();
        $this->assertEquals($v2->id, $branch->head_version_id);
        $this->assertEquals($branch->id, $v2->branch_id);
        $this->assertEquals(1, $v2->branch_version_number);
        $this->assertEquals(2, $v2->version_number); // global number
    }

    public function test_branch_version_numbers_increment_independently(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'main v1'], $this->user
        );
        $this->prompt->refresh();
        $mainBranch = $this->prompt->defaultBranch;

        $featureBranch = $this->versioningService->createBranch(
            $this->prompt, 'feature', $this->user, $v1
        );

        // Save to feature branch
        $fv1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'feature v1'], $this->user, $featureBranch
        );

        // Save to main branch
        $mv2 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'main v2'], $this->user, $mainBranch
        );

        // Save to feature branch again
        $fv2 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'feature v2'], $this->user, $featureBranch
        );

        // Branch version numbers are independent
        $this->assertEquals(1, $fv1->branch_version_number);
        $this->assertEquals(2, $mv2->branch_version_number); // main: v1(1), v2(2)
        $this->assertEquals(2, $fv2->branch_version_number);

        // Global version numbers are sequential
        $this->assertEquals(2, $fv1->version_number);
        $this->assertEquals(3, $mv2->version_number);
        $this->assertEquals(4, $fv2->version_number);
    }

    public function test_save_version_defaults_to_default_branch(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );

        $v2 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v2'], $this->user
        );

        $this->prompt->refresh();
        $this->assertEquals($this->prompt->default_branch_id, $v2->branch_id);
    }

    // --- Active Version Resolution ---

    public function test_active_version_resolves_from_default_branch_head(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );

        $this->prompt->refresh();
        $this->prompt->load('defaultBranch.headVersion');

        $this->assertEquals($v1->id, $this->prompt->active_version->id);
    }

    public function test_pinned_version_overrides_branch_head(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );
        $v2 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v2'], $this->user
        );

        $this->prompt->update(['pinned_version_id' => $v1->id]);
        $this->prompt->refresh();
        $this->prompt->load(['pinnedVersion', 'defaultBranch.headVersion']);

        $this->assertEquals($v1->id, $this->prompt->active_version->id);
    }

    // --- Branch Deletion ---

    public function test_delete_branch_orphans_versions(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );

        $branch = $this->versioningService->createBranch(
            $this->prompt, 'to-delete', $this->user, $v1
        );

        $fv1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'branch v1'], $this->user, $branch
        );

        $this->versioningService->deleteBranch($branch);

        // Version still exists but branch_id is null
        $fv1->refresh();
        $this->assertNull($fv1->branch_id);
        $this->assertNull($fv1->branch_version_number);

        // Branch is deleted
        $this->assertNull(PromptBranch::find($branch->id));
    }

    public function test_cannot_delete_default_branch(): void
    {
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );
        $this->prompt->refresh();

        $this->expectException(\RuntimeException::class);
        $this->versioningService->deleteBranch($this->prompt->defaultBranch);
    }

    // --- Set Default Branch ---

    public function test_set_default_branch(): void
    {
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );
        $this->prompt->refresh();

        $newBranch = $this->versioningService->createBranch(
            $this->prompt, 'new-default', $this->user
        );

        $this->versioningService->setDefaultBranch($this->prompt, $newBranch);
        $this->prompt->refresh();

        $this->assertEquals($newBranch->id, $this->prompt->default_branch_id);
        $this->assertTrue($newBranch->fresh()->is_default);
        $this->assertFalse(
            $this->prompt->branches()->where('name', 'main')->first()->is_default
        );
    }

    // --- MCP Tools ---

    public function test_mcp_list_branches(): void
    {
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );
        $this->versioningService->createBranch(
            $this->prompt, 'feature', $this->user
        );

        $handler = app(McpToolHandler::class);
        $result = $handler->callTool('list_branches', ['slug' => $this->prompt->slug], $this->user);

        $this->assertCount(2, $result);
        $names = array_column($result, 'name');
        $this->assertContains('main', $names);
        $this->assertContains('feature', $names);
    }

    public function test_mcp_create_branch(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );

        $handler = app(McpToolHandler::class);
        $result = $handler->callTool('create_branch', [
            'slug' => $this->prompt->slug,
            'name' => 'experiment',
            'from_version' => 1,
        ], $this->user);

        $this->assertEquals('experiment', $result['name']);
        $this->assertFalse($result['is_default']);
        $this->assertEquals(1, $result['head_version_number']);
    }

    public function test_mcp_get_prompt_with_branch(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'main content'], $this->user
        );
        $this->prompt->refresh();

        $branch = $this->versioningService->createBranch(
            $this->prompt, 'feature', $this->user, $v1
        );
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'feature content'], $this->user, $branch
        );

        $handler = app(McpToolHandler::class);
        $result = $handler->callTool('get_prompt', [
            'slug' => $this->prompt->slug,
            'branch' => 'feature',
        ], $this->user);

        $this->assertEquals('feature content', $result['content']);
        $this->assertEquals('feature', $result['branch']);
    }

    public function test_mcp_save_version_to_branch(): void
    {
        $v1 = $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );
        $this->prompt->refresh();

        $branch = $this->versioningService->createBranch(
            $this->prompt, 'feature', $this->user, $v1
        );

        $handler = app(McpToolHandler::class);
        $result = $handler->callTool('save_version', [
            'slug' => $this->prompt->slug,
            'content' => 'new feature content',
            'branch' => 'feature',
        ], $this->user);

        $this->assertEquals('feature', $result['branch']);
        $this->assertEquals(1, $result['branch_version_number']);
    }

    // --- API ---

    public function test_api_list_branches(): void
    {
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );
        $this->versioningService->createBranch(
            $this->prompt, 'feature', $this->user
        );

        $apiKey = app(\App\Services\ApiKeyService::class)->generateKey($this->user, 'Test');

        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/branches",
            ['Authorization' => "Bearer {$apiKey['key']}"]
        );

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_api_create_branch(): void
    {
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );

        $apiKey = app(\App\Services\ApiKeyService::class)->generateKey($this->user, 'Test');

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/branches",
            ['name' => 'experiment', 'from_version' => 1],
            ['Authorization' => "Bearer {$apiKey['key']}"]
        );

        $response->assertStatus(201);
        $this->assertEquals('experiment', $response->json('data.name'));
    }

    public function test_api_show_branch(): void
    {
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );
        $this->versioningService->createBranch(
            $this->prompt, 'feature', $this->user
        );

        $apiKey = app(\App\Services\ApiKeyService::class)->generateKey($this->user, 'Test');

        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/branches/feature",
            ['Authorization' => "Bearer {$apiKey['key']}"]
        );

        $response->assertStatus(200);
        $this->assertEquals('feature', $response->json('data.name'));
    }

    public function test_api_delete_branch(): void
    {
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );
        $this->versioningService->createBranch(
            $this->prompt, 'to-delete', $this->user
        );

        $apiKey = app(\App\Services\ApiKeyService::class)->generateKey($this->user, 'Test');

        $response = $this->deleteJson(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/branches/to-delete",
            [],
            ['Authorization' => "Bearer {$apiKey['key']}"]
        );

        $response->assertStatus(200);
        $this->assertNull($this->prompt->branches()->where('name', 'to-delete')->first());
    }

    public function test_api_cannot_delete_default_branch(): void
    {
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );

        $apiKey = app(\App\Services\ApiKeyService::class)->generateKey($this->user, 'Test');

        $response = $this->deleteJson(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/branches/main",
            [],
            ['Authorization' => "Bearer {$apiKey['key']}"]
        );

        $response->assertStatus(422);
    }

    public function test_api_set_default_branch(): void
    {
        $this->versioningService->createVersion(
            $this->prompt, ['content' => 'v1'], $this->user
        );
        $this->versioningService->createBranch(
            $this->prompt, 'feature', $this->user
        );

        $apiKey = app(\App\Services\ApiKeyService::class)->generateKey($this->user, 'Test');

        $response = $this->patchJson(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/branches/feature/default",
            [],
            ['Authorization' => "Bearer {$apiKey['key']}"]
        );

        $response->assertStatus(200);
        $this->prompt->refresh();
        $this->assertEquals('feature', $this->prompt->defaultBranch->name);
    }
}
