<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\McpToolHandler;
use Illuminate\Console\Command;

class McpServerCommand extends Command
{
    protected $signature = 'urge:mcp-server {--user= : User ID for authenticated operations}';

    protected $description = 'Start the MCP stdio server (reads JSON-RPC from STDIN, writes to STDOUT)';

    public function handle(McpToolHandler $handler): int
    {
        $userId = $this->option('user');
        $user = $userId ? User::find($userId) : User::first();

        $this->writeStderr('URGE MCP Server started (stdio transport). Waiting for JSON-RPC input...');

        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $request = json_decode($line, true);
            if (! $request || ! isset($request['method'])) {
                $this->writeJsonRpc(null, null, [
                    'code' => -32700,
                    'message' => 'Parse error',
                ]);

                continue;
            }

            $method = $request['method'];
            $id = $request['id'] ?? null;
            $params = $request['params'] ?? [];

            // Notifications (no id) don't get responses
            if ($id === null && in_array($method, ['notifications/initialized', 'notifications/cancelled'])) {
                continue;
            }

            $result = match ($method) {
                'initialize' => [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => [
                        'tools' => ['listChanged' => false],
                        'resources' => ['subscribe' => false, 'listChanged' => false],
                    ],
                    'serverInfo' => $handler->getServerInfo(),
                ],
                'tools/list' => [
                    'tools' => $handler->getToolDefinitions(),
                ],
                'tools/call' => $this->handleToolCall($handler, $params, $user),
                'resources/list' => [
                    'resources' => $handler->getResourceDefinitions(),
                ],
                'resources/read' => $this->handleResourceRead($handler, $params, $user),
                'ping' => new \stdClass,
                default => null,
            };

            if ($result === null) {
                $this->writeJsonRpc($id, null, [
                    'code' => -32601,
                    'message' => "Method not found: {$method}",
                ]);

                continue;
            }

            $this->writeJsonRpc($id, $result);
        }

        return 0;
    }

    private function handleToolCall(McpToolHandler $handler, array $params, ?User $user): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        $result = $handler->callTool($toolName, $arguments, $user);

        if (isset($result['error'])) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => $result['error']],
                ],
                'isError' => true,
            ];
        }

        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT)],
            ],
        ];
    }

    private function handleResourceRead(McpToolHandler $handler, array $params, ?User $user): array
    {
        $uri = $params['uri'] ?? '';
        $resource = $handler->readResource($uri, $user);

        if (isset($resource['error'])) {
            return ['contents' => []];
        }

        return ['contents' => [$resource]];
    }

    private function writeJsonRpc(?string $id, mixed $result = null, ?array $error = null): void
    {
        $response = ['jsonrpc' => '2.0', 'id' => $id];

        if ($error) {
            $response['error'] = $error;
        } else {
            $response['result'] = $result;
        }

        fwrite(STDOUT, json_encode($response)."\n");
        fflush(STDOUT);
    }

    private function writeStderr(string $message): void
    {
        fwrite(STDERR, $message."\n");
    }
}
