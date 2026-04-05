<?php

namespace Tests\Feature\Api;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateChannel;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTemplateApiTest extends TestCase
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

    public function test_list_templates(): void
    {
        PipelineTemplate::create(['name' => 'Active Template', 'is_active' => true, 'created_by' => $this->user->id]);
        PipelineTemplate::create(['name' => 'Inactive Template', 'is_active' => false, 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/pipeline-templates', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Template');
    }

    public function test_store_template(): void
    {
        $response = $this->postJson('/api/v1/pipeline-templates', [
            'name' => 'New Template',
            'description' => 'A test template',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Template')
            ->assertJsonPath('data.slug', 'new-template');

        $this->assertDatabaseHas('pipeline_templates', ['name' => 'New Template']);
    }

    public function test_store_template_requires_name(): void
    {
        $response = $this->postJson('/api/v1/pipeline-templates', [
            'description' => 'Missing name',
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_show_template(): void
    {
        $template = PipelineTemplate::create(['name' => 'Show Me', 'created_by' => $this->user->id]);
        PipelineTemplateChannel::create([
            'pipeline_template_id' => $template->id,
            'role_label' => 'Analyst',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/v1/pipeline-templates/{$template->slug}", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Show Me')
            ->assertJsonCount(1, 'data.channels');
    }

    public function test_update_template(): void
    {
        $template = PipelineTemplate::create(['name' => 'Original', 'created_by' => $this->user->id]);

        $response = $this->patchJson("/api/v1/pipeline-templates/{$template->slug}", [
            'name' => 'Updated',
            'is_active' => false,
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_destroy_template(): void
    {
        $template = PipelineTemplate::create(['name' => 'Delete Me', 'created_by' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/pipeline-templates/{$template->slug}", [], $this->headers);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('pipeline_templates', ['id' => $template->id]);
    }

    public function test_add_channel(): void
    {
        $template = PipelineTemplate::create(['name' => 'Template', 'created_by' => $this->user->id]);

        $response = $this->postJson("/api/v1/pipeline-templates/{$template->slug}/channels", [
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
        $template = PipelineTemplate::create(['name' => 'Template', 'created_by' => $this->user->id]);

        $response = $this->postJson("/api/v1/pipeline-templates/{$template->slug}/channels", [
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
        $template = PipelineTemplate::create(['name' => 'Template', 'created_by' => $this->user->id]);
        $channel = PipelineTemplateChannel::create([
            'pipeline_template_id' => $template->id,
            'role_label' => 'Old Label',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->patchJson("/api/v1/pipeline-templates/{$template->slug}/channels/{$channel->id}", [
            'role_label' => 'New Label',
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.role_label', 'New Label');
    }

    public function test_update_channel_wrong_template(): void
    {
        $template1 = PipelineTemplate::create(['name' => 'Template 1', 'created_by' => $this->user->id]);
        $template2 = PipelineTemplate::create(['name' => 'Template 2', 'created_by' => $this->user->id]);
        $channel = PipelineTemplateChannel::create([
            'pipeline_template_id' => $template2->id,
            'role_label' => 'Analyst',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->patchJson("/api/v1/pipeline-templates/{$template1->slug}/channels/{$channel->id}", [
            'role_label' => 'Hacked',
        ], $this->headers);

        $response->assertStatus(404);
    }

    public function test_remove_channel(): void
    {
        $template = PipelineTemplate::create(['name' => 'Template', 'created_by' => $this->user->id]);
        $channel = PipelineTemplateChannel::create([
            'pipeline_template_id' => $template->id,
            'role_label' => 'Remove Me',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->deleteJson("/api/v1/pipeline-templates/{$template->slug}/channels/{$channel->id}", [], $this->headers);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('pipeline_template_channels', ['id' => $channel->id]);
    }

    public function test_remove_channel_wrong_template(): void
    {
        $template1 = PipelineTemplate::create(['name' => 'Template 1', 'created_by' => $this->user->id]);
        $template2 = PipelineTemplate::create(['name' => 'Template 2', 'created_by' => $this->user->id]);
        $channel = PipelineTemplateChannel::create([
            'pipeline_template_id' => $template2->id,
            'role_label' => 'Belongs to T2',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $response = $this->deleteJson("/api/v1/pipeline-templates/{$template1->slug}/channels/{$channel->id}", [], $this->headers);

        $response->assertStatus(404);
        $this->assertDatabaseHas('pipeline_template_channels', ['id' => $channel->id]);
    }

    public function test_inactive_template_not_listed(): void
    {
        PipelineTemplate::create(['name' => 'Hidden', 'is_active' => false, 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/pipeline-templates', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_auto_slug_generation(): void
    {
        $response = $this->postJson('/api/v1/pipeline-templates', [
            'name' => 'My Cool Template',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'my-cool-template');
    }

    public function test_duplicate_slug_increments(): void
    {
        PipelineTemplate::create(['name' => 'Test', 'slug' => 'test', 'created_by' => $this->user->id]);

        $response = $this->postJson('/api/v1/pipeline-templates', [
            'name' => 'Test',
        ], $this->headers);

        $response->assertStatus(201);
        $this->assertNotEquals('test', $response->json('data.slug'));
    }
}
