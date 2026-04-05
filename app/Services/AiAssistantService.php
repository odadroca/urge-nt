<?php

namespace App\Services;

use App\Models\LlmProvider;
use App\Services\LlmProviders\LlmResult;

class AiAssistantService
{
    public function __construct(private LlmDispatchService $dispatchService) {}

    public function summarizeDifferences(string $textA, string $textB, LlmProvider $provider): LlmResult
    {
        $systemPrompt = 'You are an expert text comparison analyst. Compare the two texts and provide a concise summary of the key differences. Focus on structural changes, content additions/removals, and tone shifts. Be specific and brief.';

        $userPrompt = "TEXT A:\n---\n{$textA}\n---\n\nTEXT B:\n---\n{$textB}\n---\n\nProvide a concise summary of the differences.";

        return $this->dispatchService->dispatchWithSystem($provider, $systemPrompt, $userPrompt);
    }

    public function suggestImprovements(string $promptContent, LlmProvider $provider): LlmResult
    {
        $systemPrompt = 'You are an expert prompt engineer. Analyze the given prompt and suggest specific improvements for clarity, specificity, and effectiveness. Consider: instruction clarity, variable usage, structure, potential edge cases, and output format guidance. Provide actionable suggestions.';

        $userPrompt = "PROMPT TO ANALYZE:\n---\n{$promptContent}\n---\n\nProvide specific improvement suggestions.";

        return $this->dispatchService->dispatchWithSystem($provider, $systemPrompt, $userPrompt);
    }
}
