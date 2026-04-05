<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\CollectionShareLink;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\CollectionNestingService;
use App\Services\ShareLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NestedCollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private CollectionNestingService $nestingService;
    private string $apiKey;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nestingService = app(CollectionNestingService::class);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $result = app(ApiKeyService::class)->generateKey($this->user, 'Test Key');
        $this->apiKey = $result['key'];
        $this->headers = ['Authorization' => "Bearer {$this->apiKey}"];
    }

    // --- Nesting Service ---

    public function test_can_nest_collection_inside_another(): void
    {
        $parent = Collection::create(['title' => 'Parent', 'created_by' => $this->user->id]);
        $child = Collection::create(['title' => 'Child', 'created_by' => $this->user->id]);

        // Should not throw
        $this->nestingService->validateNesting($parent->id, $child->id);
        $this->assertTrue(true);
    }

    public function test_cannot_nest_collection_inside_itself(): void
    {
        $collection = Collection::create(['title' => 'Self', 'created_by' => $this->user->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot nest a collection inside itself.');
        $this->nestingService->validateNesting($collection->id, $collection->id);
    }

    public function test_circular_reference_detected_two_levels(): void
    {
        $a = Collection::create(['title' => 'A', 'created_by' => $this->user->id]);
        $b = Collection::create(['title' => 'B', 'created_by' => $this->user->id]);

        // A contains B
        CollectionItem::create([
            'collection_id' => $a->id,
            'item_type' => 'collection',
            'item_id' => $b->id,
            'sort_order' => 0,
        ]);

        // B cannot contain A
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular reference detected.');
        $this->nestingService->validateNesting($b->id, $a->id);
    }

    public function test_circular_reference_detected_three_levels(): void
    {
        $a = Collection::create(['title' => 'A', 'created_by' => $this->user->id]);
        $b = Collection::create(['title' => 'B', 'created_by' => $this->user->id]);
        $c = Collection::create(['title' => 'C', 'created_by' => $this->user->id]);

        // A → B → C
        CollectionItem::create([
            'collection_id' => $a->id,
            'item_type' => 'collection',
            'item_id' => $b->id,
            'sort_order' => 0,
        ]);
        CollectionItem::create([
            'collection_id' => $b->id,
            'item_type' => 'collection',
            'item_id' => $c->id,
            'sort_order' => 0,
        ]);

        // C cannot contain A
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular reference detected.');
        $this->nestingService->validateNesting($c->id, $a->id);
    }

    public function test_depth_limit_enforced(): void
    {
        config(['urge.max_collection_depth' => 2, 'urge.unlimited_collection_depth' => false]);

        $a = Collection::create(['title' => 'A', 'created_by' => $this->user->id]);
        $b = Collection::create(['title' => 'B', 'created_by' => $this->user->id]);
        $c = Collection::create(['title' => 'C', 'created_by' => $this->user->id]);
        $d = Collection::create(['title' => 'D', 'created_by' => $this->user->id]);

        // A → B → C (totalDepth = 2, at the limit)
        CollectionItem::create([
            'collection_id' => $a->id,
            'item_type' => 'collection',
            'item_id' => $b->id,
            'sort_order' => 0,
        ]);
        CollectionItem::create([
            'collection_id' => $b->id,
            'item_type' => 'collection',
            'item_id' => $c->id,
            'sort_order' => 0,
        ]);

        // C → D would make totalDepth 3, exceeding limit of 2
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Maximum nesting depth exceeded.');
        $this->nestingService->validateNesting($c->id, $d->id);
    }

    public function test_depth_limit_ignored_when_unlimited(): void
    {
        config(['urge.max_collection_depth' => 2, 'urge.unlimited_collection_depth' => true]);

        $a = Collection::create(['title' => 'A', 'created_by' => $this->user->id]);
        $b = Collection::create(['title' => 'B', 'created_by' => $this->user->id]);
        $c = Collection::create(['title' => 'C', 'created_by' => $this->user->id]);

        // A → B
        CollectionItem::create([
            'collection_id' => $a->id,
            'item_type' => 'collection',
            'item_id' => $b->id,
            'sort_order' => 0,
        ]);

        // B → C — allowed despite depth > 2 because unlimited is true
        $this->nestingService->validateNesting($b->id, $c->id);
        $this->assertTrue(true);
    }

    public function test_same_collection_in_multiple_parents(): void
    {
        $parent1 = Collection::create(['title' => 'Parent 1', 'created_by' => $this->user->id]);
        $parent2 = Collection::create(['title' => 'Parent 2', 'created_by' => $this->user->id]);
        $child = Collection::create(['title' => 'Shared Child', 'created_by' => $this->user->id]);

        CollectionItem::create([
            'collection_id' => $parent1->id,
            'item_type' => 'collection',
            'item_id' => $child->id,
            'sort_order' => 0,
        ]);

        // Should still be valid to add to parent2 (DAG allows multiple parents)
        $this->nestingService->validateNesting($parent2->id, $child->id);

        CollectionItem::create([
            'collection_id' => $parent2->id,
            'item_type' => 'collection',
            'item_id' => $child->id,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseCount('collection_items', 2);
    }

    public function test_ancestor_ids_returned_correctly(): void
    {
        $a = Collection::create(['title' => 'A', 'created_by' => $this->user->id]);
        $b = Collection::create(['title' => 'B', 'created_by' => $this->user->id]);
        $c = Collection::create(['title' => 'C', 'created_by' => $this->user->id]);

        // A → B → C
        CollectionItem::create([
            'collection_id' => $a->id,
            'item_type' => 'collection',
            'item_id' => $b->id,
            'sort_order' => 0,
        ]);
        CollectionItem::create([
            'collection_id' => $b->id,
            'item_type' => 'collection',
            'item_id' => $c->id,
            'sort_order' => 0,
        ]);

        $ancestors = $this->nestingService->getAncestorIds($c->id);
        $this->assertContains($b->id, $ancestors);
        $this->assertContains($a->id, $ancestors);
        $this->assertCount(2, $ancestors);
    }

    public function test_max_child_depth(): void
    {
        $a = Collection::create(['title' => 'A', 'created_by' => $this->user->id]);
        $b = Collection::create(['title' => 'B', 'created_by' => $this->user->id]);
        $c = Collection::create(['title' => 'C', 'created_by' => $this->user->id]);

        // A → B → C
        CollectionItem::create([
            'collection_id' => $a->id,
            'item_type' => 'collection',
            'item_id' => $b->id,
            'sort_order' => 0,
        ]);
        CollectionItem::create([
            'collection_id' => $b->id,
            'item_type' => 'collection',
            'item_id' => $c->id,
            'sort_order' => 0,
        ]);

        $this->assertEquals(2, $this->nestingService->getMaxChildDepth($a->id));
        $this->assertEquals(1, $this->nestingService->getMaxChildDepth($b->id));
        $this->assertEquals(0, $this->nestingService->getMaxChildDepth($c->id));
    }

    // --- API ---

    public function test_api_add_collection_item(): void
    {
        $parent = Collection::create(['title' => 'Parent', 'created_by' => $this->user->id]);
        $child = Collection::create(['title' => 'Child', 'created_by' => $this->user->id]);

        $response = $this->postJson("/api/v1/collections/{$parent->slug}/items", [
            'item_type' => 'collection',
            'item_id' => $child->id,
        ], $this->headers);

        $response->assertStatus(201);
        $this->assertDatabaseHas('collection_items', [
            'collection_id' => $parent->id,
            'item_type' => 'collection',
            'item_id' => $child->id,
        ]);
    }

    public function test_api_rejects_circular_reference(): void
    {
        $a = Collection::create(['title' => 'A', 'created_by' => $this->user->id]);
        $b = Collection::create(['title' => 'B', 'created_by' => $this->user->id]);

        // A contains B
        CollectionItem::create([
            'collection_id' => $a->id,
            'item_type' => 'collection',
            'item_id' => $b->id,
            'sort_order' => 0,
        ]);

        // Try to add A into B → circular
        $response = $this->postJson("/api/v1/collections/{$b->slug}/items", [
            'item_type' => 'collection',
            'item_id' => $a->id,
        ], $this->headers);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Circular reference detected.');
    }

    public function test_api_rejects_self_reference(): void
    {
        $collection = Collection::create(['title' => 'Self', 'created_by' => $this->user->id]);

        $response = $this->postJson("/api/v1/collections/{$collection->slug}/items", [
            'item_type' => 'collection',
            'item_id' => $collection->id,
        ], $this->headers);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Cannot nest a collection inside itself.');
    }

    public function test_api_show_returns_nested_collections(): void
    {
        $parent = Collection::create(['title' => 'Parent', 'created_by' => $this->user->id]);
        $child = Collection::create(['title' => 'Child', 'created_by' => $this->user->id]);

        CollectionItem::create([
            'collection_id' => $parent->id,
            'item_type' => 'collection',
            'item_id' => $child->id,
            'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/v1/collections/{$parent->slug}", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.item_type', 'collection')
            ->assertJsonPath('data.items.0.title', 'Child');
    }

    public function test_api_show_recursive_depth(): void
    {
        $a = Collection::create(['title' => 'A', 'created_by' => $this->user->id]);
        $b = Collection::create(['title' => 'B', 'created_by' => $this->user->id]);
        $c = Collection::create(['title' => 'C', 'created_by' => $this->user->id]);

        // A → B → C
        CollectionItem::create([
            'collection_id' => $a->id,
            'item_type' => 'collection',
            'item_id' => $b->id,
            'sort_order' => 0,
        ]);
        CollectionItem::create([
            'collection_id' => $b->id,
            'item_type' => 'collection',
            'item_id' => $c->id,
            'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/v1/collections/{$a->slug}", $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.item_type', 'collection')
            ->assertJsonPath('data.items.0.title', 'B')
            ->assertJsonPath('data.items.0.children.0.item_type', 'collection')
            ->assertJsonPath('data.items.0.children.0.title', 'C');
    }

    // --- Livewire ---

    public function test_livewire_add_collection_to_collection(): void
    {
        $parent = Collection::create(['title' => 'Parent', 'created_by' => $this->user->id]);
        $child = Collection::create(['title' => 'Child', 'created_by' => $this->user->id]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse\CollectionList::class)
            ->call('addCollectionToCollection', $child->id, $parent->id);

        $this->assertDatabaseHas('collection_items', [
            'collection_id' => $parent->id,
            'item_type' => 'collection',
            'item_id' => $child->id,
        ]);
    }

    public function test_livewire_rejects_circular_nesting(): void
    {
        $a = Collection::create(['title' => 'A', 'created_by' => $this->user->id]);
        $b = Collection::create(['title' => 'B', 'created_by' => $this->user->id]);

        CollectionItem::create([
            'collection_id' => $a->id,
            'item_type' => 'collection',
            'item_id' => $b->id,
            'sort_order' => 0,
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Browse\CollectionList::class)
            ->call('addCollectionToCollection', $a->id, $b->id)
            ->assertDispatched('notify', fn ($name, $params) => $params['type'] === 'error');
    }

    // --- Share / Story View ---

    public function test_share_view_renders_nested_collections(): void
    {
        $parent = Collection::create(['title' => 'Story', 'created_by' => $this->user->id]);
        $child = Collection::create(['title' => 'Chapter 1', 'description' => 'First chapter', 'created_by' => $this->user->id]);

        CollectionItem::create([
            'collection_id' => $parent->id,
            'item_type' => 'collection',
            'item_id' => $child->id,
            'sort_order' => 0,
        ]);

        $shareService = app(ShareLinkService::class);
        $link = $shareService->createLink($parent, $this->user);

        $response = $this->get("/share/{$link->token}");

        $response->assertStatus(200)
            ->assertSee('Story')
            ->assertSee('Chapter 1')
            ->assertSee('Collection');
    }

    // --- Model ---

    public function test_collection_parent_items(): void
    {
        $parent = Collection::create(['title' => 'Parent', 'created_by' => $this->user->id]);
        $child = Collection::create(['title' => 'Child', 'created_by' => $this->user->id]);

        CollectionItem::create([
            'collection_id' => $parent->id,
            'item_type' => 'collection',
            'item_id' => $child->id,
            'sort_order' => 0,
        ]);

        $parentItems = $child->parentItems()->get();
        $this->assertCount(1, $parentItems);
        $this->assertEquals($parent->id, $parentItems->first()->collection_id);
    }

    public function test_collection_morph_map_includes_collection(): void
    {
        $map = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        $this->assertArrayHasKey('collection', $map);
        $this->assertEquals(Collection::class, $map['collection']);
    }
}
