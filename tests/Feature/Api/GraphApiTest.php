<?php

namespace Tests\Feature\Api;

use App\Models\Collection;
use App\Models\GraphPosition;
use App\Models\Prompt;
use App\Models\PromptBranch;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $apiKey;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a dummy admin first so our test user is NOT auto-admin
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'slug' => 'admin',
            'role' => 'admin',
        ]);

        $this->user = User::create([
            'name' => 'Graph User',
            'email' => 'graph@example.com',
            'password' => bcrypt('password'),
            'slug' => 'graph-user',
        ]);
        $result = app(ApiKeyService::class)->generateKey($this->user, 'Graph Key');
        $this->apiKey = $result['key'];
        $this->headers = ['Authorization' => "Bearer {$this->apiKey}"];
    }

    public function test_get_nodes_returns_prompts_and_fragments(): void
    {
        Prompt::create([
            'name' => 'System Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        Prompt::create([
            'name' => 'Tone Guide',
            'type' => 'fragment',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.prompts');

        $names = collect($response->json('data.prompts'))->pluck('name')->all();
        $this->assertContains('System Prompt', $names);
        $this->assertContains('Tone Guide', $names);

        // Verify type mapping
        $types = collect($response->json('data.prompts'))->pluck('type', 'name')->all();
        $this->assertEquals('prompt', $types['System Prompt']);
        $this->assertEquals('fragment', $types['Tone Guide']);
    }

    public function test_get_nodes_includes_positions_for_current_user(): void
    {
        $prompt = Prompt::create([
            'name' => 'Positioned Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        GraphPosition::create([
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => $prompt->id,
            'x' => 150.0,
            'y' => 250.0,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200);

        $promptData = collect($response->json('data.prompts'))
            ->firstWhere('id', $prompt->id);

        $this->assertNotNull($promptData);
        $this->assertEquals(150.0, $promptData['position']['x']);
        $this->assertEquals(250.0, $promptData['position']['y']);
    }

    public function test_get_nodes_returns_null_position_when_not_set(): void
    {
        Prompt::create([
            'name' => 'No Position Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200);

        $promptData = $response->json('data.prompts.0');
        $this->assertNull($promptData['position']);
    }

    public function test_get_nodes_includes_collections(): void
    {
        Collection::create([
            'title' => 'Code Review Kit',
            'description' => 'A kit for code reviews',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.collections')
            ->assertJsonPath('data.collections.0.title', 'Code Review Kit');
    }

    public function test_get_nodes_respects_visibility(): void
    {
        // Create another user with a private prompt
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'slug' => 'other-user',
        ]);

        Prompt::create([
            'name' => 'My Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        Prompt::create([
            'name' => 'Private Prompt',
            'type' => 'prompt',
            'visibility' => 'private',
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.prompts')
            ->assertJsonPath('data.prompts.0.name', 'My Prompt');
    }

    public function test_get_nodes_includes_truncation_metadata(): void
    {
        Prompt::create([
            'name' => 'Test Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['prompts', 'collections'],
                'meta' => ['total_count', 'truncated'],
            ])
            ->assertJsonPath('meta.total_count', 1)
            ->assertJsonPath('meta.truncated', false);
    }

    public function test_save_positions(): void
    {
        Prompt::create([
            'name' => 'P1',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/graph/positions', [
            'positions' => [
                ['node_type' => 'prompt', 'node_id' => 1, 'x' => 100.5, 'y' => 200.5],
                ['node_type' => 'fragment', 'node_id' => 2, 'x' => 300.0, 'y' => 400.0],
            ],
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.saved', 2);

        $this->assertDatabaseHas('graph_positions', [
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 100.5,
        ]);
    }

    public function test_save_positions_validates_input(): void
    {
        $response = $this->postJson('/api/v1/graph/positions', [
            'positions' => [
                ['node_type' => 'invalid', 'node_id' => 1, 'x' => 100, 'y' => 200],
            ],
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_save_positions_empty_array(): void
    {
        $response = $this->postJson('/api/v1/graph/positions', [
            'positions' => [],
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.saved', 0);
    }

    public function test_get_edges_from_includes(): void
    {
        $prompt = Prompt::create([
            'name' => 'Main Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        $fragment = Prompt::create([
            'name' => 'My Fragment',
            'slug' => 'my-fragment',
            'type' => 'fragment',
            'created_by' => $this->user->id,
        ]);

        $branchId = $this->createDefaultBranch($prompt);
        $this->createVersion($prompt, $branchId, [
            'content' => 'Hello {{>my-fragment}} world',
            'includes' => ['my-fragment'],
        ]);

        $response = $this->getJson('/api/v1/graph/edges', $this->headers);

        $response->assertStatus(200);

        $edges = $response->json('data.composition');
        $this->assertCount(1, $edges);
        $this->assertEquals($prompt->slug, $edges[0]['source_slug']);
        $this->assertEquals('my-fragment', $edges[0]['target_slug']);
        $this->assertEquals('includes', $edges[0]['type']);
    }

    public function test_get_edges_from_collections(): void
    {
        $prompt = Prompt::create([
            'name' => 'Collected Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        $branchId = $this->createDefaultBranch($prompt);
        $version = $this->createVersion($prompt, $branchId, [
            'content' => 'Simple content',
        ]);
        $collection = Collection::create([
            'title' => 'My Collection',
            'created_by' => $this->user->id,
        ]);
        $collection->items()->create([
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/v1/graph/edges', $this->headers);

        $response->assertStatus(200);

        $collectionEdges = $response->json('data.collection');
        $this->assertCount(1, $collectionEdges);
        $this->assertEquals($collection->slug, $collectionEdges[0]['collection_slug']);
    }

    public function test_append_include(): void
    {
        $prompt = Prompt::create([
            'name' => 'Target Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        $branchId = $this->createDefaultBranch($prompt);
        $this->createVersion($prompt, $branchId, [
            'content' => 'Original content',
        ]);

        Prompt::create([
            'name' => 'Fragment To Include',
            'slug' => 'fragment-to-include',
            'type' => 'fragment',
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/append-include",
            ['fragment_slug' => 'fragment-to-include'],
            $this->headers
        );

        $response->assertStatus(200);

        $newVersion = PromptVersion::where('prompt_id', $prompt->id)
            ->latest('version_number')
            ->first();
        $this->assertStringContainsString('{{>fragment-to-include}}', $newVersion->content);
        $this->assertEquals(2, $newVersion->version_number);
    }

    public function test_append_include_rejects_nonexistent_fragment(): void
    {
        $prompt = Prompt::create([
            'name' => 'Target',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        $branchId = $this->createDefaultBranch($prompt);
        $this->createVersion($prompt, $branchId, [
            'content' => 'Content',
        ]);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/append-include",
            ['fragment_slug' => 'nonexistent'],
            $this->headers
        );

        $response->assertStatus(404);
    }

    public function test_remove_include(): void
    {
        $prompt = Prompt::create([
            'name' => 'Has Include',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        $branchId = $this->createDefaultBranch($prompt);
        $this->createVersion($prompt, $branchId, [
            'content' => "First line\n{{>my-frag}}\nLast line",
            'includes' => ['my-frag'],
        ]);

        $response = $this->deleteJson(
            "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/remove-include",
            ['fragment_slug' => 'my-frag'],
            $this->headers
        );

        $response->assertStatus(200);

        $newVersion = PromptVersion::where('prompt_id', $prompt->id)
            ->latest('version_number')
            ->first();
        $this->assertStringNotContainsString('{{>my-frag}}', $newVersion->content);
        $this->assertEquals(2, $newVersion->version_number);
    }

    private function createDefaultBranch(Prompt $prompt): int
    {
        $branch = PromptBranch::create([
            'prompt_id' => $prompt->id,
            'name' => 'main',
            'is_default' => true,
            'created_by' => $this->user->id,
        ]);
        $prompt->update(['default_branch_id' => $branch->id]);

        return $branch->id;
    }

    /**
     * Create a PromptVersion and update the branch HEAD.
     */
    private function createVersion(Prompt $prompt, int $branchId, array $overrides = []): PromptVersion
    {
        $version = PromptVersion::create(array_merge([
            'prompt_id' => $prompt->id,
            'content' => 'Default content',
            'version_number' => 1,
            'variables' => [],
            'includes' => [],
            'branch_id' => $branchId,
            'branch_version_number' => 1,
            'created_by' => $this->user->id,
        ], $overrides));

        // Update branch HEAD so activeVersion accessor works
        PromptBranch::where('id', $branchId)->update(['head_version_id' => $version->id]);

        return $version;
    }
}
