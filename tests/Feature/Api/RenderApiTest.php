<?php

namespace Tests\Feature\Api;

use App\Models\Prompt;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderApiTest extends TestCase
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

        $this->prompt = Prompt::create(['name' => 'Render Test', 'type' => 'prompt', 'created_by' => $this->user->id]);
        app(VersioningService::class)->createVersion($this->prompt, [
            'content' => 'Hello {{name}}, your role is {{role}}.',
        ], $this->user);
    }

    public function test_render_with_variables(): void
    {
        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/render", [
            'variables' => ['name' => 'Claude', 'role' => 'assistant'],
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'Hello Claude, your role is assistant.')
            ->assertJsonPath('data.variables_used', ['name', 'role'])
            ->assertJsonPath('data.variables_missing', []);
    }

    public function test_render_with_missing_variables(): void
    {
        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/render", [
            'variables' => ['name' => 'Claude'],
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'Hello Claude, your role is {{role}}.')
            ->assertJsonPath('data.variables_missing', ['role']);
    }

    public function test_render_specific_version(): void
    {
        app(VersioningService::class)->createVersion($this->prompt, [
            'content' => 'V2: Hey {{name}}!',
        ], $this->user);

        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/render", [
            'version' => 1,
            'variables' => ['name' => 'Test'],
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.rendered', 'Hello Test, your role is {{role}}.');
    }

    public function test_render_prompt_without_versions_returns_404(): void
    {
        $empty = Prompt::create(['name' => 'Empty', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $response = $this->postJson("/api/v1/prompts/{$this->user->slug}/{$empty->slug}/render", [], $this->headers);

        $response->assertStatus(404);
    }
}
