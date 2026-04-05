<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspacePhase3Test extends TestCase
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

    public function test_editor_has_mode_toggle(): void
    {
        $prompt = Prompt::create([
            'name' => 'Mode Test',
            'created_by' => $this->user->id,
        ]);

        $prompt->load('creator');
        $response = $this->actingAs($this->user)->get($prompt->workspaceUrl());
        $response->assertOk();
        $response->assertSee('Text', false);
        $response->assertSee('Visual', false);
    }

    public function test_editor_switch_mode(): void
    {
        $prompt = Prompt::create([
            'name' => 'Switch Mode Test',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->assertSet('editorMode', 'text')
            ->call('switchMode', 'visual')
            ->assertSet('editorMode', 'visual')
            ->call('switchMode', 'text')
            ->assertSet('editorMode', 'text');
    }

    public function test_editor_saves_variable_metadata(): void
    {
        $prompt = Prompt::create([
            'name' => 'Meta Save Test',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Initial',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->set('content', 'Hello {{name}}')
            ->call('setMetaField', 'name', 'type', 'string')
            ->call('setMetaField', 'name', 'default', 'World')
            ->call('setMetaField', 'name', 'description', 'User name')
            ->call('saveVersion')
            ->assertDispatched('version-created');

        $newVersion = PromptVersion::where('prompt_id', $prompt->id)
            ->where('version_number', 2)
            ->first();
        $this->assertNotNull($newVersion);
        $this->assertEquals('string', $newVersion->variable_metadata['name']['type']);
        $this->assertEquals('World', $newVersion->variable_metadata['name']['default']);
    }

    public function test_editor_loads_metadata_on_version_select(): void
    {
        $prompt = Prompt::create([
            'name' => 'Meta Load Test',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}}',
            'variables' => ['name'],
            'variable_metadata' => ['name' => ['type' => 'string', 'default' => 'World']],
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\Editor::class, ['prompt' => $prompt, 'currentVersion' => $version])
            ->assertSet('variableMetadata.name.type', 'string')
            ->assertSet('variableMetadata.name.default', 'World');
    }

    public function test_workspace_page_renders_all_panels(): void
    {
        $prompt = Prompt::create([
            'name' => 'Panel Test',
            'created_by' => $this->user->id,
        ]);

        $prompt->load('creator');
        $response = $this->actingAs($this->user)->get($prompt->workspaceUrl());
        $response->assertOk();
        $response->assertSee('Versions', false);
        $response->assertSee('Results', false);
        $response->assertSee('Metadata', false);
    }

    public function test_results_panel_with_multiple_results_shows_compare(): void
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

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\ResultsPanel::class, [
                'prompt' => $prompt,
                'currentVersion' => $version,
            ]);

        $component->assertSee('GPT-4')
            ->assertSee('Claude')
            ->assertSee('Compare Results', false);
    }

    public function test_version_sidebar_with_multiple_versions_has_diff(): void
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

        $v2 = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 2,
            'content' => 'Version 2',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Workspace\VersionSidebar::class, [
                'prompt' => $prompt,
                'currentVersion' => $v2,
            ]);

        $component->assertSee('v1')
            ->assertSee('v2')
            ->assertSee('Quick Diff', false);
    }
}
