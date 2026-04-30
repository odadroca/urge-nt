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
            app(\App\Services\TemplateEngine::class),
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
            app(\App\Services\TemplateEngine::class),
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
            app(\App\Services\TemplateEngine::class),
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
            app(\App\Services\TemplateEngine::class),
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
            app(\App\Services\TemplateEngine::class),
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
            app(\App\Services\TemplateEngine::class),
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
            app(\App\Services\TemplateEngine::class),
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
            app(\App\Services\TemplateEngine::class),
            $mockDispatch,
        );

        $runResult = $service->run($pipeline, $this->version, [], $this->user->id);

        $this->assertCount(3, $runResult['result_ids']);
        $this->assertEmpty($runResult['pending_client_channels']);
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
            app(\App\Services\TemplateEngine::class),
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
            app(\App\Services\TemplateEngine::class),
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
            app(\App\Services\TemplateEngine::class),
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
