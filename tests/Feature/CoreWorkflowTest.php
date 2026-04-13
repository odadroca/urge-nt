<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\Result;
use App\Models\User;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_first_user_becomes_admin(): void
    {
        $this->assertEquals('admin', $this->user->role);
        $this->assertTrue($this->user->isAdmin());
    }

    public function test_second_user_is_viewer(): void
    {
        $viewer = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => bcrypt('password'),
        ]);
        $viewer->refresh();
        $this->assertEquals('viewer', $viewer->role);
    }

    public function test_prompt_auto_generates_slug(): void
    {
        $prompt = Prompt::create([
            'name' => 'My Cool Prompt',
            'created_by' => $this->user->id,
        ]);
        $this->assertEquals('my-cool-prompt', $prompt->slug);
    }

    public function test_prompt_slug_collision_handling(): void
    {
        Prompt::create(['name' => 'Test', 'created_by' => $this->user->id]);
        $second = Prompt::create(['name' => 'Test', 'created_by' => $this->user->id]);
        $this->assertEquals('test-1', $second->slug);
    }

    public function test_prompt_fragment_type(): void
    {
        $fragment = Prompt::create([
            'name' => 'Header',
            'type' => 'fragment',
            'created_by' => $this->user->id,
        ]);
        $this->assertTrue($fragment->isFragment());
    }

    public function test_full_workflow_create_version_and_result(): void
    {
        $prompt = Prompt::create([
            'name' => 'My Prompt',
            'created_by' => $this->user->id,
        ]);

        $vs = app(VersioningService::class);
        $version = $vs->createVersion($prompt, [
            'content' => 'Tell me about {{topic}}.',
        ], $this->user);

        $this->assertEquals(1, $version->version_number);
        $this->assertEquals(['topic'], $version->variables);

        // Active version should be the latest
        $prompt->refresh();
        $this->assertEquals($version->id, $prompt->active_version->id);

        // Create a manual result
        $result = Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'manual',
            'provider_name' => 'ChatGPT',
            'model_name' => 'gpt-4o',
            'response_text' => 'Here is some information about the topic.',
            'rating' => 4,
            'starred' => true,
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($result->starred);
        $this->assertEquals(4, $result->rating);
        $this->assertEquals('manual', $result->source);

        // Verify relationships
        $this->assertEquals($prompt->id, $result->prompt->id);
        $this->assertEquals($version->id, $result->promptVersion->id);
    }

    public function test_category_model(): void
    {
        $cat = Category::create(['name' => 'Code Generation']);
        $this->assertEquals('code-generation', $cat->slug);

        $prompt = Prompt::create([
            'name' => 'Test',
            'category_id' => $cat->id,
            'created_by' => $this->user->id,
        ]);
        $this->assertEquals($cat->id, $prompt->category->id);
    }

    public function test_dashboard_redirects_to_browse(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertRedirect('/app/browse');
    }

    public function test_browse_requires_auth(): void
    {
        $response = $this->get('/browse');
        $response->assertRedirect('/login');
    }

    public function test_browse_accessible_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)->get('/browse');
        $response->assertOk();
    }

    public function test_workspace_accessible_when_authenticated(): void
    {
        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'created_by' => $this->user->id,
        ]);

        $prompt->load('creator');
        $response = $this->actingAs($this->user)->get($prompt->workspaceUrl());
        $response->assertOk();
    }
}
