<?php

namespace App\Services\LlmProviders;

/**
 * LLM-02 / LLM-03 / LLM-04: redact URLs and credentials from any
 * provider error text we surface back to clients (and persist into
 * Result.error_message). The pre-PB-4 drivers echoed
 * $response->body() / $e->getMessage() verbatim, which made any
 * upstream that reflected request headers an instant credential
 * exfil channel.
 */
class DriverErrorSanitizer
{
    private const MAX_BYTES = 1024;

    /**
     * Trim an upstream error message to a safe size and strip URLs.
     */
    public static function trim(string $msg): string
    {
        // Strip URLs (covers Gemini ?key=… URLs and any other token-bearing
        // links upstream might echo).
        $msg = preg_replace('#https?://\S+#i', '[url]', $msg);

        // Strip obvious bearer-token-shaped strings
        $msg = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $msg);
        $msg = preg_replace('/sk-[A-Za-z0-9_\-]{8,}/', '[redacted-key]', $msg);
        $msg = preg_replace('/AIza[0-9A-Za-z_\-]{20,}/', '[redacted-key]', $msg); // Google API keys

        if (strlen($msg) > self::MAX_BYTES) {
            $msg = substr($msg, 0, self::MAX_BYTES).'… [truncated]';
        }

        return $msg;
    }

    /**
     * Generic message for a transport-layer exception. Never echo
     * $e->getMessage() into Result.error_message — many cURL/TLS
     * errors include the full request URL (with credentials, for
     * the Gemini case until LLM-02 lands).
     */
    public static function generic(\Throwable $e): string
    {
        // Class name only — no message, no URL leak.
        return 'LLM transport error: '.class_basename($e);
    }
}
