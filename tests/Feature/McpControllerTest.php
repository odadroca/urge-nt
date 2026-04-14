<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('result.protocolVersion', '2025-06-18')
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
            ->assertJsonPath('result.tools.0.name', 'create_prompt');
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

        $response->assertStatus(401)
            ->assertHeader('WWW-Authenticate');
    }

    // --- Streamable HTTP transport ---

    public function test_initialize_returns_session_id_header(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'initialize',
            'params' => [],
        ], $this->headers);

        $response->assertStatus(200)
            ->assertHeader('Mcp-Session-Id');
    }

    public function test_delete_terminates_session(): void
    {
        // First initialize to get a session
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'initialize',
            'params' => [],
        ], $this->headers);

        $sessionId = $response->headers->get('Mcp-Session-Id');

        // Delete session
        $deleteResponse = $this->delete('/api/v1/mcp', [], array_merge($this->headers, [
            'Mcp-Session-Id' => $sessionId,
        ]));

        $deleteResponse->assertStatus(204);
    }

    public function test_get_returns_405(): void
    {
        $response = $this->get('/api/v1/mcp', $this->headers);

        $response->assertStatus(405);
    }
}
