<?php

namespace App\Services\LlmProviders;

class LlmResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $text,
        public readonly ?string $modelUsed,
        public readonly ?int $inputTokens,
        public readonly ?int $outputTokens,
        public readonly int $durationMs,
        public readonly ?string $error = null,
    ) {}

    public static function success(
        string $text,
        string $modelUsed,
        int $durationMs,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
    ): self {
        return new self(
            success: true,
            text: $text,
            modelUsed: $modelUsed,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            durationMs: $durationMs,
        );
    }

    public static function failure(string $error, string $modelUsed, int $durationMs): self
    {
        return new self(
            success: false,
            text: null,
            modelUsed: $modelUsed,
            inputTokens: null,
            outputTokens: null,
            durationMs: $durationMs,
            error: $error,
        );
    }
}
