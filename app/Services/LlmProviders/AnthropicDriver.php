<?php

namespace App\Services\LlmProviders;

use App\Services\LlmProviders\Contracts\LlmDriverInterface;
use Illuminate\Support\Facades\Http;

class AnthropicDriver implements LlmDriverInterface
{
    private const BASE_URL = 'https://api.anthropic.com';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function complete(string $prompt): LlmResult
    {
        return $this->send(null, [['role' => 'user', 'content' => $prompt]]);
    }

    public function completeWithSystem(string $systemPrompt, string $userPrompt): LlmResult
    {
        return $this->send($systemPrompt, [['role' => 'user', 'content' => $userPrompt]]);
    }

    private function send(?string $system, array $messages): LlmResult
    {
        $start = hrtime(true);

        try {
            $payload = [
                'model'      => $this->model,
                'max_tokens' => 4096,
                'messages'   => $messages,
            ];

            if ($system !== null) {
                $payload['system'] = $system;
            }

            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
                ->withOptions(['verify' => config('urge.curl_ssl_verify', true)])
                ->timeout(120)
                ->post(self::BASE_URL . '/v1/messages', $payload);

            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? $response->body();
                return LlmResult::failure($error, $this->model, $durationMs);
            }

            $data = $response->json();
            return LlmResult::success(
                text: $data['content'][0]['text'] ?? '',
                modelUsed: $data['model'] ?? $this->model,
                durationMs: $durationMs,
                inputTokens: $data['usage']['input_tokens'] ?? null,
                outputTokens: $data['usage']['output_tokens'] ?? null,
            );
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
            return LlmResult::failure($e->getMessage(), $this->model, $durationMs);
        }
    }
}
