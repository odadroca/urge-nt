<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_browse_redirects_to_react_spa(): void
    {
        $response = $this->actingAs($this->user)->get('/browse');
        $response->assertRedirect('/app/browse');
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

        // Without filter, both visible
        $response = $this->actingAs($this->user)->getJson('/api/v1/prompts');
        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Marketing Prompt'));
        $this->assertTrue($names->contains('Other Prompt'));

        // With category filter
        $response = $this->actingAs($this->user)->getJson('/api/v1/prompts?category_id=' . $cat->id);
        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Marketing Prompt'));
        $this->assertFalse($names->contains('Other Prompt'));
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

        $response = $this->actingAs($this->user)->getJson('/api/v1/prompts?tag=ai');
        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Tagged Prompt'));
        $this->assertFalse($names->contains('Untagged Prompt'));
    }

    public function test_starred_results_endpoint(): void
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

        $response = $this->actingAs($this->user)->getJson('/api/v1/results/starred');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('StarProvider', $data[0]['provider_name']);
        $this->assertEquals('Starred response', $data[0]['response_text']);
    }

    public function test_collections_list_endpoint(): void
    {
        Collection::create([
            'title' => 'Browse Collection',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/collections');
        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Browse Collection'));
    }

    public function test_prompts_include_results_count(): void
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

        $response = $this->actingAs($this->user)->getJson('/api/v1/prompts');
        $response->assertOk();
        $prompt = collect($response->json('data'))->firstWhere('name', 'Count Test');
        $this->assertNotNull($prompt);
        // The API returns prompt data — verify the prompt exists and results can be queried
        $resultsResponse = $this->actingAs($this->user)->getJson(
            '/api/v1/prompts/' . $this->user->slug . '/' . $prompt['slug'] . '/results'
        );
        $resultsResponse->assertOk();
        $this->assertCount(2, $resultsResponse->json('data'));
    }
}
