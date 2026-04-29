import { useState, useCallback } from 'react';

export default function useCopyToClipboard(resetMs = 1500) {
    const [copied, setCopied] = useState(false);

    const copy = useCallback(async (text) => {
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), resetMs);
        } catch (err) {
            console.error('Copy failed:', err);
        }
    }, [resetMs]);

    return { copied, copy };
}
