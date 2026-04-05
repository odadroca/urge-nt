<?php

namespace App\Services\LlmProviders;

use App\Services\LlmProviders\Contracts\LlmDriverInterface;
use Illuminate\Support\Facades\Http;

class OpenAiDriver implements LlmDriverInterface
{
    private const BASE_URL = 'https://api.openai.com';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $baseUrl = null,
    ) {}

    public function complete(string $prompt): LlmResult
    {
        return $this->send([['role' => 'user', 'content' => $prompt]]);
    }

    public function completeWithSystem(string $systemPrompt, string $userPrompt): LlmResult
    {
        return $this->send([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ]);
    }

    private function send(array $messages): LlmResult
    {
        $base = rtrim($this->baseUrl ?? self::BASE_URL, '/');
        $start = hrtime(true);

        try {
            $response = Http::withToken($this->apiKey)
                ->withOptions(['verify' => config('urge.curl_ssl_verify', true)])
                ->timeout(120)
                ->post("{$base}/v1/chat/completions", [
                    'model'    => $this->model,
                    'messages' => $messages,
                ]);

            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? $response->body();
                return LlmResult::failure($error, $this->model, $durationMs);
            }

            $data = $response->json();
            return LlmResult::success(
                text: $data['choices'][0]['message']['content'] ?? '',
                modelUsed: $data['model'] ?? $this->model,
                durationMs: $durationMs,
                inputTokens: $data['usage']['prompt_tokens'] ?? null,
                outputTokens: $data['usage']['completion_tokens'] ?? null,
            );
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
            return LlmResult::failure($e->getMessage(), $this->model, $durationMs);
        }
    }
}
