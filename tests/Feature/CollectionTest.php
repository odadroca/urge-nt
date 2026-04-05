<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionTest extends TestCase
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

    public function test_create_collection(): void
    {
        $collection = Collection::create([
            'title' => 'My Collection',
            'description' => 'Test collection',
            'created_by' => $this->user->id,
        ]);

        $this->assertNotNull($collection->id);
        $this->assertEquals('my-collection', $collection->slug);
        $this->assertEquals('My Collection', $collection->title);
    }

    public function test_add_prompt_version_to_collection(): void
    {
        $collection = Collection::create([
            'title' => 'Version Collection',
            'created_by' => $this->user->id,
        ]);

        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $item = CollectionItem::create([
            'collection_id' => $collection->id,
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
            'sort_order' => 0,
        ]);

        $this->assertEquals(1, $collection->items()->count());
        $resolved = $item->item;
        $this->assertInstanceOf(PromptVersion::class, $resolved);
        $this->assertEquals($version->id, $resolved->id);
    }

    public function test_add_result_to_collection(): void
    {
        $collection = Collection::create([
            'title' => 'Result Collection',
            'created_by' => $this->user->id,
        ]);

        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $result = Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'manual',
            'response_text' => 'Response',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        CollectionItem::create([
            'collection_id' => $collection->id,
            'item_type' => 'result',
            'item_id' => $result->id,
            'sort_order' => 0,
        ]);

        $item = $collection->items()->first();
        $resolved = $item->item;
        $this->assertInstanceOf(Result::class, $resolved);
        $this->assertEquals($result->id, $resolved->id);
    }

    public function test_reorder_items(): void
    {
        $collection = Collection::create([
            'title' => 'Reorder Test',
            'created_by' => $this->user->id,
        ]);

        $prompt = Prompt::create([
            'name' => 'Prompt',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $item1 = CollectionItem::create([
            'collection_id' => $collection->id,
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
            'sort_order' => 0,
        ]);

        $result = Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'manual',
            'response_text' => 'Response',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        $item2 = CollectionItem::create([
            'collection_id' => $collection->id,
            'item_type' => 'result',
            'item_id' => $result->id,
            'sort_order' => 1,
        ]);

        // Reorder: swap
        $item1->update(['sort_order' => 1]);
        $item2->update(['sort_order' => 0]);

        $items = $collection->items()->orderBy('sort_order')->get();
        $this->assertEquals($item2->id, $items->first()->id);
        $this->assertEquals($item1->id, $items->last()->id);
    }

    public function test_remove_item_from_collection(): void
    {
        $collection = Collection::create([
            'title' => 'Remove Test',
            'created_by' => $this->user->id,
        ]);

        $prompt = Prompt::create([
            'name' => 'Prompt',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $item = CollectionItem::create([
            'collection_id' => $collection->id,
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
            'sort_order' => 0,
        ]);

        $this->assertEquals(1, $collection->items()->count());
        $item->delete();
        $this->assertEquals(0, $collection->items()->count());
    }

    public function test_soft_delete_collection(): void
    {
        $collection = Collection::create([
            'title' => 'Delete Test',
            'created_by' => $this->user->id,
        ]);

        $id = $collection->id;
        $collection->delete();

        $this->assertNull(Collection::find($id));
        $this->assertNotNull(Collection::withTrashed()->find($id));
    }

    public function test_duplicate_item_prevention(): void
    {
        $collection = Collection::create([
            'title' => 'Dupe Test',
            'created_by' => $this->user->id,
        ]);

        $prompt = Prompt::create([
            'name' => 'Prompt',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        CollectionItem::create([
            'collection_id' => $collection->id,
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
            'sort_order' => 0,
        ]);

        // firstOrCreate should not create duplicate
        CollectionItem::firstOrCreate([
            'collection_id' => $collection->id,
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
        ], ['sort_order' => 1]);

        $this->assertEquals(1, $collection->items()->count());
    }

    public function test_collection_slug_uniqueness(): void
    {
        $c1 = Collection::create([
            'title' => 'My Collection',
            'created_by' => $this->user->id,
        ]);

        $c2 = Collection::create([
            'title' => 'My Collection',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals('my-collection', $c1->slug);
        $this->assertEquals('my-collection-1', $c2->slug);
    }

    public function test_collection_list_livewire_component(): void
    {
        Collection::create([
            'title' => 'Test Collection',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse\CollectionList::class)
            ->assertSee('Test Collection');
    }
}
