<?php

namespace Tests\Feature;

use App\Models\EvaluationSetting;
use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\Result;
use App\Models\ResultEvaluation;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\LlmDispatchService;
use App\Services\LlmProviders\LlmResult;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private array $headers;
    private Prompt $prompt;
    private Result $result;
    private LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Eval User',
            'email' => 'eval@example.com',
            'password' => bcrypt('password'),
        ]);

        $keyResult = app(ApiKeyService::class)->generateKey($this->user, 'Eval Key');
        $this->headers = ['Authorization' => "Bearer {$keyResult['key']}"];

        $this->prompt = Prompt::create([
            'name' => 'Eval Test Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $version = app(VersioningService::class)->createVersion($this->prompt, [
            'content' => 'Write a haiku about {{topic}}',
            'commit_message' => 'Initial',
        ], $this->user);

        $this->result = Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'provider_name' => 'TestProvider',
            'model_name' => 'test-model',
            'rendered_content' => 'Write a haiku about nature',
            'response_text' => 'Green leaves gently fall, Whispers of the autumn wind, Nature rests in peace',
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        $this->provider = LlmProvider::create([
            'name' => 'EvalProvider',
            'driver' => 'openai',
            'api_key' => encrypt('fake-key'),
            'model' => 'gpt-4',
            'is_active' => true,
        ]);
    }

    private function enableEvaluation(): void
    {
        EvaluationSetting::set('enabled', true);
        EvaluationSetting::set('default_provider_id', $this->provider->id);
        EvaluationSetting::set('dimensions', config('urge.evaluation.default_dimensions'));
    }

    private function mockLlmDispatch(): void
    {
        $fakeResponse = json_encode([
            'scores' => [
                ['dimension' => 'relevance', 'score' => 4, 'reasoning' => 'Good relevance'],
                ['dimension' => 'completeness', 'score' => 3, 'reasoning' => 'Partially complete'],
                ['dimension' => 'accuracy', 'score' => 5, 'reasoning' => 'Accurate'],
                ['dimension' => 'clarity', 'score' => 4, 'reasoning' => 'Clear'],
                ['dimension' => 'conciseness', 'score' => 5, 'reasoning' => 'Concise'],
            ],
        ]);

        $mockService = $this->mock(LlmDispatchService::class);
        $mockService->shouldReceive('dispatchWithSystem')
            ->andReturn(LlmResult::success($fakeResponse, 'gpt-4', 500, 100, 200));
    }

    // ---- Test 1: Evaluate result via API ----

    public function test_evaluate_result_via_api(): void
    {
        $this->enableEvaluation();
        $this->mockLlmDispatch();

        $response = $this->postJson(
            "/api/v1/results/{$this->result->id}/evaluate",
            [],
            $this->headers
        );

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertArrayHasKey('composite_score', $data);
        $this->assertArrayHasKey('scores', $data);
        $this->assertCount(5, $data['scores']);
        $this->assertEquals(1, $data['evaluation_version']);
    }

    // ---- Test 2: Evaluate creates DB records ----

    public function test_evaluate_creates_db_records(): void
    {
        $this->enableEvaluation();
        $this->mockLlmDispatch();

        $this->postJson(
            "/api/v1/results/{$this->result->id}/evaluate",
            [],
            $this->headers
        );

        $this->assertEquals(5, ResultEvaluation::where('result_id', $this->result->id)->count());
    }

    // ---- Test 3: List evaluation versions ----

    public function test_list_evaluation_versions(): void
    {
        $this->enableEvaluation();
        $this->mockLlmDispatch();

        $this->postJson(
            "/api/v1/results/{$this->result->id}/evaluate",
            [],
            $this->headers
        );

        $response = $this->getJson(
            "/api/v1/results/{$this->result->id}/evaluations",
            $this->headers
        );

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('evaluation_version', $data[0]);
        $this->assertArrayHasKey('composite_score', $data[0]);
    }

    // ---- Test 4: Get latest evaluation ----

    public function test_get_latest_evaluation(): void
    {
        $this->enableEvaluation();
        $this->mockLlmDispatch();

        $this->postJson(
            "/api/v1/results/{$this->result->id}/evaluate",
            [],
            $this->headers
        );

        $response = $this->getJson(
            "/api/v1/results/{$this->result->id}/evaluations/latest",
            $this->headers
        );

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('scores', $data);
        $this->assertNotEmpty($data['scores']);
    }

    // ---- Test 5: Get specific version ----

    public function test_get_specific_version(): void
    {
        $this->enableEvaluation();
        $this->mockLlmDispatch();

        $this->postJson(
            "/api/v1/results/{$this->result->id}/evaluate",
            [],
            $this->headers
        );

        $response = $this->getJson(
            "/api/v1/results/{$this->result->id}/evaluations/1",
            $this->headers
        );

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(1, $data['evaluation_version']);
    }

    // ---- Test 6: Evaluation disabled returns error ----

    public function test_evaluation_disabled_returns_error(): void
    {
        // Do NOT call enableEvaluation()

        $response = $this->postJson(
            "/api/v1/results/{$this->result->id}/evaluate",
            [],
            $this->headers
        );

        $response->assertStatus(400);
        $this->assertArrayHasKey('error', $response->json());
    }

    // ---- Test 7: Re-evaluation increments version ----

    public function test_re_evaluation_increments_version(): void
    {
        $this->enableEvaluation();
        $this->mockLlmDispatch();

        // First evaluation
        $this->postJson(
            "/api/v1/results/{$this->result->id}/evaluate",
            [],
            $this->headers
        );

        // Second evaluation
        $response = $this->postJson(
            "/api/v1/results/{$this->result->id}/evaluate",
            [],
            $this->headers
        );

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals(2, $data['evaluation_version']);
        $this->assertEquals(10, ResultEvaluation::where('result_id', $this->result->id)->count());
    }

    // ---- Test 8: Human rating sync ----

    public function test_human_rating_syncs(): void
    {
        $this->enableEvaluation();
        $this->mockLlmDispatch();

        // Create an evaluation first
        $this->postJson(
            "/api/v1/results/{$this->result->id}/evaluate",
            [],
            $this->headers
        );

        // Update the result rating
        $this->result->update(['rating' => 4]);

        // Call syncHumanRating
        app(\App\Services\EvaluationService::class)->syncHumanRating($this->result, $this->user);

        // Assert human dimension exists
        $human = ResultEvaluation::where('result_id', $this->result->id)
            ->where('dimension', 'human')
            ->first();

        $this->assertNotNull($human);
        $this->assertEquals(4, $human->score);
    }

    // ---- Test 9: Composite score calculation ----

    public function test_composite_score_calculation(): void
    {
        // Insert 3 rows manually with different scores and weights
        ResultEvaluation::create([
            'result_id' => $this->result->id,
            'evaluation_version' => 1,
            'evaluator_provider' => 'test',
            'evaluator_model' => 'test-model',
            'dimension' => 'relevance',
            'score' => 5,
            'weight' => 2.00,
            'created_by' => $this->user->id,
        ]);

        ResultEvaluation::create([
            'result_id' => $this->result->id,
            'evaluation_version' => 1,
            'evaluator_provider' => 'test',
            'evaluator_model' => 'test-model',
            'dimension' => 'accuracy',
            'score' => 3,
            'weight' => 1.00,
            'created_by' => $this->user->id,
        ]);

        ResultEvaluation::create([
            'result_id' => $this->result->id,
            'evaluation_version' => 1,
            'evaluator_provider' => 'test',
            'evaluator_model' => 'test-model',
            'dimension' => 'clarity',
            'score' => 4,
            'weight' => 1.00,
            'created_by' => $this->user->id,
        ]);

        // Weighted average: (5*2 + 3*1 + 4*1) / (2+1+1) = (10+3+4)/4 = 17/4 = 4.25
        $composite = ResultEvaluation::compositeScore($this->result->id, 1);

        $this->assertEquals(4.25, $composite);
    }

    // ---- Test 10: MCP evaluate_result tool ----

    public function test_mcp_evaluate_result_tool(): void
    {
        $this->enableEvaluation();
        $this->mockLlmDispatch();

        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'tools/call',
            'params' => [
                'name' => 'evaluate_result',
                'arguments' => ['result_id' => $this->result->id],
            ],
        ], $this->headers);

        $response->assertStatus(200);

        $content = $response->json('result.content.0.text');
        $decoded = json_decode($content, true);

        $this->assertArrayHasKey('scores', $decoded);
        $this->assertArrayHasKey('composite_score', $decoded);
        $this->assertNotEmpty($decoded['scores']);
    }

    // ---- Test 11: MCP get_evaluations tool ----

    public function test_mcp_get_evaluations_tool(): void
    {
        $this->enableEvaluation();
        $this->mockLlmDispatch();

        // Create evaluation rows via the evaluate endpoint first
        $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'tools/call',
            'params' => [
                'name' => 'evaluate_result',
                'arguments' => ['result_id' => $this->result->id],
            ],
        ], $this->headers);

        // Now fetch evaluations
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '2',
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_evaluations',
                'arguments' => ['result_id' => $this->result->id],
            ],
        ], $this->headers);

        $response->assertStatus(200);

        $content = $response->json('result.content.0.text');
        $decoded = json_decode($content, true);

        $this->assertArrayHasKey('scores', $decoded);
        $this->assertNotEmpty($decoded['scores']);
    }
}
