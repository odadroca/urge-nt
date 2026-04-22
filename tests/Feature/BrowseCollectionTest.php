<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_add_prompt_version_to_collection(): void
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

        $response = $this->actingAs($this->user)->postJson('/api/v1/collections/' . $collection->slug . '/items', [
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('collection_items', [
            'collection_id' => $collection->id,
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
        ]);
    }

    public function test_add_nonexistent_item_returns_error(): void
    {
        $collection = Collection::create([
            'title' => 'My Collection',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/collections/' . $collection->slug . '/items', [
            'item_type' => 'prompt_version',
            'item_id' => 99999,
        ]);

        $response->assertStatus(404);

        $this->assertDatabaseMissing('collection_items', [
            'collection_id' => $collection->id,
        ]);
    }

    public function test_bulk_add_prompt_versions_to_collection(): void
    {
        $versions = [];
        for ($i = 1; $i <= 3; $i++) {
            $prompt = Prompt::create([
                'name' => "Prompt {$i}",
                'type' => 'prompt',
                'created_by' => $this->user->id,
            ]);
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

        // Add each version individually via API
        foreach ($versions as $version) {
            $response = $this->actingAs($this->user)->postJson('/api/v1/collections/' . $collection->slug . '/items', [
                'item_type' => 'prompt_version',
                'item_id' => $version->id,
            ]);
            $response->assertStatus(201);
        }

        $this->assertEquals(3, CollectionItem::where('collection_id', $collection->id)->count());
    }

    public function test_add_skips_nonexistent_versions(): void
    {
        $promptWithVersion = Prompt::create([
            'name' => 'Has Version',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        $version = PromptVersion::create([
            'prompt_id' => $promptWithVersion->id,
            'version_number' => 1,
            'content' => 'Content',
            'created_by' => $this->user->id,
        ]);

        $collection = Collection::create([
            'title' => 'Test Collection',
            'created_by' => $this->user->id,
        ]);

        // Add real version — succeeds
        $response = $this->actingAs($this->user)->postJson('/api/v1/collections/' . $collection->slug . '/items', [
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
        ]);
        $response->assertStatus(201);

        // Add nonexistent version — fails
        $response = $this->actingAs($this->user)->postJson('/api/v1/collections/' . $collection->slug . '/items', [
            'item_type' => 'prompt_version',
            'item_id' => 99999,
        ]);
        $response->assertStatus(404);

        $this->assertEquals(1, CollectionItem::where('collection_id', $collection->id)->count());
    }

    public function test_duplicate_add_returns_conflict(): void
    {
        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Content',
            'created_by' => $this->user->id,
        ]);

        $collection = Collection::create([
            'title' => 'My Collection',
            'created_by' => $this->user->id,
        ]);

        // First add — succeeds
        $response = $this->actingAs($this->user)->postJson('/api/v1/collections/' . $collection->slug . '/items', [
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
        ]);
        $response->assertStatus(201);

        // Second add — duplicate
        $response = $this->actingAs($this->user)->postJson('/api/v1/collections/' . $collection->slug . '/items', [
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
        ]);
        $response->assertStatus(409);

        $this->assertEquals(1, CollectionItem::where('collection_id', $collection->id)->count());
    }
}
