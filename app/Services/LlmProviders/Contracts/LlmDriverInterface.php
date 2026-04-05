<?php

namespace App\Services\LlmProviders\Contracts;

use App\Services\LlmProviders\LlmResult;

interface LlmDriverInterface
{
    public function complete(string $prompt): LlmResult;

    public function completeWithSystem(string $systemPrompt, string $userPrompt): LlmResult;
}
