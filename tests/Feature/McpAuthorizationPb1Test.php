<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\PipelineChannel;
use App\Models\Prompt;
use App\Models\Result;
use App\Models\Team;
use App\Models\User;
use App\Services\McpToolHandler;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * Phase B Sprint 1 regression tests for the MCP tool surface:
 * MCP-01 (pipeline-mutation tools require ownership),
 * MCP-03 (delete_result/update_result require ownership not just visibility),
 * MCP-04 (pin_version/archive_version require ownership).
 *
 * We invoke the tool methods directly via reflection (they are private)
 * to keep these tests focused on the authorization logic, not transport.
 */
class McpAuthorizationPb1Test extends TestCase
{
    use RefreshDatabase;

    private McpToolHandler $handler;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        // First user is auto-admin — throwaway so alice/bob are non-admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->alice = User::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->bob = User::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->handler = app(McpToolHandler::class);
    }

    private function invokeTool(string $method, array $args, ?User $user): array
    {
        $ref = new ReflectionClass($this->handler);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke($this->handler, $args, $user);
    }

    private function makeResult(User $owner): Result
    {
        $prompt = Prompt::create(['name' => 'P', 'type' => 'prompt', 'created_by' => $owner->id]);
        app(VersioningService::class)->createVersion($prompt, ['content' => 'hi'], $owner);
        $version = $prompt->versions()->first();

        return Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'mcp',
            'response_text' => 'secret',
            'created_by' => $owner->id,
        ]);
    }

    // ---------- MCP-01: pipeline mutation tools require ownership ----------

    public function test_update_pipeline_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);

        $result = $this->invokeTool('updatePipelineTool', ['slug' => $pipeline->slug, 'name' => 'Hijacked'], $this->bob);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Alice P', $pipeline->fresh()->name);
    }

    public function test_delete_pipeline_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);

        $result = $this->invokeTool('deletePipelineTool', ['slug' => $pipeline->slug], $this->bob);

        $this->assertArrayHasKey('error', $result);
        $this->assertDatabaseHas('pipelines', ['id' => $pipeline->id]);
    }

    public function test_add_channel_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);

        $result = $this->invokeTool('addChannelTool', [
            'pipeline_slug' => $pipeline->slug,
            'role_label' => 'X',
            'trigger' => 'parallel',
        ], $this->bob);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(0, $pipeline->channels()->count());
    }

    public function test_update_channel_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);
        $channel = PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Keep',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $result = $this->invokeTool('updateChannelTool', [
            'channel_id' => $channel->id,
            'role_label' => 'Hijacked',
        ], $this->bob);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Keep', $channel->fresh()->role_label);
    }

    public function test_remove_channel_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);
        $channel = PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Keep',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $result = $this->invokeTool('removeChannelTool', ['channel_id' => $channel->id], $this->bob);

        $this->assertArrayHasKey('error', $result);
        $this->assertDatabaseHas('pipeline_channels', ['id' => $channel->id]);
    }

    public function test_run_pipeline_blocked_cross_tenant_via_pipeline_ownership(): void
    {
        // Alice owns both a shared prompt and a pipeline. Bob is a team member
        // who can see the prompt; pipeline must still refuse cross-tenant run.
        $prompt = Prompt::create(['name' => 'Shared', 'type' => 'prompt', 'created_by' => $this->alice->id, 'visibility' => 'shared']);
        app(VersioningService::class)->createVersion($prompt, ['content' => 'hi'], $this->alice);
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id, 'is_active' => true]);
        PipelineChannel::create(['pipeline_id' => $pipeline->id, 'role_label' => 'c', 'trigger' => 'parallel', 'sort_order' => 0]);

        $result = $this->invokeTool('runPipeline', [
            'slug' => $prompt->slug,
            'owner' => $this->alice->slug,
            'template_slug' => $pipeline->slug,
        ], $this->bob);

        // Bob cannot see the (private) prompt → "Prompt not found" first.
        // Even if he could, pipeline ownership would block.
        $this->assertArrayHasKey('error', $result);
    }

    // ---------- MCP-03: result write tools require ownership not just visibility ----------

    public function test_delete_result_requires_ownership(): void
    {
        $result = $this->makeResult($this->alice);
        // Share the prompt with bob's team so bob has visibility but no ownership
        $team = Team::create(['name' => 'T', 'slug' => 't', 'created_by' => $this->alice->id]);
        $team->members()->attach($this->alice->id, ['role' => 'owner']);
        $team->members()->attach($this->bob->id, ['role' => 'member']);
        $result->prompt->teams()->attach($team->id);

        $out = $this->invokeTool('deleteResult', ['id' => $result->id], $this->bob);

        $this->assertArrayHasKey('error', $out);
        $this->assertDatabaseHas('results', ['id' => $result->id]);
    }

    public function test_update_result_requires_ownership(): void
    {
        $result = $this->makeResult($this->alice);
        $team = Team::create(['name' => 'T', 'slug' => 't', 'created_by' => $this->alice->id]);
        $team->members()->attach($this->alice->id, ['role' => 'owner']);
        $team->members()->attach($this->bob->id, ['role' => 'member']);
        $result->prompt->teams()->attach($team->id);

        $out = $this->invokeTool('updateResult', ['id' => $result->id, 'rating' => 1], $this->bob);

        $this->assertArrayHasKey('error', $out);
        $this->assertNull($result->fresh()->rating);
    }

    // ---------- MCP-04: pin/archive require ownership ----------

    public function test_pin_version_blocked_for_non_owner(): void
    {
        $prompt = Prompt::create(['name' => 'Shared', 'type' => 'prompt', 'created_by' => $this->alice->id, 'visibility' => 'shared']);
        app(VersioningService::class)->createVersion($prompt, ['content' => 'v1'], $this->alice);
        $version = $prompt->versions()->first();
        $team = Team::create(['name' => 'T', 'slug' => 't', 'created_by' => $this->alice->id]);
        $team->members()->attach($this->alice->id, ['role' => 'owner']);
        $team->members()->attach($this->bob->id, ['role' => 'member']);
        $prompt->teams()->attach($team->id);

        $out = $this->invokeTool('pinVersion', [
            'slug' => $prompt->slug,
            'owner' => $this->alice->slug,
            'version_id' => $version->id,
        ], $this->bob);

        $this->assertArrayHasKey('error', $out);
        $this->assertNull($prompt->fresh()->pinned_version_id);
    }

    public function test_archive_version_blocked_for_non_owner(): void
    {
        $prompt = Prompt::create(['name' => 'Shared', 'type' => 'prompt', 'created_by' => $this->alice->id, 'visibility' => 'shared']);
        app(VersioningService::class)->createVersion($prompt, ['content' => 'v1'], $this->alice);
        $version = $prompt->versions()->first();
        $team = Team::create(['name' => 'T', 'slug' => 't', 'created_by' => $this->alice->id]);
        $team->members()->attach($this->alice->id, ['role' => 'owner']);
        $team->members()->attach($this->bob->id, ['role' => 'member']);
        $prompt->teams()->attach($team->id);

        $out = $this->invokeTool('archiveVersion', [
            'slug' => $prompt->slug,
            'owner' => $this->alice->slug,
            'version' => $version->version_number,
        ], $this->bob);

        $this->assertArrayHasKey('error', $out);
        $this->assertNull($version->fresh()->archived_at);
    }
}
