import { useState, useCallback, useEffect, useRef } from 'react';

/**
 * Manage a transient boolean flag that auto-clears after a timeout.
 *
 * Each call to `trigger()` sets the flag to true and (re)starts the timer
 * — so consecutive triggers stack the timeout from the latest call, not
 * from the first. Mount/unmount cleanup is handled.
 *
 * Use for "Saved.", "Copied!", "Sent." style ephemeral confirmations
 * where naive `setSaved(true) + setTimeout(() => setSaved(false), ms)`
 * has the bug that a second call within the timeout window doesn't
 * reset the timer (because `setSaved(true)` is a no-op when already true,
 * so the cleanup-aware effect doesn't re-run).
 */
export default function useTransientFlag(timeoutMs = 2500) {
    const [flag, setFlag] = useState(false);
    const timerRef = useRef(null);

    useEffect(() => () => {
        if (timerRef.current) clearTimeout(timerRef.current);
    }, []);

    const trigger = useCallback(() => {
        setFlag(true);
        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => {
            setFlag(false);
            timerRef.current = null;
        }, timeoutMs);
    }, [timeoutMs]);

    return { flag, trigger };
}
