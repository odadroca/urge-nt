<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseEnhancedTest extends TestCase
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

    public function test_browse_shows_all_tabs(): void
    {
        $response = $this->actingAs($this->user)->get('/browse');
        $response->assertOk();
        $response->assertSee('Prompts', false);
        $response->assertSee('Fragments', false);
        $response->assertSee('Collections', false);
        $response->assertSee('Starred', false);
    }

    public function test_category_filter(): void
    {
        $cat = Category::create(['name' => 'Marketing']);

        Prompt::create([
            'name' => 'Marketing Prompt',
            'category_id' => $cat->id,
            'created_by' => $this->user->id,
        ]);

        Prompt::create([
            'name' => 'Other Prompt',
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class);

        // Without filter, both visible
        $component->assertSee('Marketing Prompt')
            ->assertSee('Other Prompt');

        // With category filter
        $component->set('categoryFilter', $cat->id)
            ->assertSee('Marketing Prompt')
            ->assertDontSee('Other Prompt');
    }

    public function test_tag_filter(): void
    {
        Prompt::create([
            'name' => 'Tagged Prompt',
            'tags' => ['ai', 'testing'],
            'created_by' => $this->user->id,
        ]);

        Prompt::create([
            'name' => 'Untagged Prompt',
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class)
            ->set('tagFilter', 'ai')
            ->assertSee('Tagged Prompt')
            ->assertDontSee('Untagged Prompt');
    }

    public function test_starred_tab_shows_starred_results(): void
    {
        $prompt = Prompt::create([
            'name' => 'Star Test',
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
            'provider_name' => 'StarProvider',
            'response_text' => 'Starred response',
            'starred' => true,
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class)
            ->set('tab', 'starred')
            ->assertSee('StarProvider')
            ->assertSee('Starred response', false);
    }

    public function test_collections_tab_renders(): void
    {
        Collection::create([
            'title' => 'Browse Collection',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class)
            ->set('tab', 'collections')
            ->assertSeeLivewire(\App\Livewire\Browse\CollectionList::class);
    }

    public function test_result_count_on_prompt_cards(): void
    {
        $prompt = Prompt::create([
            'name' => 'Count Test',
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
            'response_text' => 'R1',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'manual',
            'response_text' => 'R2',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class)
            ->assertSee('2 results');
    }
}
