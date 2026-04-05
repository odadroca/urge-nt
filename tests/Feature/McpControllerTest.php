<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class McpControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private array $headers;
    private Prompt $prompt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $result = app(ApiKeyService::class)->generateKey($this->user, 'MCP Key');
        $this->headers = ['Authorization' => "Bearer {$result['key']}"];

        $this->prompt = Prompt::create(['name' => 'MCP Controller Test', 'type' => 'prompt', 'created_by' => $this->user->id]);
        app(VersioningService::class)->createVersion($this->prompt, [
            'content' => 'Hello {{name}}!',
        ], $this->user);
    }

    // --- Direct transport (no sessionId) ---

    public function test_initialize(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'initialize',
            'params' => [],
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonPath('id', '1')
            ->assertJsonPath('result.protocolVersion', '2024-11-05')
            ->assertJsonPath('result.serverInfo.name', 'urge');
    }

    public function test_tools_list(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '2',
            'method' => 'tools/list',
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('result.tools.0.name', 'get_prompt');
    }

    public function test_tools_call(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '3',
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_prompt',
                'arguments' => ['slug' => $this->prompt->slug],
            ],
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('id', '3');

        $content = $response->json('result.content.0.text');
        $decoded = json_decode($content, true);
        $this->assertEquals($this->prompt->slug, $decoded['slug']);
    }

    public function test_tools_call_render(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '4',
            'method' => 'tools/call',
            'params' => [
                'name' => 'render_prompt',
                'arguments' => [
                    'slug' => $this->prompt->slug,
                    'variables' => ['name' => 'World'],
                ],
            ],
        ], $this->headers);

        $response->assertStatus(200);
        $content = $response->json('result.content.0.text');
        $decoded = json_decode($content, true);
        $this->assertEquals('Hello World!', $decoded['rendered']);
    }

    public function test_resources_list(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '5',
            'method' => 'resources/list',
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonStructure(['result' => ['resources']]);
    }

    public function test_resources_read(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '6',
            'method' => 'resources/read',
            'params' => ['uri' => "urge://prompts/{$this->prompt->slug}"],
        ], $this->headers);

        $response->assertStatus(200);
        $contents = $response->json('result.contents');
        $this->assertNotEmpty($contents);
        $this->assertEquals('Hello {{name}}!', $contents[0]['text']);
    }

    public function test_unknown_method(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '7',
            'method' => 'unknown/method',
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('error.code', -32601);
    }

    public function test_ping(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '8',
            'method' => 'ping',
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('id', '8');
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'initialize',
        ]);

        $response->assertStatus(401);
    }

    // --- SSE transport (with sessionId) ---

    public function test_sse_post_with_session_id_returns_202_and_queues_response(): void
    {
        $sessionId = 'test-session-' . uniqid();

        $response = $this->postJson('/api/v1/mcp?sessionId=' . $sessionId, [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'initialize',
            'params' => [],
        ], $this->headers);

        $response->assertStatus(202);

        // Verify the response was queued in cache
        $counter = Cache::get("mcp_sse:{$sessionId}:counter");
        $this->assertEquals(1, $counter);

        $queued = Cache::get("mcp_sse:{$sessionId}:msg:1");
        $this->assertNotNull($queued);
        $this->assertEquals('2.0', $queued['jsonrpc']);
        $this->assertEquals('1', $queued['id']);
        $this->assertEquals('2024-11-05', $queued['result']['protocolVersion']);
        $this->assertEquals('urge', $queued['result']['serverInfo']['name']);
    }

    public function test_sse_tools_list_queued(): void
    {
        $sessionId = 'test-session-' . uniqid();

        $response = $this->postJson('/api/v1/mcp?sessionId=' . $sessionId, [
            'jsonrpc' => '2.0',
            'id' => '2',
            'method' => 'tools/list',
        ], $this->headers);

        $response->assertStatus(202);

        $queued = Cache::get("mcp_sse:{$sessionId}:msg:1");
        $this->assertNotNull($queued);
        $this->assertCount(15, $queued['result']['tools']);
        $this->assertEquals('get_prompt', $queued['result']['tools'][0]['name']);
    }

    public function test_sse_notification_returns_202_without_queueing(): void
    {
        $sessionId = 'test-session-' . uniqid();

        $response = $this->postJson('/api/v1/mcp?sessionId=' . $sessionId, [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], $this->headers);

        $response->assertStatus(202);

        // No message should be queued
        $counter = Cache::get("mcp_sse:{$sessionId}:counter");
        $this->assertNull($counter);
    }

    public function test_sse_multiple_messages_queued_sequentially(): void
    {
        $sessionId = 'test-session-' . uniqid();

        // Send initialize
        $this->postJson('/api/v1/mcp?sessionId=' . $sessionId, [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'initialize',
            'params' => [],
        ], $this->headers)->assertStatus(202);

        // Send tools/list
        $this->postJson('/api/v1/mcp?sessionId=' . $sessionId, [
            'jsonrpc' => '2.0',
            'id' => '2',
            'method' => 'tools/list',
        ], $this->headers)->assertStatus(202);

        // Verify both queued
        $this->assertEquals(2, Cache::get("mcp_sse:{$sessionId}:counter"));

        $msg1 = Cache::get("mcp_sse:{$sessionId}:msg:1");
        $this->assertEquals('1', $msg1['id']);
        $this->assertArrayHasKey('protocolVersion', $msg1['result']);

        $msg2 = Cache::get("mcp_sse:{$sessionId}:msg:2");
        $this->assertEquals('2', $msg2['id']);
        $this->assertArrayHasKey('tools', $msg2['result']);
    }

    public function test_sse_stream_returns_event_stream_content_type(): void
    {
        $response = $this->get('/api/v1/mcp', $this->headers);

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'text/event-stream',
            $response->headers->get('Content-Type')
        );
    }
}
