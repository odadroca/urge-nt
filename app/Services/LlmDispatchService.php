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
        $driver = $this->resolveDriver($provider);
        return $driver->complete($prompt);
    }

    public function dispatchWithSystem(LlmProvider $provider, string $systemPrompt, string $userPrompt): LlmResult
    {
        $driver = $this->resolveDriver($provider);
        return $driver->completeWithSystem($systemPrompt, $userPrompt);
    }

    private function resolveDriver(LlmProvider $provider): LlmDriverInterface
    {
        $apiKey = $provider->isOllama() ? '' : ($provider->api_key ?? '');

        if (!$provider->isOllama() && trim($apiKey) === '') {
            throw new \RuntimeException("No API key configured for provider: {$provider->name}");
        }

        return match ($provider->driver) {
            'openai'     => new OpenAiDriver($apiKey, $provider->model, $provider->endpoint),
            'anthropic'  => new AnthropicDriver($apiKey, $provider->model),
            'mistral'    => new MistralDriver($apiKey, $provider->model),
            'gemini'     => new GeminiDriver($apiKey, $provider->model),
            'ollama'     => new OllamaDriver($provider->endpoint ?? 'http://localhost:11434', $provider->model),
            'openrouter' => new OpenRouterDriver($apiKey, $provider->model),
            default      => throw new \InvalidArgumentException("Unknown LLM driver: {$provider->driver}"),
        };
    }
}
