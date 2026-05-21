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

        // response_text is encrypted at rest (LLM-05); query via response_hash.
        $this->assertDatabaseHas('results', [
            'response_hash' => hash('sha256', 'The LLM response'),
            'provider_name' => 'OpenAI',
        ]);
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

    public function test_download_result_as_markdown(): void
    {
        $version = $this->prompt->versions()->first();
        $result = Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'provider_name' => 'OpenAI',
            'model_name' => 'gpt-4',
            'response_text' => 'Downloadable response',
            'rating' => 4,
            'starred' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get("/api/v1/results/{$result->id}/download", $this->headers);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $response->assertHeader(
            'Content-Disposition',
            "attachment; filename={$this->prompt->slug}-v1-{$result->id}.md"
        );

        $body = $response->streamedContent();
        $this->assertStringStartsWith("---\n", $body);
        $this->assertStringContainsString('prompt: ' . $this->prompt->slug, $body);
        $this->assertStringContainsString('owner: ' . $this->user->slug, $body);
        $this->assertStringContainsString('provider: OpenAI', $body);
        $this->assertStringContainsString('model: gpt-4', $body);
        $this->assertStringContainsString('starred: true', $body);
        $this->assertStringContainsString('Downloadable response', $body);
    }

    public function test_download_result_of_other_users_private_prompt_returns_404(): void
    {
        // $this->user is the first user created → admin (sees everything).
        // Create two non-admin users so we can test cross-user visibility.
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);
        $stranger = User::create([
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);
        $strangerKey = app(ApiKeyService::class)->generateKey($stranger, 'Stranger Key');
        $strangerHeaders = ['Authorization' => "Bearer {$strangerKey['key']}"];

        $ownerPrompt = Prompt::create([
            'name' => 'Owner Private Prompt',
            'type' => 'prompt',
            'created_by' => $owner->id,
            'visibility' => 'private',
        ]);
        $version = app(VersioningService::class)->createVersion(
            $ownerPrompt,
            ['content' => 'secret'],
            $owner
        );
        $otherResult = Result::create([
            'prompt_id' => $ownerPrompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'response_text' => 'Should not be visible',
            'created_by' => $owner->id,
        ]);

        $response = $this->get("/api/v1/results/{$otherResult->id}/download", $strangerHeaders);

        $response->assertStatus(404);
    }

    public function test_store_result_accepts_run_source_scheduled(): void
    {
        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/results", [
            'version'       => 1,
            'response_text' => 'Daily summary',
            'run_source'    => 'scheduled',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.run_source', 'scheduled');

        $this->assertDatabaseHas('results', [
            'response_hash' => hash('sha256', 'Daily summary'),
            'run_source'    => 'scheduled',
        ]);
    }

    public function test_store_result_rejects_invalid_run_source(): void
    {
        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/results", [
            'version'       => 1,
            'response_text' => 'X',
            'run_source'    => 'cron',
        ], $this->headers);

        $response->assertStatus(422)->assertJsonValidationErrors(['run_source']);
    }

    public function test_filter_results_by_run_source(): void
    {
        $version = $this->prompt->versions()->first();
        Result::create([
            'prompt_id'         => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source'            => 'api',
            'run_source'        => 'scheduled',
            'response_text'     => 'cron run',
            'created_by'        => $this->user->id,
        ]);
        Result::create([
            'prompt_id'         => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source'            => 'api',
            'response_text'     => 'one-off',
            'created_by'        => $this->user->id,
        ]);

        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/results?run_source=scheduled",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.run_source', 'scheduled');
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
