<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseCollectionTest extends TestCase
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

    public function test_add_prompt_to_collection_adds_active_version(): void
    {
        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello world',
            'created_by' => $this->user->id,
        ]);

        $collection = Collection::create([
            'title' => 'My Collection',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class)
            ->call('addPromptToCollection', $prompt->id, $collection->id);

        $this->assertDatabaseHas('collection_items', [
            'collection_id' => $collection->id,
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
        ]);
    }

    public function test_add_prompt_without_versions_dispatches_error(): void
    {
        $prompt = Prompt::create([
            'name' => 'Empty Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $collection = Collection::create([
            'title' => 'My Collection',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class)
            ->call('addPromptToCollection', $prompt->id, $collection->id)
            ->assertDispatched('notify', message: 'Prompt has no versions yet', type: 'error');

        $this->assertDatabaseMissing('collection_items', [
            'collection_id' => $collection->id,
        ]);
    }

    public function test_bulk_add_prompts_to_collection(): void
    {
        $prompts = [];
        $versions = [];
        for ($i = 1; $i <= 3; $i++) {
            $prompt = Prompt::create([
                'name' => "Prompt {$i}",
                'type' => 'prompt',
                'created_by' => $this->user->id,
            ]);
            $prompts[] = $prompt;
            $versions[] = PromptVersion::create([
                'prompt_id' => $prompt->id,
                'version_number' => 1,
                'content' => "Content {$i}",
                'created_by' => $this->user->id,
            ]);
        }

        $collection = Collection::create([
            'title' => 'Bulk Collection',
            'created_by' => $this->user->id,
        ]);

        $promptIds = array_map(fn ($p) => $p->id, $prompts);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class)
            ->call('addPromptsToCollection', $promptIds, $collection->id)
            ->assertDispatched('notify', message: 'Added 3 item(s) to collection', type: 'success')
            ->assertDispatched('selection-cleared');

        $this->assertEquals(3, CollectionItem::where('collection_id', $collection->id)->count());
    }

    public function test_bulk_add_skips_prompts_without_versions(): void
    {
        $promptWithVersion = Prompt::create([
            'name' => 'Has Version',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        PromptVersion::create([
            'prompt_id' => $promptWithVersion->id,
            'version_number' => 1,
            'content' => 'Content',
            'created_by' => $this->user->id,
        ]);

        $promptWithoutVersion = Prompt::create([
            'name' => 'No Version',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $collection = Collection::create([
            'title' => 'Test Collection',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class)
            ->call('addPromptsToCollection', [$promptWithVersion->id, $promptWithoutVersion->id], $collection->id)
            ->assertDispatched('notify', message: 'Added 1 item(s) to collection', type: 'success');

        $this->assertEquals(1, CollectionItem::where('collection_id', $collection->id)->count());
    }

    public function test_duplicate_add_does_not_create_duplicate_items(): void
    {
        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Content',
            'created_by' => $this->user->id,
        ]);

        $collection = Collection::create([
            'title' => 'My Collection',
            'created_by' => $this->user->id,
        ]);

        $browse = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse::class);

        $browse->call('addPromptToCollection', $prompt->id, $collection->id);
        $browse->call('addPromptToCollection', $prompt->id, $collection->id);

        $this->assertEquals(1, CollectionItem::where('collection_id', $collection->id)->count());
    }
}
