<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\LlmProvider;
use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateChannel;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use App\Services\LlmDispatchService;
use App\Services\LlmProviders\LlmResult;
use App\Services\PipelineTemplateService;
use App\Services\TemplateEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PipelineRunIdTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Prompt $prompt;
    private PromptVersion $version;
    private LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

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
        $template = PipelineTemplate::create([
            'name' => 'Test Template',
            'created_by' => $this->user->id,
        ]);

        PipelineTemplateChannel::create([
            'pipeline_template_id' => $template->id,
            'role_label' => 'Analyst',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        PipelineTemplateChannel::create([
            'pipeline_template_id' => $template->id,
            'role_label' => 'Critic',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 1,
        ]);

        $mock = Mockery::mock(LlmDispatchService::class);
        $mock->shouldReceive('dispatchWithSystem')
            ->twice()
            ->andReturn(LlmResult::success('Response text', 'gpt-4', 10, 20));

        $service = new PipelineTemplateService(
            app(TemplateEngine::class),
            $mock,
        );

        $resultIds = $service->run($template, $this->version, [], $this->user->id);

        $this->assertCount(2, $resultIds);

        $results = Result::whereIn('id', $resultIds)->get();
        $runIds = $results->pluck('pipeline_run_id')->unique();

        // All results share the same pipeline_run_id
        $this->assertCount(1, $runIds);
        $this->assertNotNull($runIds->first());
        // UUID format
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $runIds->first());
    }

    public function test_different_runs_get_different_ids(): void
    {
        $template = PipelineTemplate::create([
            'name' => 'Test Template',
            'created_by' => $this->user->id,
        ]);

        PipelineTemplateChannel::create([
            'pipeline_template_id' => $template->id,
            'role_label' => 'Analyst',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $mock = Mockery::mock(LlmDispatchService::class);
        $mock->shouldReceive('dispatchWithSystem')
            ->times(2)
            ->andReturn(LlmResult::success('Response', 'gpt-4', 10, 20));

        $service = new PipelineTemplateService(
            app(TemplateEngine::class),
            $mock,
        );

        $firstRunIds = $service->run($template, $this->version, [], $this->user->id);
        $secondRunIds = $service->run($template, $this->version, [], $this->user->id);

        $firstRunId = Result::find($firstRunIds[0])->pipeline_run_id;
        $secondRunId = Result::find($secondRunIds[0])->pipeline_run_id;

        $this->assertNotEquals($firstRunId, $secondRunId);
    }

    public function test_collect_pipeline_run_adds_all_results(): void
    {
        $this->actingAs($this->user);

        $collection = Collection::create([
            'title' => 'Test Collection',
            'created_by' => $this->user->id,
        ]);

        $runId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'pipeline_run_id' => $runId,
            'pipeline_template_id' => null,
            'provider_name' => 'TestProvider',
            'response_text' => 'Result A',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'pipeline_run_id' => $runId,
            'pipeline_template_id' => null,
            'provider_name' => 'TestProvider',
            'response_text' => 'Result B',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        Livewire::test(\App\Livewire\Workspace\ResultsPanel::class, ['prompt' => $this->prompt])
            ->call('collectPipelineRun', $runId, $collection->id);

        $this->assertEquals(2, CollectionItem::where('collection_id', $collection->id)->count());
    }

    public function test_grouping_uses_run_id_not_timestamp(): void
    {
        $this->actingAs($this->user);

        $template = PipelineTemplate::create([
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
            'pipeline_template_id' => $template->id,
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
            'pipeline_template_id' => $template->id,
            'pipeline_run_id' => $runIdB,
            'provider_name' => 'TestProvider',
            'response_text' => 'Run B result',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::test(\App\Livewire\Workspace\ResultsPanel::class, ['prompt' => $this->prompt]);

        // The component should render with two separate pipeline groups
        $groupedResults = $component->viewData('groupedResults');
        $pipelineGroups = $groupedResults->where('type', 'pipeline');

        $this->assertCount(2, $pipelineGroups);
    }
}
