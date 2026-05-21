<?php

namespace App\Services\LlmProviders;

use App\Services\LlmProviders\Contracts\LlmDriverInterface;
use Illuminate\Support\Facades\Http;

class OllamaDriver implements LlmDriverInterface
{
    public function __construct(
        private readonly string $baseUrl,
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
        $base = rtrim($this->baseUrl, '/');
        $start = hrtime(true);

        try {
            $response = Http::withOptions(['verify' => config('urge.curl_ssl_verify', true), 'allow_redirects' => false])
                ->timeout(300)
                ->post("{$base}/api/chat", [
                    'model'    => $this->model,
                    'messages' => $messages,
                    'stream'   => false,
                ]);

            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($response->failed()) {
                $msg = $response->json('error');
                $error = is_string($msg) && $msg !== ''
                    ? DriverErrorSanitizer::trim($msg)
                    : 'Ollama request failed.';
                return LlmResult::failure($error, $this->model, $durationMs);
            }

            $data = $response->json();
            $text = $data['message']['content'] ?? '';

            return LlmResult::success(
                text: $text,
                modelUsed: $data['model'] ?? $this->model,
                durationMs: $durationMs,
                inputTokens: $data['prompt_eval_count'] ?? null,
                outputTokens: $data['eval_count'] ?? null,
            );
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
            return LlmResult::failure(DriverErrorSanitizer::generic($e), $this->model, $durationMs);
        }
    }
}
