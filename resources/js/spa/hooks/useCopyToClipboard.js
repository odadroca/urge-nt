import { useCallback } from 'react';
import useTransientFlag from './useTransientFlag.js';

export default function useCopyToClipboard(resetMs = 1500) {
    const { flag: copied, trigger: flashCopied } = useTransientFlag(resetMs);

    const copy = useCallback(async (text) => {
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            flashCopied();
        } catch (err) {
            console.error('Copy failed:', err);
        }
    }, [flashCopied]);

    return { copied, copy };
}
