<?php

namespace Tests\Feature\Api;

use App\Models\Pipeline;
use App\Models\PipelineChannel;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $result = app(ApiKeyService::class)->generateKey($this->user, 'Test Key');
        $this->apiKey = $result['key'];
        $this->headers = ['Authorization' => "Bearer {$this->apiKey}"];
    }

    public function test_list_pipelines(): void
    {
        Pipeline::create(['name' => 'Active Pipeline', 'is_active' => true, 'created_by' => $this->user->id]);
        Pipeline::create(['name' => 'Inactive Pipeline', 'is_active' => false, 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/pipelines', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Pipeline');
    }

    public function test_store_pipeline(): void
    {
        $response = $this->postJson('/api/v1/pipelines', [
            'name' => 'New Pipeline',
            'description' => 'A test pipeline',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Pipeline')
            ->assertJsonPath('data.slug', 'new-pipeline');

        $this->assertDatabaseHas('pipelines', ['name' => 'New Pipeline']);
    }

    public function test_store_pipeline_requires_name(): void
    {
        $response = $this->postJson('/api/v1/pipelines', [
            'description' => 'Missing name',
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_show_pipeline(): void
    {
        $pipeline = Pipeline::create(['name' => 'Show Me', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Analyst',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/v1/pipelines/{$pipeline->slug}", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Show Me')
            ->assertJsonCount(1, 'data.channels');
    }

    public function test_update_pipeline(): void
    {
        $pipeline = Pipeline::create(['name' => 'Original', 'created_by' => $this->user->id]);

        $response = $this->patchJson("/api/v1/pipelines/{$pipeline->slug}", [
            'name' => 'Updated',
            'is_active' => false,
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_destroy_pipeline(): void
    {
        $pipeline = Pipeline::create(['name' => 'Delete Me', 'created_by' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/pipelines/{$pipeline->slug}", [], $this->headers);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('pipelines', ['id' => $pipeline->id]);
    }

    public function test_add_channel(): void
    {
        $pipeline = Pipeline::create(['name' => 'Pipeline', 'created_by' => $this->user->id]);

        $response = $this->postJson("/api/v1/pipelines/{$pipeline->slug}/channels", [
            'role_label' => 'Reviewer',
            'trigger' => 'parallel',
            'system_prompt' => 'You are a reviewer.',
            'sort_order' => 1,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.role_label', 'Reviewer')
            ->assertJsonPath('data.trigger', 'parallel');
    }

    public function test_add_synthesis_channel(): void
    {
        $pipeline = Pipeline::create(['name' => 'Pipeline', 'created_by' => $this->user->id]);

        $response = $this->postJson("/api/v1/pipelines/{$pipeline->slug}/channels", [
            'role_label' => 'Synthesizer',
            'trigger' => 'synthesis',
            'system_prompt' => 'Synthesize the results.',
            'sort_order' => 99,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.trigger', 'synthesis');
    }

    public function test_update_channel(): void
    {
        $pipeline = Pipeline::create(['name' => 'Pipeline', 'created_by' => $this->user->id]);
        $channel = PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Old Label',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->patchJson("/api/v1/pipelines/{$pipeline->slug}/channels/{$channel->id}", [
            'role_label' => 'New Label',
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.role_label', 'New Label');
    }

    public function test_update_channel_wrong_pipeline(): void
    {
        $pipeline1 = Pipeline::create(['name' => 'Pipeline 1', 'created_by' => $this->user->id]);
        $pipeline2 = Pipeline::create(['name' => 'Pipeline 2', 'created_by' => $this->user->id]);
        $channel = PipelineChannel::create([
            'pipeline_id' => $pipeline2->id,
            'role_label' => 'Analyst',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->patchJson("/api/v1/pipelines/{$pipeline1->slug}/channels/{$channel->id}", [
            'role_label' => 'Hacked',
        ], $this->headers);

        $response->assertStatus(404);
    }

    public function test_remove_channel(): void
    {
        $pipeline = Pipeline::create(['name' => 'Pipeline', 'created_by' => $this->user->id]);
        $channel = PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Remove Me',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->deleteJson("/api/v1/pipelines/{$pipeline->slug}/channels/{$channel->id}", [], $this->headers);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('pipeline_channels', ['id' => $channel->id]);
    }

    public function test_remove_channel_wrong_pipeline(): void
    {
        $pipeline1 = Pipeline::create(['name' => 'Pipeline 1', 'created_by' => $this->user->id]);
        $pipeline2 = Pipeline::create(['name' => 'Pipeline 2', 'created_by' => $this->user->id]);
        $channel = PipelineChannel::create([
            'pipeline_id' => $pipeline2->id,
            'role_label' => 'Belongs to P2',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->deleteJson("/api/v1/pipelines/{$pipeline1->slug}/channels/{$channel->id}", [], $this->headers);

        $response->assertStatus(404);
        $this->assertDatabaseHas('pipeline_channels', ['id' => $channel->id]);
    }

    public function test_inactive_pipeline_not_listed(): void
    {
        Pipeline::create(['name' => 'Hidden', 'is_active' => false, 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/pipelines', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_auto_slug_generation(): void
    {
        $response = $this->postJson('/api/v1/pipelines', [
            'name' => 'My Cool Pipeline',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'my-cool-pipeline');
    }

    public function test_duplicate_slug_increments(): void
    {
        Pipeline::create(['name' => 'Test', 'slug' => 'test', 'created_by' => $this->user->id]);

        $response = $this->postJson('/api/v1/pipelines', [
            'name' => 'Test',
        ], $this->headers);

        $response->assertStatus(201);
        $this->assertNotEquals('test', $response->json('data.slug'));
    }
}
