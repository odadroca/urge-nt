<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_variables_endpoint_requires_auth(): void
    {
        $this->getJson('/internal/variables')
            ->assertUnauthorized();
    }

    public function test_variables_endpoint_returns_unique_variable_names(): void
    {
        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}}, welcome to {{city}}',
            'variables' => ['name', 'city'],
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 2,
            'content' => 'Hi {{name}}, your role is {{role}}',
            'variables' => ['name', 'role'],
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/internal/variables');

        $response->assertOk();
        $data = $response->json();

        $this->assertContains('name', $data);
        $this->assertContains('city', $data);
        $this->assertContains('role', $data);
        // name should only appear once
        $this->assertCount(1, array_keys(array_filter($data, fn($v) => $v === 'name')));
    }

    public function test_variables_endpoint_returns_empty_when_no_versions(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/internal/variables');

        $response->assertOk()
            ->assertJson([]);
    }

    public function test_fragments_endpoint_requires_auth(): void
    {
        $this->getJson('/internal/fragments')
            ->assertUnauthorized();
    }

    public function test_fragments_endpoint_returns_prompts_with_versions(): void
    {
        $prompt1 = Prompt::create([
            'name' => 'Greeting Fragment',
            'type' => 'fragment',
            'created_by' => $this->user->id,
        ]);
        PromptVersion::create([
            'prompt_id' => $prompt1->id,
            'version_number' => 1,
            'content' => 'Hello!',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        // Prompt without versions should not appear
        Prompt::create([
            'name' => 'Empty Prompt',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/internal/fragments');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data);
        $this->assertEquals('greeting-fragment', $data[0]['slug']);
        $this->assertEquals('Greeting Fragment', $data[0]['name']);
    }

    public function test_fragments_excludes_soft_deleted_prompts(): void
    {
        $prompt = Prompt::create([
            'name' => 'Deleted Fragment',
            'created_by' => $this->user->id,
        ]);
        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Gone',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);
        $prompt->delete();

        $response = $this->actingAs($this->user)
            ->getJson('/internal/fragments');

        $response->assertOk()
            ->assertJson([]);
    }
}
