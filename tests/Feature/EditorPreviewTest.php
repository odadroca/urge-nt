<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditorPreviewTest extends TestCase
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

    public function test_toggle_preview_on_and_off(): void
    {
        $prompt = Prompt::create([
            'name' => 'Preview Toggle',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello world',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->assertSet('showPreview', false)
            ->call('togglePreview')
            ->assertSet('showPreview', true);

        $this->assertNotNull($component->get('previewResult'));

        $component->call('togglePreview')
            ->assertSet('showPreview', false)
            ->assertSet('previewResult', null);
    }

    public function test_preview_renders_content_with_variable_defaults(): void
    {
        $prompt = Prompt::create([
            'name' => 'Default Vars',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
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

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->call('togglePreview');

        $previewResult = $component->get('previewResult');
        $this->assertEquals('Hello Alice, welcome to Wonderland', $previewResult['rendered']);
        $this->assertContains('name', $previewResult['variables_used']);
        $this->assertContains('place', $previewResult['variables_used']);
        $this->assertEmpty($previewResult['variables_missing']);
    }

    public function test_preview_renders_with_user_filled_overrides(): void
    {
        $prompt = Prompt::create([
            'name' => 'Override Vars',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}}',
            'variables' => ['name'],
            'variable_metadata' => [
                'name' => ['type' => 'string', 'default' => 'Alice'],
            ],
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->call('togglePreview')
            ->set('previewVariables.name', 'Bob');

        $previewResult = $component->get('previewResult');
        $this->assertEquals('Hello Bob', $previewResult['rendered']);
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

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => '{{>system-context}} Now help me.',
            'variables' => [],
            'includes' => ['system-context'],
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->call('togglePreview');

        $previewResult = $component->get('previewResult');
        $this->assertEquals('You are a helpful assistant. Now help me.', $previewResult['rendered']);
        $this->assertContains('system-context', $previewResult['includes_resolved']);
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

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Start {{>circular-a}}',
            'variables' => [],
            'includes' => ['circular-a'],
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->call('togglePreview');

        $this->assertNull($component->get('previewResult'));
        $this->assertStringContainsString('Circular include', $component->get('previewError'));
    }

    public function test_preview_shows_missing_variables(): void
    {
        $prompt = Prompt::create([
            'name' => 'Missing Vars',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}} and {{role}}',
            'variables' => ['name', 'role'],
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->call('togglePreview');

        $previewResult = $component->get('previewResult');
        $this->assertContains('name', $previewResult['variables_missing']);
        $this->assertContains('role', $previewResult['variables_missing']);
        $this->assertStringContainsString('{{name}}', $previewResult['rendered']);
    }

    public function test_preview_updates_when_content_changes(): void
    {
        $prompt = Prompt::create([
            'name' => 'Content Change',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->call('togglePreview');

        $this->assertEquals('Hello', $component->get('previewResult.rendered'));

        $component->set('content', 'Goodbye');
        $this->assertEquals('Goodbye', $component->get('previewResult.rendered'));
    }

    public function test_preview_updates_when_metadata_default_changes(): void
    {
        $prompt = Prompt::create([
            'name' => 'Meta Default Change',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}}',
            'variables' => ['name'],
            'variable_metadata' => [
                'name' => ['type' => 'string', 'default' => 'Alice'],
            ],
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->call('togglePreview');

        $this->assertEquals('Hello Alice', $component->get('previewResult.rendered'));

        $component->call('setMetaField', 'name', 'default', 'Bob');
        $this->assertEquals('Hello Bob', $component->get('previewResult.rendered'));
    }

    public function test_preview_empty_content_shows_no_result(): void
    {
        $prompt = Prompt::create([
            'name' => 'Empty Content',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => '',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->call('togglePreview')
            ->assertSet('previewResult', null)
            ->assertSet('previewError', null);
    }

    public function test_preview_button_visible_in_toolbar(): void
    {
        $prompt = Prompt::create([
            'name' => 'Toolbar Test',
            'created_by' => $this->user->id,
        ]);

        $prompt->load('creator');
        $response = $this->actingAs($this->user)->get($prompt->workspaceUrl());
        $response->assertOk();
        $response->assertSee('Preview', false);
    }
}
