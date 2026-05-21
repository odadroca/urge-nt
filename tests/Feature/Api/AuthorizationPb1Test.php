<?php

namespace Tests\Feature\Api;

use App\Models\Pipeline;
use App\Models\PipelineChannel;
use App\Models\Prompt;
use App\Models\Result;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B Sprint 1 regression tests — closes the audit findings:
 * AUTHZ-01..09, MCP-01..04 (where reachable via the REST surface),
 * DEAD-03 (legacy route removed), AUTH-12 (API key is_active=true).
 *
 * The first user created in Laravel's seeder pattern becomes admin
 * automatically (see RegisteredUserController). We deliberately create
 * a sacrificial admin first, then two non-admin tenants so that
 * cross-user assertions are not silently bypassed by the visibleTo
 * admin-override.
 */
class AuthorizationPb1Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    private array $aliceHeaders;

    private array $bobHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->alice = User::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
        ]);
        $aliceKey = app(ApiKeyService::class)->generateKey($this->alice, 'Alice Key');
        $this->aliceHeaders = ['Authorization' => "Bearer {$aliceKey['key']}"];

        $this->bob = User::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
        ]);
        $bobKey = app(ApiKeyService::class)->generateKey($this->bob, 'Bob Key');
        $this->bobHeaders = ['Authorization' => "Bearer {$bobKey['key']}"];
    }

    private function alicesResult(): Result
    {
        $prompt = Prompt::create(['name' => 'Alice Prompt', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($prompt, ['content' => 'hello'], $this->alice);
        $version = $prompt->versions()->first();

        return Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'response_text' => 'secret response',
            'created_by' => $this->alice->id,
        ]);
    }

    // ---------- AUTHZ-01: Standalone Result endpoints ----------

    public function test_result_show_blocked_cross_tenant_via_id_enumeration(): void
    {
        $result = $this->alicesResult();

        $response = $this->getJson("/api/v1/results/{$result->id}", $this->bobHeaders);

        $response->assertStatus(404);
    }

    public function test_result_update_blocked_cross_tenant(): void
    {
        $result = $this->alicesResult();

        $response = $this->patchJson("/api/v1/results/{$result->id}", ['rating' => 1], $this->bobHeaders);

        $response->assertStatus(404);
        $this->assertNull($result->fresh()->rating);
    }

    public function test_result_destroy_blocked_cross_tenant(): void
    {
        $result = $this->alicesResult();

        $response = $this->deleteJson("/api/v1/results/{$result->id}", [], $this->bobHeaders);

        $response->assertStatus(404);
        $this->assertDatabaseHas('results', ['id' => $result->id]);
    }

    public function test_result_download_blocked_cross_tenant(): void
    {
        $result = $this->alicesResult();

        $response = $this->get("/api/v1/results/{$result->id}/download", $this->bobHeaders);

        $response->assertStatus(404);
    }

    // ---------- AUTHZ-02: Evaluation endpoints ----------

    public function test_evaluate_blocked_cross_tenant(): void
    {
        $result = $this->alicesResult();

        $response = $this->postJson("/api/v1/results/{$result->id}/evaluate", [], $this->bobHeaders);

        $response->assertStatus(404);
    }

    public function test_evaluation_list_blocked_cross_tenant(): void
    {
        $result = $this->alicesResult();

        $response = $this->getJson("/api/v1/results/{$result->id}/evaluations", $this->bobHeaders);

        $response->assertStatus(404);
    }

    public function test_evaluation_latest_blocked_cross_tenant(): void
    {
        $result = $this->alicesResult();

        $response = $this->getJson("/api/v1/results/{$result->id}/evaluations/latest", $this->bobHeaders);

        $response->assertStatus(404);
    }

    // ---------- AUTHZ-03: Pipeline endpoints ----------

    public function test_pipeline_index_does_not_leak_other_tenants(): void
    {
        Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);

        $response = $this->getJson('/api/v1/pipelines', $this->bobHeaders);

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertNotContains('Alice P', $names);
    }

    public function test_pipeline_show_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);

        $response = $this->getJson("/api/v1/pipelines/{$pipeline->slug}", $this->bobHeaders);

        $response->assertStatus(404);
    }

    public function test_pipeline_update_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);

        $response = $this->patchJson("/api/v1/pipelines/{$pipeline->slug}", ['name' => 'Hijacked'], $this->bobHeaders);

        $response->assertStatus(403);
        $this->assertEquals('Alice P', $pipeline->fresh()->name);
    }

    public function test_pipeline_destroy_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);

        $response = $this->deleteJson("/api/v1/pipelines/{$pipeline->slug}", [], $this->bobHeaders);

        $response->assertStatus(403);
        $this->assertDatabaseHas('pipelines', ['id' => $pipeline->id]);
    }

    public function test_pipeline_add_channel_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);

        $response = $this->postJson(
            "/api/v1/pipelines/{$pipeline->slug}/channels",
            ['role_label' => 'X', 'trigger' => 'parallel'],
            $this->bobHeaders
        );

        $response->assertStatus(403);
        $this->assertEquals(0, $pipeline->channels()->count());
    }

    public function test_pipeline_remove_channel_blocked_cross_tenant(): void
    {
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id]);
        $channel = PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Keep me',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->deleteJson(
            "/api/v1/pipelines/{$pipeline->slug}/channels/{$channel->id}",
            [],
            $this->bobHeaders
        );

        $response->assertStatus(403);
        $this->assertDatabaseHas('pipeline_channels', ['id' => $channel->id]);
    }

    // ---------- AUTHZ-04: runPipeline visibility ----------

    public function test_run_pipeline_blocked_when_prompt_not_visible(): void
    {
        $prompt = Prompt::create(['name' => 'Alice Prompt', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($prompt, ['content' => 'hello'], $this->alice);
        $pipeline = Pipeline::create(['name' => 'Alice P', 'created_by' => $this->alice->id, 'is_active' => true]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->alice->slug}/{$prompt->slug}/run-pipeline",
            ['template_slug' => $pipeline->slug],
            $this->bobHeaders
        );

        $response->assertStatus(404);
    }

    // ---------- AUTHZ-05: API-key prompt scoping across endpoints ----------

    public function test_scoped_api_key_blocked_on_versions(): void
    {
        $allowed = Prompt::create(['name' => 'OK', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        $denied = Prompt::create(['name' => 'Forbidden', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($denied, ['content' => 'x'], $this->alice);

        $key = app(ApiKeyService::class)->generateKey($this->alice, 'Scoped', [$allowed->id]);
        $headers = ['Authorization' => "Bearer {$key['key']}"];

        $response = $this->getJson("/api/v1/prompts/{$this->alice->slug}/{$denied->slug}/versions", $headers);

        $response->assertStatus(403);
    }

    public function test_scoped_api_key_blocked_on_render(): void
    {
        $allowed = Prompt::create(['name' => 'OK', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        $denied = Prompt::create(['name' => 'Forbidden', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($denied, ['content' => 'hi'], $this->alice);

        $key = app(ApiKeyService::class)->generateKey($this->alice, 'Scoped', [$allowed->id]);
        $headers = ['Authorization' => "Bearer {$key['key']}"];

        $response = $this->postJson(
            "/api/v1/prompts/{$this->alice->slug}/{$denied->slug}/render",
            ['variables' => []],
            $headers
        );

        $response->assertStatus(403);
    }

    public function test_scoped_api_key_blocked_on_results_list(): void
    {
        $allowed = Prompt::create(['name' => 'OK', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        $denied = Prompt::create(['name' => 'Forbidden', 'type' => 'prompt', 'created_by' => $this->alice->id]);

        $key = app(ApiKeyService::class)->generateKey($this->alice, 'Scoped', [$allowed->id]);
        $headers = ['Authorization' => "Bearer {$key['key']}"];

        $response = $this->getJson("/api/v1/prompts/{$this->alice->slug}/{$denied->slug}/results", $headers);

        $response->assertStatus(403);
    }

    public function test_scoped_api_key_blocked_on_branches(): void
    {
        $allowed = Prompt::create(['name' => 'OK', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        $denied = Prompt::create(['name' => 'Forbidden', 'type' => 'prompt', 'created_by' => $this->alice->id]);

        $key = app(ApiKeyService::class)->generateKey($this->alice, 'Scoped', [$allowed->id]);
        $headers = ['Authorization' => "Bearer {$key['key']}"];

        $response = $this->getJson("/api/v1/prompts/{$this->alice->slug}/{$denied->slug}/branches", $headers);

        $response->assertStatus(403);
    }

    public function test_scoped_api_key_blocked_on_result_show(): void
    {
        $allowed = Prompt::create(['name' => 'OK', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        $denied = Prompt::create(['name' => 'Forbidden', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($denied, ['content' => 'x'], $this->alice);
        $version = $denied->versions()->first();
        $result = Result::create([
            'prompt_id' => $denied->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'response_text' => 'secret',
            'created_by' => $this->alice->id,
        ]);

        $key = app(ApiKeyService::class)->generateKey($this->alice, 'Scoped', [$allowed->id]);
        $headers = ['Authorization' => "Bearer {$key['key']}"];

        $response = $this->getJson("/api/v1/results/{$result->id}", $headers);

        $response->assertStatus(403);
    }

    // ---------- AUTHZ-07: role:admin middleware enforcement ----------

    public function test_users_index_requires_admin_via_middleware(): void
    {
        $response = $this->getJson('/api/v1/users', $this->aliceHeaders);

        $response->assertStatus(403);
    }

    public function test_provider_mutation_requires_admin_via_middleware(): void
    {
        $response = $this->postJson('/api/v1/providers', [
            'name' => 'Bad',
            'driver' => 'openai',
            'model' => 'gpt-4',
        ], $this->aliceHeaders);

        $response->assertStatus(403);
    }

    // ---------- AUTH-12: API key is_active explicit ----------

    public function test_generated_api_key_is_active(): void
    {
        $result = app(ApiKeyService::class)->generateKey($this->alice, 'Activity Test');
        $this->assertTrue($result['model']->is_active);
    }

    // ---------- AUTHZ-09: graph visibility regression ----------

    public function test_graph_nodes_does_not_leak_cross_tenant(): void
    {
        $alicesPrompt = Prompt::create(['name' => 'Alice Private', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($alicesPrompt, ['content' => 'priv'], $this->alice);

        $response = $this->getJson('/api/v1/graph/nodes', $this->bobHeaders);

        $response->assertStatus(200);
        $slugs = collect($response->json('data.prompts'))->pluck('slug')->all();
        $this->assertNotContains($alicesPrompt->slug, $slugs);
    }
}
