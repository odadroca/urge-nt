<?php

namespace Tests\Feature\Api;

use App\Models\Prompt;
use App\Models\Result;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Prompt $prompt;
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
        $this->headers = ['Authorization' => "Bearer {$result['key']}"];

        $this->prompt = Prompt::create(['name' => 'Result Test', 'type' => 'prompt', 'created_by' => $this->user->id]);
        app(VersioningService::class)->createVersion($this->prompt, ['content' => 'Test content'], $this->user);
    }

    public function test_list_results(): void
    {
        $version = $this->prompt->versions()->first();
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'response_text' => 'Test response',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/results", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_create_result(): void
    {
        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/results", [
            'version' => 1,
            'response_text' => 'The LLM response',
            'provider_name' => 'OpenAI',
            'model_name' => 'gpt-4',
            'rating' => 5,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.response_text', 'The LLM response')
            ->assertJsonPath('data.source', 'api');

        $this->assertDatabaseHas('results', ['response_text' => 'The LLM response', 'provider_name' => 'OpenAI']);
    }

    public function test_create_result_requires_version_and_response(): void
    {
        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/results", [
            'notes' => 'Missing required fields',
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_show_result(): void
    {
        $version = $this->prompt->versions()->first();
        $result = Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'response_text' => 'Show me',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/results/{$result->id}", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.response_text', 'Show me');
    }

    public function test_update_result(): void
    {
        $version = $this->prompt->versions()->first();
        $result = Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'response_text' => 'Update me',
            'created_by' => $this->user->id,
        ]);

        $response = $this->patchJson("/api/v1/results/{$result->id}", [
            'rating' => 4,
            'starred' => true,
            'notes' => 'Great result',
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.starred', true)
            ->assertJsonPath('data.notes', 'Great result');
    }

    public function test_filter_results_by_starred(): void
    {
        $version = $this->prompt->versions()->first();
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'response_text' => 'Starred',
            'starred' => true,
            'created_by' => $this->user->id,
        ]);
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'response_text' => 'Not starred',
            'starred' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/results?starred=true", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }
}
