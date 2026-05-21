<?php

namespace App\Services\LlmProviders;

use App\Services\LlmProviders\Contracts\LlmDriverInterface;
use Illuminate\Support\Facades\Http;

class GeminiDriver implements LlmDriverInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function complete(string $prompt): LlmResult
    {
        return $this->send(null, $prompt);
    }

    public function completeWithSystem(string $systemPrompt, string $userPrompt): LlmResult
    {
        return $this->send($systemPrompt, $userPrompt);
    }

    private function send(?string $system, string $userPrompt): LlmResult
    {
        $url = self::BASE_URL."/v1beta/models/{$this->model}:generateContent";
        $start = hrtime(true);

        try {
            $payload = [
                'contents' => [
                    ['parts' => [['text' => $userPrompt]]],
                ],
            ];

            if ($system !== null) {
                $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
            }

            // LLM-02: pass the API key via x-goog-api-key (was: ?key=… in
            // the URL — leaked via exception messages on transport errors).
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->withOptions(['verify' => config('urge.curl_ssl_verify', true), 'allow_redirects' => false])
                ->timeout(120)
                ->post($url, $payload);

            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($response->failed()) {
                return LlmResult::failure($this->safeError($response), $this->model, $durationMs);
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return LlmResult::success(
                text: $text,
                modelUsed: $this->model,
                durationMs: $durationMs,
                inputTokens: $data['usageMetadata']['promptTokenCount'] ?? null,
                outputTokens: $data['usageMetadata']['candidatesTokenCount'] ?? null,
            );
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            return LlmResult::failure(DriverErrorSanitizer::generic($e), $this->model, $durationMs);
        }
    }

    private function safeError($response): string
    {
        $msg = $response->json('error.message');
        if (is_string($msg) && $msg !== '') {
            return DriverErrorSanitizer::trim($msg);
        }

        return 'Gemini request failed.';
    }
}
