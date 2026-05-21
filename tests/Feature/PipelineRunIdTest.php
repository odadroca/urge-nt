<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\LlmProvider;
use App\Models\Pipeline;
use App\Models\PipelineChannel;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\LlmDispatchService;
use App\Services\LlmProviders\LlmResult;
use App\Services\PipelineService;
use App\Services\TemplateEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PipelineRunIdTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Prompt $prompt;

    private PromptVersion $version;

    private LlmProvider $provider;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $result = app(ApiKeyService::class)->generateKey($this->user, 'Test Key');
        $this->headers = ['Authorization' => "Bearer {$result['key']}"];

        $this->prompt = Prompt::create([
            'name' => 'Test Prompt',
            'type' => 'chat',
            'created_by' => $this->user->id,
        ]);

        $this->version = PromptVersion::create([
            'prompt_id' => $this->prompt->id,
            'content' => 'Hello world',
            'version_number' => 1,
            'created_by' => $this->user->id,
        ]);

        $this->provider = LlmProvider::create([
            'name' => 'TestProvider',
            'driver' => 'openai',
            'model' => 'gpt-4',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);
    }

    public function test_pipeline_run_id_assigned_on_run(): void
    {
        $template = Pipeline::create([
            'name' => 'Test Template',
            'created_by' => $this->user->id,
        ]);

        PipelineChannel::create([
            'pipeline_id' => $template->id,
            'role_label' => 'Analyst',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        PipelineChannel::create([
            'pipeline_id' => $template->id,
            'role_label' => 'Critic',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 1,
        ]);

        $mock = Mockery::mock(LlmDispatchService::class);
        $mock->shouldReceive('dispatchWithSystem')
            ->twice()
            ->andReturn(LlmResult::success('Response text', 'gpt-4', 10, 20));

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mock,
        );

        $runResult = $service->run($template, $this->version, [], $this->user->id);

        $this->assertCount(2, $runResult['result_ids']);

        $results = Result::whereIn('id', $runResult['result_ids'])->get();
        $runIds = $results->pluck('pipeline_run_id')->unique();

        // All results share the same pipeline_run_id
        $this->assertCount(1, $runIds);
        $this->assertNotNull($runIds->first());
        // UUID format
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $runIds->first());
    }

    public function test_different_runs_get_different_ids(): void
    {
        $template = Pipeline::create([
            'name' => 'Test Template',
            'created_by' => $this->user->id,
        ]);

        PipelineChannel::create([
            'pipeline_id' => $template->id,
            'role_label' => 'Analyst',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $mock = Mockery::mock(LlmDispatchService::class);
        $mock->shouldReceive('dispatchWithSystem')
            ->times(2)
            ->andReturn(LlmResult::success('Response', 'gpt-4', 10, 20));

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mock,
        );

        $firstRunResult = $service->run($template, $this->version, [], $this->user->id);
        $secondRunResult = $service->run($template, $this->version, [], $this->user->id);

        $firstRunId = Result::find($firstRunResult['result_ids'][0])->pipeline_run_id;
        $secondRunId = Result::find($secondRunResult['result_ids'][0])->pipeline_run_id;

        $this->assertNotEquals($firstRunId, $secondRunId);
    }

    public function test_collect_pipeline_run_adds_all_results_via_api(): void
    {
        $collection = Collection::create([
            'title' => 'Test Collection',
            'created_by' => $this->user->id,
        ]);

        $runId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $resultA = Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'pipeline_run_id' => $runId,
            'pipeline_id' => null,
            'provider_name' => 'TestProvider',
            'response_text' => 'Result A',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        $resultB = Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'pipeline_run_id' => $runId,
            'pipeline_id' => null,
            'provider_name' => 'TestProvider',
            'response_text' => 'Result B',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        // Add each result to collection via API
        $this->postJson("/api/v1/collections/{$collection->slug}/items", [
            'item_type' => 'result',
            'item_id' => $resultA->id,
        ], $this->headers)->assertStatus(201);

        $this->postJson("/api/v1/collections/{$collection->slug}/items", [
            'item_type' => 'result',
            'item_id' => $resultB->id,
        ], $this->headers)->assertStatus(201);

        $this->assertEquals(2, CollectionItem::where('collection_id', $collection->id)->count());
    }

    public function test_results_grouped_by_pipeline_run_id(): void
    {
        $template = Pipeline::create([
            'name' => 'Template',
            'created_by' => $this->user->id,
        ]);

        $runIdA = 'aaaaaaaa-0000-0000-0000-000000000001';
        $runIdB = 'aaaaaaaa-0000-0000-0000-000000000002';

        // Two results with same template but different run IDs (created at same time)
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'pipeline_id' => $template->id,
            'pipeline_run_id' => $runIdA,
            'provider_name' => 'TestProvider',
            'response_text' => 'Run A result',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'pipeline_id' => $template->id,
            'pipeline_run_id' => $runIdB,
            'provider_name' => 'TestProvider',
            'response_text' => 'Run B result',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        // Fetch results via API and verify they have different pipeline_run_ids
        $response = $this->getJson(
            "/api/v1/prompts/{$this->user->slug}/{$this->prompt->slug}/results",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $data = $response->json('data');
        $runIds = array_unique(array_column($data, 'pipeline_run_id'));
        $this->assertCount(2, $runIds);
    }
}
