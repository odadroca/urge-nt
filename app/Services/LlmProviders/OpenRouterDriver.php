<?php

namespace App\Services\LlmProviders;

use App\Services\LlmProviders\Contracts\LlmDriverInterface;

class OpenRouterDriver implements LlmDriverInterface
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

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
            $payload = json_encode([
                'model'    => $this->model,
                'messages' => $messages,
            ]);

            $ch = curl_init(self::ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_SSL_VERIFYPEER => config('urge.curl_ssl_verify', true),
                CURLOPT_SSL_VERIFYHOST => config('urge.curl_ssl_verify', true) ? 2 : 0,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                    'HTTP-Referer: ' . config('app.url', 'http://localhost'),
                    'X-Title: ' . config('app.name', 'URGE'),
                ],
                CURLOPT_POSTFIELDS => $payload,
            ]);

            $rawResponse = curl_exec($ch);
            $curlError   = curl_error($ch);
            $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($rawResponse === false) {
                return LlmResult::failure($curlError ?: 'cURL request failed', $this->model, $durationMs);
            }

            $data = json_decode($rawResponse, true);

            if ($httpCode >= 400) {
                $error = $data['error']['message'] ?? $rawResponse;
                return LlmResult::failure($error, $this->model, $durationMs);
            }

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
