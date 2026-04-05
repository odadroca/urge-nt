<?php

namespace App\Http\Controllers;

use App\Services\McpToolHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpController
{
    public function __construct(private McpToolHandler $handler) {}

    /**
     * POST /api/v1/mcp — Handle JSON-RPC 2.0 requests.
     *
     * If a sessionId query param is present (SSE transport), the response is
     * queued for delivery on the SSE stream and 202 Accepted is returned.
     * Otherwise the JSON-RPC response is returned directly (backwards compat).
     */
    public function handle(Request $request): JsonResponse|Response
    {
        // Rate limiting: 60 requests per minute per user/IP
        $key = 'mcp:' . ($request->user()?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => [
                    'code'    => -32000,
                    'message' => 'Rate limit exceeded. Try again later.',
                ],
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $body = $request->json()->all();

        // Validate JSON-RPC version
        if (!isset($body['jsonrpc']) || $body['jsonrpc'] !== '2.0') {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => $body['id'] ?? null,
                'error'   => [
                    'code'    => -32600,
                    'message' => 'Invalid Request: missing or invalid "jsonrpc" field. Must be "2.0".',
                ],
            ]);
        }

        $method = $body['method'] ?? '';
        $id = $body['id'] ?? null;

        // Notifications (no id) — acknowledge without queueing a response
        if ($id === null && str_starts_with($method, 'notifications/')) {
            $sessionId = $request->query('sessionId');
            if ($sessionId) {
                return response('', 202);
            }
            return response()->json(['jsonrpc' => '2.0', 'id' => null, 'result' => new \stdClass()]);
        }

        $response = $this->processJsonRpc($body, $request);

        // SSE transport: queue response for the SSE stream
        $sessionId = $request->query('sessionId');
        if ($sessionId) {
            $msgId = Cache::increment("mcp_sse:{$sessionId}:counter");
            Cache::put("mcp_sse:{$sessionId}:msg:{$msgId}", $response, 300);

            return response('', 202);
        }

        // Direct transport: return JSON-RPC response inline
        return response()->json($response);
    }

    /**
     * GET /api/v1/mcp — SSE endpoint for MCP streaming.
     *
     * Opens a persistent SSE connection, sends the message endpoint URL
     * (with a unique sessionId), then polls for queued JSON-RPC responses
     * and delivers them as `event: message` SSE events.
     */
    public function stream(Request $request): StreamedResponse
    {
        $sessionId = Str::uuid()->toString();

        return new StreamedResponse(function () use ($sessionId) {
            // Disable output buffering
            while (ob_get_level()) {
                ob_end_flush();
            }

            // Send the message endpoint URL
            $messageUrl = url('/api/v1/mcp') . '?' . http_build_query(['sessionId' => $sessionId]);
            echo "event: endpoint\n";
            echo "data: {$messageUrl}\n\n";
            flush();

            // Poll for queued messages and deliver as SSE events
            $lastSeen = 0;
            $timeout = 300; // 5 minutes
            $start = time();
            $lastPing = time();

            while ((time() - $start) < $timeout) {
                if (connection_aborted()) {
                    break;
                }

                $counter = (int) Cache::get("mcp_sse:{$sessionId}:counter", 0);

                for ($i = $lastSeen + 1; $i <= $counter; $i++) {
                    $cacheKey = "mcp_sse:{$sessionId}:msg:{$i}";
                    $msg = Cache::pull($cacheKey);
                    if ($msg) {
                        echo "event: message\n";
                        echo "data: " . json_encode($msg) . "\n\n";
                        flush();
                    }
                }
                $lastSeen = $counter;

                // Keepalive ping every 15 seconds
                if ((time() - $lastPing) >= 15) {
                    echo ": ping\n\n";
                    flush();
                    $lastPing = time();
                }

                usleep(100000); // 100ms poll interval
            }

            // Cleanup session keys
            Cache::forget("mcp_sse:{$sessionId}:counter");
        }, 200, [
            'Content-Type'       => 'text/event-stream',
            'Cache-Control'      => 'no-cache',
            'Connection'         => 'keep-alive',
            'X-Accel-Buffering'  => 'no',
        ]);
    }

    /**
     * Process a JSON-RPC 2.0 request and return the response envelope.
     */
    private function processJsonRpc(array $body, Request $request): array
    {
        $method = $body['method'] ?? '';
        $id = $body['id'] ?? null;
        $params = $body['params'] ?? [];

        $result = match ($method) {
            'initialize' => [
                'protocolVersion' => '2024-11-05',
                'capabilities'    => [
                    'tools'     => ['listChanged' => false],
                    'resources' => ['subscribe' => false, 'listChanged' => false],
                ],
                'serverInfo' => $this->handler->getServerInfo(),
            ],
            'tools/list' => [
                'tools' => $this->handler->getToolDefinitions(),
            ],
            'tools/call' => $this->handleToolCall($params, $request),
            'resources/list' => [
                'resources' => $this->handler->getResourceDefinitions(),
            ],
            'resources/read' => $this->handleResourceRead($params, $request),
            'ping' => new \stdClass(),
            default => null,
        };

        if ($result === null) {
            return [
                'jsonrpc' => '2.0',
                'id'      => $id,
                'error'   => [
                    'code'    => -32601,
                    'message' => "Method not found: {$method}",
                ],
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ];
    }

    private function handleToolCall(array $params, Request $request): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];
        $user = $request->user();

        $result = $this->handler->callTool($toolName, $arguments, $user);

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

    private function handleResourceRead(array $params, Request $request): array
    {
        $uri = $params['uri'] ?? '';
        $resource = $this->handler->readResource($uri, $request->user());

        if (isset($resource['error'])) {
            return ['contents' => []];
        }

        return [
            'contents' => [$resource],
        ];
    }
}
