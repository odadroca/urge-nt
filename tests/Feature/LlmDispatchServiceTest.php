<?php

namespace Tests\Feature;

use App\Models\LlmProvider;
use App\Models\User;
use App\Services\LlmDispatchService;
use App\Services\LlmProviders\LlmResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private LlmDispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->service = new LlmDispatchService();
    }

    public function test_llm_result_success_factory(): void
    {
        $result = LlmResult::success('Hello', 'gpt-4', 150, 10, 5);

        $this->assertTrue($result->success);
        $this->assertEquals('Hello', $result->text);
        $this->assertEquals('gpt-4', $result->modelUsed);
        $this->assertEquals(150, $result->durationMs);
        $this->assertEquals(10, $result->inputTokens);
        $this->assertEquals(5, $result->outputTokens);
        $this->assertNull($result->error);
    }

    public function test_llm_result_failure_factory(): void
    {
        $result = LlmResult::failure('Rate limited', 'gpt-4', 50);

        $this->assertFalse($result->success);
        $this->assertNull($result->text);
        $this->assertEquals('gpt-4', $result->modelUsed);
        $this->assertEquals(50, $result->durationMs);
        $this->assertEquals('Rate limited', $result->error);
        $this->assertNull($result->inputTokens);
        $this->assertNull($result->outputTokens);
    }

    public function test_dispatch_openai_driver(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hello from OpenAI']]],
                'model' => 'gpt-4o-mini',
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $provider = LlmProvider::create([
            'name' => 'OpenAI',
            'driver' => 'openai',
            'api_key' => 'sk-test-key',
            'model' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $result = $this->service->dispatch($provider, 'Hello');

        $this->assertTrue($result->success);
        $this->assertEquals('Hello from OpenAI', $result->text);
        $this->assertEquals('gpt-4o-mini', $result->modelUsed);
        $this->assertEquals(10, $result->inputTokens);
        $this->assertEquals(5, $result->outputTokens);
    }

    public function test_dispatch_anthropic_driver(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'Hello from Claude']],
                'model' => 'claude-haiku-4-5-20251001',
                'usage' => ['input_tokens' => 8, 'output_tokens' => 4],
            ]),
        ]);

        $provider = LlmProvider::create([
            'name' => 'Anthropic',
            'driver' => 'anthropic',
            'api_key' => 'sk-ant-test',
            'model' => 'claude-haiku-4-5-20251001',
            'is_active' => true,
        ]);

        $result = $this->service->dispatch($provider, 'Hello');

        $this->assertTrue($result->success);
        $this->assertEquals('Hello from Claude', $result->text);
        $this->assertEquals(8, $result->inputTokens);
    }

    public function test_dispatch_with_system_prompt(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'System response']]],
                'model' => 'gpt-4o-mini',
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 5],
            ]),
        ]);

        $provider = LlmProvider::create([
            'name' => 'OpenAI',
            'driver' => 'openai',
            'api_key' => 'sk-test-key',
            'model' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $result = $this->service->dispatchWithSystem($provider, 'You are a helper', 'Hello');

        $this->assertTrue($result->success);
        $this->assertEquals('System response', $result->text);
    }

    public function test_unknown_driver_throws_exception(): void
    {
        $provider = LlmProvider::create([
            'name' => 'Unknown',
            'driver' => 'fakellm',
            'api_key' => 'key',
            'model' => 'model',
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown LLM driver: fakellm');
        $this->service->dispatch($provider, 'Hello');
    }

    public function test_missing_api_key_throws_exception(): void
    {
        $provider = LlmProvider::create([
            'name' => 'No Key',
            'driver' => 'openai',
            'model' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No API key configured');
        $this->service->dispatch($provider, 'Hello');
    }

    public function test_ollama_does_not_require_api_key(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response([
                'message' => ['content' => 'Ollama response'],
                'model' => 'llama3.2',
            ]),
        ]);

        $provider = LlmProvider::create([
            'name' => 'Ollama',
            'driver' => 'ollama',
            'model' => 'llama3.2',
            'endpoint' => 'http://localhost:11434',
            'is_active' => true,
        ]);

        $result = $this->service->dispatch($provider, 'Hello');

        $this->assertTrue($result->success);
        $this->assertEquals('Ollama response', $result->text);
    }

    public function test_failed_api_response_returns_failure(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $provider = LlmProvider::create([
            'name' => 'OpenAI',
            'driver' => 'openai',
            'api_key' => 'sk-test-key',
            'model' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $result = $this->service->dispatch($provider, 'Hello');

        $this->assertFalse($result->success);
        $this->assertEquals('Rate limit exceeded', $result->error);
    }
}
