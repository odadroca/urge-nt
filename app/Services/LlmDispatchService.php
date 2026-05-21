<?php

namespace App\Services;

use App\Models\LlmProvider;
use App\Services\LlmProviders\AnthropicDriver;
use App\Services\LlmProviders\Contracts\LlmDriverInterface;
use App\Services\LlmProviders\GeminiDriver;
use App\Services\LlmProviders\LlmResult;
use App\Services\LlmProviders\MistralDriver;
use App\Services\LlmProviders\OllamaDriver;
use App\Services\LlmProviders\OpenAiDriver;
use App\Services\LlmProviders\OpenRouterDriver;

class LlmDispatchService
{
    public function dispatch(LlmProvider $provider, string $prompt): LlmResult
    {
        $this->assertPromptSize($prompt);
        $driver = $this->resolveDriver($provider);

        return $driver->complete($prompt);
    }

    public function dispatchWithSystem(LlmProvider $provider, string $systemPrompt, string $userPrompt): LlmResult
    {
        $this->assertPromptSize($systemPrompt.$userPrompt);
        $driver = $this->resolveDriver($provider);

        return $driver->completeWithSystem($systemPrompt, $userPrompt);
    }

    private function assertPromptSize(string $combined): void
    {
        $max = (int) config('urge.max_prompt_bytes', 1024 * 1024); // 1 MiB
        if (strlen($combined) > $max) {
            throw new \RuntimeException(
                "Prompt size exceeds the configured limit ({$max} bytes)."
            );
        }
    }

    private function resolveDriver(LlmProvider $provider): LlmDriverInterface
    {
        $apiKey = $provider->isOllama() ? '' : ($provider->api_key ?? '');

        if (! $provider->isOllama() && trim($apiKey) === '') {
            throw new \RuntimeException("No API key configured for provider: {$provider->name}");
        }

        // Custom endpoints reach the network → SSRF-validate before constructing
        // the driver (LLM-01). For ollama, loopback is the documented default
        // and we require an explicit endpoint (LLM-08).
        $endpoint = $provider->endpoint ?? null;

        if ($provider->driver === 'openai' && $endpoint) {
            UrlSafetyService::assertSafe($endpoint, ['allow_loopback' => false, 'allow_http' => false]);
        }
        if ($provider->driver === 'ollama') {
            if (! $endpoint) {
                throw new \RuntimeException(
                    "Ollama provider '{$provider->name}' requires an explicit endpoint."
                );
            }
            UrlSafetyService::assertSafe($endpoint, ['allow_loopback' => true, 'allow_http' => true]);
        }

        return match ($provider->driver) {
            'openai' => new OpenAiDriver($apiKey, $provider->model, $endpoint),
            'anthropic' => new AnthropicDriver($apiKey, $provider->model),
            'mistral' => new MistralDriver($apiKey, $provider->model),
            'gemini' => new GeminiDriver($apiKey, $provider->model),
            'ollama' => new OllamaDriver($endpoint, $provider->model),
            'openrouter' => new OpenRouterDriver($apiKey, $provider->model),
            default => throw new \InvalidArgumentException("Unknown LLM driver: {$provider->driver}"),
        };
    }
}
