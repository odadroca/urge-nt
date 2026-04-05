<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptApiTest extends TestCase
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

    public function test_list_prompts(): void
    {
        Prompt::create(['name' => 'Test Prompt', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/prompts', $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Test Prompt');
    }

    public function test_list_prompts_filter_by_type(): void
    {
        Prompt::create(['name' => 'Prompt', 'type' => 'prompt', 'created_by' => $this->user->id]);
        Prompt::create(['name' => 'Fragment', 'type' => 'fragment', 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/prompts?type=fragment', $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Fragment');
    }

    public function test_list_prompts_filter_by_tag(): void
    {
        Prompt::create(['name' => 'Tagged', 'type' => 'prompt', 'tags' => ['coding'], 'created_by' => $this->user->id]);
        Prompt::create(['name' => 'Untagged', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/prompts?tag=coding', $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_prompts_search(): void
    {
        Prompt::create(['name' => 'Code Review', 'type' => 'prompt', 'created_by' => $this->user->id]);
        Prompt::create(['name' => 'Email Writer', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $response = $this->getJson('/api/v1/prompts?search=code', $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_create_prompt(): void
    {
        $response = $this->postJson('/api/v1/prompts', [
            'name' => 'New Prompt',
            'type' => 'prompt',
            'description' => 'A test prompt',
            'tags' => ['test', 'api'],
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Prompt')
            ->assertJsonPath('data.slug', 'new-prompt');

        $this->assertDatabaseHas('prompts', ['name' => 'New Prompt']);
    }

    public function test_create_prompt_requires_name(): void
    {
        $response = $this->postJson('/api/v1/prompts', [
            'type' => 'prompt',
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_show_prompt(): void
    {
        $prompt = Prompt::create(['name' => 'Show Me', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Show Me');
    }

    public function test_update_prompt(): void
    {
        $prompt = Prompt::create(['name' => 'Old Name', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $response = $this->patchJson("/api/v1/prompts/{$this->user->slug}/{$prompt->slug}", [
            'name' => 'New Name',
            'description' => 'Updated description',
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('prompts', ['id' => $prompt->id, 'name' => 'New Name']);
    }
}
