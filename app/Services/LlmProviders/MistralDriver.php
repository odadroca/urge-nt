<?php

namespace App\Services\LlmProviders;

use App\Services\LlmProviders\Contracts\LlmDriverInterface;
use Illuminate\Support\Facades\Http;

class MistralDriver implements LlmDriverInterface
{
    private const BASE_URL = 'https://api.mistral.ai';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
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
        $start = hrtime(true);

        try {
            $response = Http::withToken($this->apiKey)
                ->withOptions(['verify' => config('urge.curl_ssl_verify', true), 'allow_redirects' => false])
                ->timeout(120)
                ->post(self::BASE_URL.'/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                ]);

            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($response->failed()) {
                $msg = $response->json('message');
                $error = is_string($msg) && $msg !== ''
                    ? DriverErrorSanitizer::trim($msg)
                    : 'Mistral request failed.';

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

            return LlmResult::failure(DriverErrorSanitizer::generic($e), $this->model, $durationMs);
        }
    }
}
