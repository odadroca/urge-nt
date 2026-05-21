<?php

namespace Tests\Feature\Api;

use App\Models\Prompt;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionApiTest extends TestCase
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

        $this->prompt = Prompt::create(['name' => 'Test Prompt', 'type' => 'prompt', 'created_by' => $this->user->id]);
    }

    public function test_list_versions(): void
    {
        $vs = app(VersioningService::class);
        $vs->createVersion($this->prompt, ['content' => 'v1 content'], $this->user);
        $vs->createVersion($this->prompt, ['content' => 'v2 content'], $this->user);

        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/versions", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_create_version(): void
    {
        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/versions", [
            'content' => 'Hello {{name}}, you are a {{role}}.',
            'commit_message' => 'Initial version',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.variables', ['name', 'role']);
    }

    public function test_create_version_requires_content(): void
    {
        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/versions", [
            'commit_message' => 'No content',
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_show_version(): void
    {
        app(VersioningService::class)->createVersion($this->prompt, ['content' => 'Version 1'], $this->user);

        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/versions/1", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.content', 'Version 1');
    }

    public function test_show_nonexistent_version_returns_404(): void
    {
        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/versions/999", $this->headers);

        $response->assertStatus(404);
    }

    public function test_download_version_as_markdown(): void
    {
        app(VersioningService::class)->createVersion(
            $this->prompt,
            ['content' => 'Hello {{name}}', 'commit_message' => 'first'],
            $this->user
        );

        $response = $this->get(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/versions/1/download",
            $this->headers
        );

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $response->assertHeader(
            'Content-Disposition',
            "attachment; filename={$this->prompt->slug}-v1.md"
        );

        $body = $response->streamedContent();
        $this->assertStringStartsWith("---\n", $body);
        $this->assertStringContainsString('prompt: '.$this->prompt->slug, $body);
        $this->assertStringContainsString('owner: '.$this->user->slug, $body);
        $this->assertStringContainsString('version: 1', $body);
        $this->assertStringContainsString('Hello {{name}}', $body);
    }

    public function test_download_nonexistent_version_returns_404(): void
    {
        $response = $this->get(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/versions/999/download",
            $this->headers
        );

        $response->assertStatus(404);
    }

    public function test_download_version_of_other_users_private_prompt_returns_404(): void
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
        app(VersioningService::class)->createVersion(
            $ownerPrompt,
            ['content' => 'secret'],
            $owner
        );

        $response = $this->get(
            "/api/v1/prompts/{$owner->slug}/{$ownerPrompt->slug}/versions/1/download",
            $strangerHeaders
        );

        $response->assertStatus(404);
    }
}
