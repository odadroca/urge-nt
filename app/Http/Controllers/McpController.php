<?php

namespace App\Http\Controllers;

use App\Services\ApiKeyService;
use App\Services\McpToolHandler;
use App\Services\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class McpController
{
    public function __construct(private McpToolHandler $handler) {}

    /**
     * POST /api/v1/mcp — Streamable HTTP transport.
     */
    public function handle(Request $request): JsonResponse|Response
    {
        // Validate Origin header
        $origin = $request->header('Origin');
        if ($origin && !$this->isAllowedOrigin($origin)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32000, 'message' => 'Origin not allowed.'],
            ], 403);
        }

        // Attempt authentication (non-aborting — handles OAuth 401 discovery)
        $this->resolveAuth($request);

        if (!$request->user()) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32000, 'message' => 'Authentication required.'],
            ], 401)->withHeaders([
                'WWW-Authenticate' => 'Bearer resource_metadata="' . url('/.well-known/oauth-protected-resource') . '"',
            ]);
        }

        // Rate limiting
        $key = 'mcp:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32000, 'message' => 'Rate limit exceeded.'],
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $body = $request->json()->all();

        if (!isset($body['jsonrpc']) || $body['jsonrpc'] !== '2.0') {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => $body['id'] ?? null,
                'error'   => ['code' => -32600, 'message' => 'Invalid Request: jsonrpc must be "2.0".'],
            ]);
        }

        $method = $body['method'] ?? '';
        $id = $body['id'] ?? null;

        // Notifications — acknowledge without response
        if ($id === null && str_starts_with($method, 'notifications/')) {
            return response('', 204);
        }

        // Validate session for non-initialize requests
        $sessionId = $request->header('Mcp-Session-Id');
        if ($method !== 'initialize' && $sessionId) {
            if (!Cache::has("mcp_session:{$sessionId}")) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id'      => $id,
                    'error'   => ['code' => -32000, 'message' => 'Invalid or expired session.'],
                ], 400);
            }
            Cache::put("mcp_session:{$sessionId}", $request->user()->id, 3600);
        }

        $response = $this->processJsonRpc($body, $request);

        $headers = ['Content-Type' => 'application/json'];

        // On initialize, create session
        if ($method === 'initialize') {
            $newSessionId = Str::uuid()->toString();
            Cache::put("mcp_session:{$newSessionId}", $request->user()->id, 3600);
            $headers['Mcp-Session-Id'] = $newSessionId;
        }

        return response()->json($response)->withHeaders($headers);
    }

    /**
     * GET /api/v1/mcp — Server-initiated messages (not implemented).
     */
    public function stream(): Response
    {
        return response('', 405)->withHeaders([
            'Allow' => 'POST, DELETE',
        ]);
    }

    /**
     * DELETE /api/v1/mcp — Terminate session.
     */
    public function destroy(Request $request): Response
    {
        $sessionId = $request->header('Mcp-Session-Id');
        if ($sessionId) {
            Cache::forget("mcp_session:{$sessionId}");
        }

        return response('', 204);
    }

    /**
     * Non-aborting auth resolution — try Sanctum, OAuth, API key.
     */
    private function resolveAuth(Request $request): void
    {
        if ($request->user()) {
            return;
        }

        $bearer = $request->bearerToken();
        if (!$bearer) {
            return;
        }

        // OAuth token (non-prefixed)
        if (!str_starts_with($bearer, config('urge.key_prefix', 'urge_'))) {
            $oauthToken = app(OAuthService::class)->findByToken($bearer);
            if ($oauthToken) {
                $request->setUserResolver(fn () => $oauthToken->user);
                $request->attributes->set('oauth_token', $oauthToken);
                return;
            }
        }

        // API key (urge_ prefixed)
        $apiKey = app(ApiKeyService::class)->findByToken($bearer);
        if ($apiKey && $apiKey->is_active) {
            $apiKey->update(['last_used_at' => now()]);
            $request->setUserResolver(fn () => $apiKey->user);
            $request->attributes->set('api_key', $apiKey);
        }
    }

    private function processJsonRpc(array $body, Request $request): array
    {
        $method = $body['method'] ?? '';
        $id = $body['id'] ?? null;
        $params = $body['params'] ?? [];

        $result = match ($method) {
            'initialize' => [
                'protocolVersion' => '2025-06-18',
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
                'error'   => ['code' => -32601, 'message' => "Method not found: {$method}"],
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

        // Scope enforcement for OAuth tokens
        $oauthToken = $request->attributes->get('oauth_token');
        if ($oauthToken) {
            $requiredScope = $this->handler->getRequiredScope($toolName);
            if ($requiredScope && !$oauthToken->hasScope($requiredScope)) {
                return [
                    'content' => [
                        ['type' => 'text', 'text' => "Insufficient scope. Required: {$requiredScope}"],
                    ],
                    'isError' => true,
                ];
            }
        }

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

        return ['contents' => [$resource]];
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $appUrl = config('app.url');
        if (str_starts_with($origin, $appUrl)) {
            return true;
        }
        if (str_starts_with($origin, 'http://localhost') || str_starts_with($origin, 'http://127.0.0.1')) {
            return true;
        }
        // Allow known MCP client origins (Claude.ai, Claude Desktop)
        $allowedOrigins = [
            'https://claude.ai',
            'https://www.claude.ai',
            'chrome-extension://',
        ];
        foreach ($allowedOrigins as $allowed) {
            if (str_starts_with($origin, $allowed)) {
                return true;
            }
        }
        // Allow any HTTPS origin — MCP clients authenticate via OAuth/Bearer tokens,
        // so Origin is a secondary check. Auth is the real gate.
        if (str_starts_with($origin, 'https://')) {
            return true;
        }
        return false;
    }
}
