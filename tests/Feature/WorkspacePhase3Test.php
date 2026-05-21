<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePhase3Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $result = app(ApiKeyService::class)->generateKey($this->user, 'Test Key');
        $this->headers = ['Authorization' => "Bearer {$result['key']}"];
    }

    public function test_prompt_show_returns_active_version(): void
    {
        $prompt = Prompt::create([
            'name' => 'Mode Test',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Test content',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', $prompt->slug);
    }

    public function test_versions_list_returns_versions(): void
    {
        $prompt = Prompt::create([
            'name' => 'Switch Mode Test',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/versions",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_create_version_with_variable_metadata(): void
    {
        $prompt = Prompt::create([
            'name' => 'Meta Save Test',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Initial',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/versions",
            [
                'content' => 'Hello {{name}}',
                'variable_metadata' => [
                    'name' => [
                        'type' => 'string',
                        'default' => 'World',
                        'description' => 'User name',
                    ],
                ],
            ],
            $this->headers
        );

        $response->assertStatus(201);

        $newVersion = PromptVersion::where('prompt_id', $prompt->id)
            ->where('version_number', 2)
            ->first();
        $this->assertNotNull($newVersion);
        $this->assertEquals('string', $newVersion->variable_metadata['name']['type']);
        $this->assertEquals('World', $newVersion->variable_metadata['name']['default']);
    }

    public function test_version_show_returns_metadata(): void
    {
        $prompt = Prompt::create([
            'name' => 'Meta Load Test',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}}',
            'variables' => ['name'],
            'variable_metadata' => ['name' => ['type' => 'string', 'default' => 'World']],
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/versions/1",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.variable_metadata.name.type', 'string')
            ->assertJsonPath('data.variable_metadata.name.default', 'World');
    }

    public function test_prompt_show_includes_version_and_result_counts(): void
    {
        $prompt = Prompt::create([
            'name' => 'Panel Test',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['slug', 'versions_count', 'results_count'],
            ]);
    }

    public function test_results_list_returns_multiple_results(): void
    {
        $prompt = Prompt::create([
            'name' => 'Compare Test',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'manual',
            'provider_name' => 'GPT-4',
            'response_text' => 'Response 1',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'manual',
            'provider_name' => 'Claude',
            'response_text' => 'Response 2',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/results",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $data = $response->json('data');
        $providers = array_column($data, 'provider_name');
        $this->assertContains('GPT-4', $providers);
        $this->assertContains('Claude', $providers);
    }

    public function test_versions_list_with_multiple_versions(): void
    {
        $prompt = Prompt::create([
            'name' => 'Diff Test',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Version 1',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 2,
            'content' => 'Version 2',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/versions",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }
}
