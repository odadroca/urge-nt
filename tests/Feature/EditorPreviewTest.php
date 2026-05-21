<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorPreviewTest extends TestCase
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

    public function test_render_returns_content(): void
    {
        $prompt = Prompt::create([
            'name' => 'Preview Toggle',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello world',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            [],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'Hello world');
    }

    public function test_preview_renders_content_with_variable_defaults(): void
    {
        $prompt = Prompt::create([
            'name' => 'Default Vars',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}}, welcome to {{place}}',
            'variables' => ['name', 'place'],
            'variable_metadata' => [
                'name' => ['type' => 'string', 'default' => 'Alice'],
                'place' => ['type' => 'string', 'default' => 'Wonderland'],
            ],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            [],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'Hello Alice, welcome to Wonderland');

        $data = $response->json('data');
        $this->assertContains('name', $data['variables_used']);
        $this->assertContains('place', $data['variables_used']);
        $this->assertEmpty($data['variables_missing']);
    }

    public function test_preview_renders_with_user_filled_overrides(): void
    {
        $prompt = Prompt::create([
            'name' => 'Override Vars',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}}',
            'variables' => ['name'],
            'variable_metadata' => [
                'name' => ['type' => 'string', 'default' => 'Alice'],
            ],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            ['variables' => ['name' => 'Bob']],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'Hello Bob');
    }

    public function test_preview_resolves_includes(): void
    {
        $fragment = Prompt::create([
            'name' => 'System Context',
            'type' => 'fragment',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $fragment->id,
            'version_number' => 1,
            'content' => 'You are a helpful assistant.',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $prompt = Prompt::create([
            'name' => 'Include Test',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => '{{>system-context}} Now help me.',
            'variables' => [],
            'includes' => ['system-context'],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            [],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'You are a helpful assistant. Now help me.');

        $data = $response->json('data');
        $this->assertContains('system-context', $data['includes_resolved']);
    }

    public function test_preview_handles_circular_includes(): void
    {
        $promptA = Prompt::create([
            'name' => 'Circular A',
            'type' => 'fragment',
            'created_by' => $this->user->id,
        ]);

        $promptB = Prompt::create([
            'name' => 'Circular B',
            'type' => 'fragment',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $promptA->id,
            'version_number' => 1,
            'content' => 'A includes {{>circular-b}}',
            'variables' => [],
            'includes' => ['circular-b'],
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $promptB->id,
            'version_number' => 1,
            'content' => 'B includes {{>circular-a}}',
            'variables' => [],
            'includes' => ['circular-a'],
            'created_by' => $this->user->id,
        ]);

        $prompt = Prompt::create([
            'name' => 'Circular Test',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Start {{>circular-a}}',
            'variables' => [],
            'includes' => ['circular-a'],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            [],
            $this->headers
        );

        // Circular includes throw RuntimeException; PB-3 catches that in
        // RenderController and surfaces as 422 (input was bad, not a server bug).
        $response->assertStatus(422);
    }

    public function test_preview_shows_missing_variables(): void
    {
        $prompt = Prompt::create([
            'name' => 'Missing Vars',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}} and {{role}}',
            'variables' => ['name', 'role'],
            'created_by' => $this->user->id,
        ]);

        // Strict mode rejects missing variables
        $strictResponse = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            [],
            $this->headers
        );
        $strictResponse->assertStatus(422);

        // Lenient mode shows missing variables
        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            ['strict' => false],
            $this->headers
        );
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertContains('name', $data['variables_missing']);
        $this->assertContains('role', $data['variables_missing']);
        $this->assertStringContainsString('{{name}}', $data['rendered']);
    }

    public function test_render_returns_updated_content_for_new_version(): void
    {
        $prompt = Prompt::create([
            'name' => 'Content Change',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            ['version' => 1],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'Hello');

        // Create a new version with different content
        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 2,
            'content' => 'Goodbye',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            ['version' => 2],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'Goodbye');
    }

    public function test_render_uses_metadata_defaults(): void
    {
        $prompt = Prompt::create([
            'name' => 'Meta Default Change',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}}',
            'variables' => ['name'],
            'variable_metadata' => [
                'name' => ['type' => 'string', 'default' => 'Alice'],
            ],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            [],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'Hello Alice');

        // Override the default with a variable
        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            ['variables' => ['name' => 'Bob']],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', 'Hello Bob');
    }

    public function test_render_empty_content_returns_empty(): void
    {
        $prompt = Prompt::create([
            'name' => 'Empty Content',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => '',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/render",
            [],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.rendered', '');
    }

    public function test_prompt_show_includes_active_version(): void
    {
        $prompt = Prompt::create([
            'name' => 'Toolbar Test',
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
}
