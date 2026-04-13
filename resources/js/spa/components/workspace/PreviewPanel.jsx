import { useState, useEffect, useCallback } from 'react';
import { renderPrompt } from '../../api/render.js';
import useDebounce from '../../hooks/useDebounce.js';

export default function PreviewPanel({ username, slug, versionNumber, variables = [] }) {
    const [variableValues, setVariableValues] = useState({});
    const [rendered, setRendered] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    // Reset variable values when variables change
    useEffect(() => {
        setVariableValues(prev => {
            const next = {};
            variables.forEach(v => { next[v] = prev[v] || ''; });
            return next;
        });
    }, [variables.join(',')]);

    const doRender = useCallback(async () => {
        if (!versionNumber) return;
        setLoading(true);
        setError(null);
        try {
            const result = await renderPrompt(username, slug, {
                version: versionNumber,
                variables: variableValues,
            });
            setRendered(result.data?.rendered || result.rendered || '');
        } catch (err) {
            setError(err.response?.data?.error || 'Render failed');
        } finally {
            setLoading(false);
        }
    }, [username, slug, versionNumber, variableValues]);

    const debouncedRender = useDebounce(doRender, 500);

    // Re-render when variables change
    useEffect(() => {
        debouncedRender();
    }, [variableValues, versionNumber]);

    // Initial render
    useEffect(() => {
        doRender();
    }, [versionNumber]);

    return (
        <div className="flex flex-col h-full border-t border-gray-700">
            {/* Variable fill form */}
            {variables.length > 0 && (
                <div className="p-3 border-b border-gray-700 space-y-2">
                    <span className="text-[10px] text-gray-500 uppercase tracking-wider">Variables</span>
                    <div className="grid grid-cols-2 gap-2">
                        {variables.map(v => (
                            <div key={v}>
                                <label className="text-[10px] text-gray-400 block mb-0.5">{`{{${v}}}`}</label>
                                <input
                                    value={variableValues[v] || ''}
                                    onChange={(e) => setVariableValues(prev => ({ ...prev, [v]: e.target.value }))}
                                    placeholder={v}
                                    className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1 outline-none focus:border-indigo-500"
                                />
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Rendered output */}
            <div className="flex-1 overflow-y-auto p-3">
                {loading && (
                    <div className="flex justify-center py-4">
                        <div className="animate-spin h-4 w-4 border-2 border-indigo-500 border-t-transparent rounded-full" />
                    </div>
                )}
                {error && (
                    <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-2 py-1 rounded">{error}</div>
                )}
                {!loading && !error && (
                    <pre className="text-xs text-gray-300 whitespace-pre-wrap font-mono leading-relaxed">{rendered}</pre>
                )}
            </div>
        </div>
    );
}
