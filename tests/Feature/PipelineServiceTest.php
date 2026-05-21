<?php

namespace Tests\Feature;

use App\Models\LlmProvider;
use App\Models\Pipeline;
use App\Models\PipelineChannel;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use App\Services\LlmDispatchService;
use App\Services\LlmProviders\LlmResult;
use App\Services\PipelineService;
use App\Services\TemplateEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PipelineServiceTest extends TestCase
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
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $this->version = PromptVersion::create([
            'prompt_id' => $this->prompt->id,
            'content' => 'Hello world, analyze this prompt.',
            'version_number' => 1,
            'created_by' => $this->user->id,
        ]);

        $this->provider = LlmProvider::create([
            'name' => 'Test Provider',
            'driver' => 'openai',
            'model' => 'gpt-4',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);
    }

    public function test_parallel_channels_create_results(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Analyst',
            'llm_provider_id' => $this->provider->id,
            'system_prompt' => 'You are an analyst.',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Critic',
            'llm_provider_id' => $this->provider->id,
            'system_prompt' => 'You are a critic.',
            'trigger' => 'parallel',
            'sort_order' => 1,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->twice()
            ->andReturn(
                LlmResult::success('Analyst response', 'gpt-4', 100, 10, 20),
                LlmResult::success('Critic response', 'gpt-4', 150, 15, 25),
            );

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, ['name' => 'World'], $this->user->id);

        $this->assertCount(2, $runResult['result_ids']);
        $this->assertEmpty($runResult['pending_client_channels']);

        $results = Result::whereIn('id', $runResult['result_ids'])->get();
        $this->assertEquals('Analyst', $results[0]->role_label);
        $this->assertEquals('Critic', $results[1]->role_label);
        $this->assertEquals($pipeline->id, $results[0]->pipeline_id);
    }

    public function test_synthesis_channel_receives_parallel_output(): void
    {
        $pipeline = Pipeline::create(['name' => 'Synth Test', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Analyst',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Synthesizer',
            'llm_provider_id' => $this->provider->id,
            'system_prompt' => 'Combine the results.',
            'trigger' => 'synthesis',
            'sort_order' => 99,
        ]);

        $callCount = 0;
        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->twice()
            ->andReturnUsing(function ($provider, $systemPrompt, $content) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return LlmResult::success('Parallel output', 'gpt-4', 100, 10, 20);
                }
                $this->assertStringContains('[ANALYST]', $content);
                $this->assertStringContains('Parallel output', $content);
                $this->assertEquals('Combine the results.', $systemPrompt);

                return LlmResult::success('Synthesized response', 'gpt-4', 200, 30, 40);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertCount(2, $runResult['result_ids']);
        $this->assertEmpty($runResult['pending_client_channels']);

        $synthesisResult = Result::find($runResult['result_ids'][1]);
        $this->assertEquals('Synthesizer', $synthesisResult->role_label);
        $this->assertEquals('Synthesized response', $synthesisResult->response_text);
    }

    public function test_channel_with_no_provider_is_pending_client(): void
    {
        $pipeline = Pipeline::create(['name' => 'Client Test', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Local Worker',
            'llm_provider_id' => null,
            'system_prompt' => 'Run me locally.',
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldNotReceive('dispatchWithSystem');

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertEmpty($runResult['result_ids']);
        $this->assertCount(1, $runResult['pending_client_channels']);

        $pending = $runResult['pending_client_channels'][0];
        $this->assertEquals('Local Worker', $pending['role_label']);
        $this->assertEquals('parallel', $pending['trigger']);
        $this->assertEquals('Run me locally.', $pending['system_prompt']);
        $this->assertStringContains('Hello world', $pending['user_prompt']);
    }

    public function test_channel_with_inactive_provider_is_pending_client(): void
    {
        $inactiveProvider = LlmProvider::create([
            'name' => 'Inactive',
            'driver' => 'openai',
            'model' => 'gpt-4',
            'api_key' => 'test-key',
            'is_active' => false,
        ]);

        $pipeline = Pipeline::create(['name' => 'Inactive Test', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Inactive Channel',
            'llm_provider_id' => $inactiveProvider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldNotReceive('dispatchWithSystem');

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertEmpty($runResult['result_ids']);
        $this->assertCount(1, $runResult['pending_client_channels']);
        $this->assertEquals('Inactive Channel', $runResult['pending_client_channels'][0]['role_label']);
    }

    public function test_synthesis_pending_client_gets_prebuilt_input_when_parallels_are_server(): void
    {
        $pipeline = Pipeline::create(['name' => 'Mixed', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Analyst',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Synthesizer',
            'llm_provider_id' => null,
            'system_prompt' => 'Combine results.',
            'trigger' => 'synthesis',
            'sort_order' => 99,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturn(LlmResult::success('Analyst result', 'gpt-4', 100, 10, 20));

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertCount(1, $runResult['result_ids']);
        $this->assertCount(1, $runResult['pending_client_channels']);

        $synthPending = $runResult['pending_client_channels'][0];
        $this->assertEquals('Synthesizer', $synthPending['role_label']);
        $this->assertEquals('synthesis', $synthPending['trigger']);
        $this->assertNotNull($synthPending['user_prompt']);
        $this->assertStringContains('[ANALYST]', $synthPending['user_prompt']);
        $this->assertStringContains('Analyst result', $synthPending['user_prompt']);
    }

    public function test_synthesis_pending_client_user_prompt_null_when_mixing_server_and_client_parallels(): void
    {
        // One server-dispatched parallel (success), one client-pending parallel,
        // and a client synthesis. Prebuilding the synthesis input from only the
        // server result would silently drop the client parallel's output once
        // the caller runs it — return null and let the caller assemble.
        $inactive = LlmProvider::create([
            'name' => 'Inactive',
            'driver' => 'openai',
            'model' => 'gpt-4',
            'api_key' => 'test-key',
            'is_active' => false,
        ]);

        $pipeline = Pipeline::create(['name' => 'Mixed parallels', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'ServerSide',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'ClientSide',
            'llm_provider_id' => $inactive->id,
            'trigger' => 'parallel',
            'sort_order' => 1,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Synthesizer',
            'llm_provider_id' => null,
            'system_prompt' => 'Combine.',
            'trigger' => 'synthesis',
            'sort_order' => 99,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturn(LlmResult::success('server output', 'gpt-4', 100, 10, 20));

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertCount(1, $runResult['result_ids']);
        $this->assertCount(2, $runResult['pending_client_channels']);

        $synthPending = collect($runResult['pending_client_channels'])
            ->firstWhere('trigger', 'synthesis');
        $this->assertNotNull($synthPending);
        $this->assertNull(
            $synthPending['user_prompt'],
            'Synthesis user_prompt should be null when any parallel is client-pending; otherwise the caller would synthesize on incomplete context.'
        );
    }

    public function test_synthesis_pending_client_with_result_history_input_unaffected_by_client_parallels(): void
    {
        // result_history synthesis ignores this run's parallels, so a mixed
        // client/server parallel set should NOT cause user_prompt to go null.
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'response_text' => 'historical answer',
            'created_by' => $this->user->id,
        ]);

        $inactive = LlmProvider::create([
            'name' => 'Inactive2',
            'driver' => 'openai',
            'model' => 'gpt-4',
            'api_key' => 'test-key',
            'is_active' => false,
        ]);

        $pipeline = Pipeline::create(['name' => 'History+mixed', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'ClientParallel',
            'llm_provider_id' => $inactive->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'HistorySynth',
            'llm_provider_id' => null,
            'input_source' => 'result_history',
            'input_filters' => [],
            'trigger' => 'synthesis',
            'sort_order' => 99,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldNotReceive('dispatchWithSystem');

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $synthPending = collect($runResult['pending_client_channels'])
            ->firstWhere('trigger', 'synthesis');
        $this->assertNotNull($synthPending);
        $this->assertNotNull(
            $synthPending['user_prompt'],
            'result_history synthesis is independent of this run\'s parallels — should still get its prebuilt history input.'
        );
        $this->assertStringContains('historical answer', $synthPending['user_prompt']);
    }

    public function test_synthesis_pending_client_user_prompt_null_when_all_parallels_client(): void
    {
        $pipeline = Pipeline::create(['name' => 'All client', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Worker',
            'llm_provider_id' => null,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Synthesizer',
            'llm_provider_id' => null,
            'trigger' => 'synthesis',
            'sort_order' => 99,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldNotReceive('dispatchWithSystem');

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertEmpty($runResult['result_ids']);
        $this->assertCount(2, $runResult['pending_client_channels']);

        $synthPending = collect($runResult['pending_client_channels'])
            ->firstWhere('trigger', 'synthesis');
        $this->assertNotNull($synthPending);
        $this->assertNull($synthPending['user_prompt']);
    }

    public function test_synthesis_skipped_when_no_parallel_results_and_synthesis_is_server(): void
    {
        $pipeline = Pipeline::create(['name' => 'No Parallel', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Lonely Synthesizer',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'synthesis',
            'sort_order' => 0,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldNotReceive('dispatchWithSystem');

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertEmpty($runResult['result_ids']);
        $this->assertEmpty($runResult['pending_client_channels']);
    }

    public function test_failed_parallel_result_excluded_from_synthesis_input(): void
    {
        $pipeline = Pipeline::create(['name' => 'Fail Test', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Succeeder',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Failer',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 1,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Synthesizer',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'synthesis',
            'sort_order' => 99,
        ]);

        $callCount = 0;
        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->times(3)
            ->andReturnUsing(function ($provider, $systemPrompt, $content) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return LlmResult::success('Good result', 'gpt-4', 100, 10, 20);
                }
                if ($callCount === 2) {
                    return LlmResult::failure('API error', 'gpt-4', 50);
                }
                $this->assertStringContains('[SUCCEEDER]', $content);
                $this->assertStringContains('Good result', $content);
                $this->assertStringNotContains('FAILER', $content);

                return LlmResult::success('Synthesis result', 'gpt-4', 200, 30, 40);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertCount(3, $runResult['result_ids']);
        $this->assertEmpty($runResult['pending_client_channels']);
    }

    public function test_result_history_channel_serializes_recent_results(): void
    {
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'response_text' => 'old answer',
            'provider_name' => 'OpenAI',
            'model_name' => 'gpt-4',
            'created_by' => $this->user->id,
        ]);

        $pipeline = Pipeline::create(['name' => 'Trend', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Analyst',
            'llm_provider_id' => $this->provider->id,
            'system_prompt' => 'Analyze this history.',
            'input_source' => 'result_history',
            'input_filters' => [],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $captured = null;
        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturnUsing(function ($provider, $systemPrompt, $content) use (&$captured) {
                $captured = $content;

                return LlmResult::success('Trend report', 'gpt-4', 100);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertCount(1, $runResult['result_ids']);
        $this->assertNotNull($captured);
        $this->assertStringContains('old answer', $captured);
        $this->assertStringContains('OpenAI · gpt-4', $captured);
    }

    public function test_result_history_channel_respects_since_window(): void
    {
        // Old result outside the window
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'response_text' => 'ancient',
            'created_by' => $this->user->id,
        ])->forceFill(['created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)])->save();

        // Recent result inside the window
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'response_text' => 'recent',
            'created_by' => $this->user->id,
        ]);

        $pipeline = Pipeline::create(['name' => 'Window', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Recent',
            'llm_provider_id' => $this->provider->id,
            'input_source' => 'result_history',
            'input_filters' => ['since' => 'P30D'],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $captured = null;
        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturnUsing(function ($provider, $systemPrompt, $content) use (&$captured) {
                $captured = $content;

                return LlmResult::success('OK', 'gpt-4', 100);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );
        $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertStringContains('recent', $captured);
        $this->assertStringNotContains('ancient', $captured);
    }

    public function test_result_history_channel_respects_run_source_filter(): void
    {
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'run_source' => 'manual',
            'response_text' => 'ad-hoc',
            'created_by' => $this->user->id,
        ]);
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'run_source' => 'scheduled',
            'response_text' => 'cron-output',
            'created_by' => $this->user->id,
        ]);

        $pipeline = Pipeline::create(['name' => 'Scheduled-only', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Trend',
            'llm_provider_id' => $this->provider->id,
            'input_source' => 'result_history',
            'input_filters' => ['run_source' => 'scheduled'],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $captured = null;
        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturnUsing(function ($provider, $systemPrompt, $content) use (&$captured) {
                $captured = $content;

                return LlmResult::success('OK', 'gpt-4', 100);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );
        $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertStringContains('cron-output', $captured);
        $this->assertStringNotContains('ad-hoc', $captured);
    }

    public function test_result_history_channel_skipped_when_no_results_match(): void
    {
        $pipeline = Pipeline::create(['name' => 'Empty', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Trend',
            'llm_provider_id' => $this->provider->id,
            'input_source' => 'result_history',
            'input_filters' => [],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldNotReceive('dispatchWithSystem');

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertEmpty($runResult['result_ids']);
        $this->assertEmpty($runResult['pending_client_channels']);
    }

    public function test_result_history_pending_client_when_no_provider(): void
    {
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'response_text' => 'historical answer',
            'created_by' => $this->user->id,
        ]);

        $pipeline = Pipeline::create(['name' => 'Client analytical', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Trend',
            'llm_provider_id' => null,
            'input_source' => 'result_history',
            'input_filters' => [],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldNotReceive('dispatchWithSystem');

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertEmpty($runResult['result_ids']);
        $this->assertCount(1, $runResult['pending_client_channels']);
        $pending = $runResult['pending_client_channels'][0];
        $this->assertEquals('result_history', $pending['input_source']);
        $this->assertStringContains('historical answer', $pending['user_prompt']);
    }

    public function test_result_history_excludes_failures_by_default(): void
    {
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'status' => 'success',
            'response_text' => 'good run',
            'created_by' => $this->user->id,
        ]);
        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->version->id,
            'source' => 'api',
            'status' => 'error',
            'response_text' => 'bad run',
            'error_message' => 'API timeout',
            'created_by' => $this->user->id,
        ]);

        $pipeline = Pipeline::create(['name' => 'No failures', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Trend',
            'llm_provider_id' => $this->provider->id,
            'input_source' => 'result_history',
            'input_filters' => [],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $captured = null;
        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturnUsing(function ($provider, $systemPrompt, $content) use (&$captured) {
                $captured = $content;

                return LlmResult::success('OK', 'gpt-4', 100);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );
        $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertStringContains('good run', $captured);
        $this->assertStringNotContains('bad run', $captured);
    }

    public function test_result_history_respects_visibility(): void
    {
        // A second user creates a private prompt with results
        $other = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);
        $otherPrompt = Prompt::create([
            'name' => 'Hidden',
            'type' => 'prompt',
            'created_by' => $other->id,
            'visibility' => 'private',
        ]);
        $otherVersion = PromptVersion::create([
            'prompt_id' => $otherPrompt->id,
            'content' => 'x',
            'version_number' => 1,
            'created_by' => $other->id,
        ]);
        Result::create([
            'prompt_id' => $otherPrompt->id,
            'prompt_version_id' => $otherVersion->id,
            'source' => 'api',
            'response_text' => 'private secret',
            'created_by' => $other->id,
        ]);

        // Non-admin user tries to read other's history via input_filters
        $reader = User::create([
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);
        $readerPrompt = Prompt::create([
            'name' => 'Reader Prompt',
            'type' => 'prompt',
            'created_by' => $reader->id,
        ]);
        $readerVersion = PromptVersion::create([
            'prompt_id' => $readerPrompt->id,
            'content' => 'y',
            'version_number' => 1,
            'created_by' => $reader->id,
        ]);

        $pipeline = Pipeline::create(['name' => 'Sneaky', 'created_by' => $reader->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Peeker',
            'llm_provider_id' => $this->provider->id,
            'input_source' => 'result_history',
            'input_filters' => ['prompt_slug' => $otherPrompt->slug, 'owner' => $other->slug],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldNotReceive('dispatchWithSystem');

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $readerVersion, [], $reader->id);

        // Non-visible target → channel resolves to no input → skipped
        $this->assertEmpty($runResult['result_ids']);
        $this->assertEmpty($runResult['pending_client_channels']);
    }

    /**
     * @testWith [0]
     *           [-1]
     *           ["abc"]
     */
    public function test_result_history_limit_falls_back_to_default_when_invalid($invalidLimit): void
    {
        // Seed 60 results — more than the safe default of 50, fewer than the cap.
        for ($i = 0; $i < 60; $i++) {
            Result::create([
                'prompt_id' => $this->prompt->id,
                'prompt_version_id' => $this->version->id,
                'source' => 'api',
                'response_text' => "result $i",
                'created_by' => $this->user->id,
            ]);
        }

        $pipeline = Pipeline::create(['name' => 'Bad limit', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Trend',
            'llm_provider_id' => $this->provider->id,
            'input_source' => 'result_history',
            'input_filters' => ['limit' => $invalidLimit],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $captured = null;
        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturnUsing(function ($provider, $systemPrompt, $content) use (&$captured) {
                $captured = $content;

                return LlmResult::success('OK', 'gpt-4', 100);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );
        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        // Channel should run (not be skipped) and use the default 50 — not 0 (skip)
        // and not -1 (which on SQLite would dump the entire 60-row history).
        $this->assertCount(1, $runResult['result_ids']);
        $entryCount = substr_count($captured, "\n\n---\n\n") + 1;
        $this->assertEquals(50, $entryCount, 'Expected default limit of 50 entries');
    }

    public function test_result_history_limit_capped_at_100(): void
    {
        for ($i = 0; $i < 110; $i++) {
            Result::create([
                'prompt_id' => $this->prompt->id,
                'prompt_version_id' => $this->version->id,
                'source' => 'api',
                'response_text' => "r$i",
                'created_by' => $this->user->id,
            ]);
        }

        $pipeline = Pipeline::create(['name' => 'Cap', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Trend',
            'llm_provider_id' => $this->provider->id,
            'input_source' => 'result_history',
            'input_filters' => ['limit' => 500],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $captured = null;
        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturnUsing(function ($provider, $systemPrompt, $content) use (&$captured) {
                $captured = $content;

                return LlmResult::success('OK', 'gpt-4', 100);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );
        $service->run($pipeline, $this->version, [], $this->user->id);

        $entryCount = substr_count($captured, "\n\n---\n\n") + 1;
        $this->assertEquals(100, $entryCount, 'Expected limit cap of 100');
    }

    public function test_result_history_respects_explicit_valid_limit(): void
    {
        for ($i = 0; $i < 20; $i++) {
            Result::create([
                'prompt_id' => $this->prompt->id,
                'prompt_version_id' => $this->version->id,
                'source' => 'api',
                'response_text' => "r$i",
                'created_by' => $this->user->id,
            ]);
        }

        $pipeline = Pipeline::create(['name' => 'Explicit', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Trend',
            'llm_provider_id' => $this->provider->id,
            'input_source' => 'result_history',
            'input_filters' => ['limit' => 5],
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $captured = null;
        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturnUsing(function ($provider, $systemPrompt, $content) use (&$captured) {
                $captured = $content;

                return LlmResult::success('OK', 'gpt-4', 100);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );
        $service->run($pipeline, $this->version, [], $this->user->id);

        $entryCount = substr_count($captured, "\n\n---\n\n") + 1;
        $this->assertEquals(5, $entryCount);
    }

    public function test_run_source_is_stamped_on_every_result(): void
    {
        $pipeline = Pipeline::create(['name' => 'Scheduled', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Worker',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Synthesizer',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'synthesis',
            'sort_order' => 99,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->twice()
            ->andReturn(LlmResult::success('Out', 'gpt-4', 100, 10, 20));

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id, 'scheduled');

        $this->assertCount(2, $runResult['result_ids']);
        foreach (Result::whereIn('id', $runResult['result_ids'])->get() as $result) {
            $this->assertEquals('scheduled', $result->run_source);
        }
    }

    public function test_run_source_defaults_to_null_when_not_provided(): void
    {
        $pipeline = Pipeline::create(['name' => 'Default', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Worker',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturn(LlmResult::success('Out', 'gpt-4', 100));

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertNull(Result::find($runResult['result_ids'][0])->run_source);
    }

    public function test_variables_are_rendered_in_content(): void
    {
        $varVersion = PromptVersion::create([
            'prompt_id' => $this->prompt->id,
            'content' => 'Hello {{name}}',
            'version_number' => 2,
            'created_by' => $this->user->id,
        ]);

        $pipeline = Pipeline::create(['name' => 'Var Test', 'created_by' => $this->user->id]);
        PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => 'Worker',
            'llm_provider_id' => $this->provider->id,
            'trigger' => 'parallel',
            'sort_order' => 0,
        ]);

        $mockDispatch = Mockery::mock(LlmDispatchService::class);
        $mockDispatch->shouldReceive('dispatchWithSystem')
            ->once()
            ->andReturnUsing(function ($provider, $systemPrompt, $content) {
                $this->assertStringContains('Hello World', $content);

                return LlmResult::success('Done', 'gpt-4', 100);
            });

        $service = new PipelineService(
            app(TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $varVersion, ['name' => 'World'], $this->user->id);

        $this->assertCount(1, $runResult['result_ids']);
        $result = Result::find($runResult['result_ids'][0]);
        $this->assertEquals(['name' => 'World'], $result->variables_used);
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }

    private function assertStringNotContains(string $needle, string $haystack): void
    {
        $this->assertStringNotContainsString($needle, $haystack);
    }
}
