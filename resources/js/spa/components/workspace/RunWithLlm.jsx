import { useState, useEffect, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listProviders } from '../../api/providers.js';
import { runPrompt } from '../../api/prompts.js';

export default function RunWithLlm({ prompt, version, username, slug, onClose }) {
    const queryClient = useQueryClient();
    const [selectedProviderIds, setSelectedProviderIds] = useState([]);
    const [variableValues, setVariableValues] = useState({});
    const [isRunning, setIsRunning] = useState(false);
    const [error, setError] = useState(null);
    const [lastRunCount, setLastRunCount] = useState(null);

    const { data: providersData, isLoading: loadingProviders } = useQuery({
        queryKey: ['providers'],
        queryFn: listProviders,
    });

    const providers = providersData?.data ?? [];
    const variables = version?.variables ?? [];
    const variableMetadata = version?.variable_metadata ?? {};

    // Reset variable values when version changes
    useEffect(() => {
        setVariableValues(prev => {
            const next = {};
            variables.forEach(v => {
                next[v] = prev[v] || variableMetadata[v]?.default || '';
            });
            return next;
        });
    }, [version?.id]);

    const handleToggleProvider = useCallback((id) => {
        setSelectedProviderIds(prev =>
            prev.includes(id) ? prev.filter(p => p !== id) : [...prev, id]
        );
    }, []);

    const handleRun = useCallback(async () => {
        if (isRunning || selectedProviderIds.length === 0) return;
        setIsRunning(true);
        setError(null);
        setLastRunCount(null);
        try {
            const result = await runPrompt(username, slug, {
                version_number: version?.version_number,
                provider_ids: selectedProviderIds,
                variables: Object.keys(variableValues).length > 0 ? variableValues : undefined,
            });
            const count = result.data?.length ?? 0;
            setLastRunCount(count);
            queryClient.invalidateQueries({ queryKey: ['workspace', username, slug, 'results'] });
        } catch (err) {
            setError(err.response?.data?.error || err.response?.data?.message || 'Run failed');
        } finally {
            setIsRunning(false);
        }
    }, [isRunning, selectedProviderIds, variableValues, username, slug, version, queryClient]);

    // Ctrl+Enter shortcut
    useEffect(() => {
        const handler = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                handleRun();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [handleRun]);

    return (
        <div className="border-t border-gray-700 p-3 space-y-3">
            <div className="flex items-center justify-between">
                <h4 className="text-xs font-semibold text-gray-300 uppercase tracking-wide">Run with LLM</h4>
                <button onClick={onClose} className="text-xs text-gray-500 hover:text-gray-300">&times;</button>
            </div>

            {/* Providers */}
            {loadingProviders ? (
                <div className="flex justify-center py-2">
                    <div className="animate-spin h-4 w-4 border-2 border-indigo-500 border-t-transparent rounded-full" />
                </div>
            ) : providers.length === 0 ? (
                <p className="text-xs text-gray-500">
                    No active LLM providers.{' '}
                    <a href="/settings" className="text-indigo-400 hover:underline">Configure in Settings</a>.
                </p>
            ) : (
                <>
                    <div className="space-y-1">
                        <label className="block text-xs font-medium text-gray-400">Providers</label>
                        {providers.map(p => (
                            <label key={p.id} className="flex items-center gap-2 text-xs text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={selectedProviderIds.includes(p.id)}
                                    onChange={() => handleToggleProvider(p.id)}
                                    className="rounded border-gray-600 bg-gray-900 text-indigo-600 focus:ring-indigo-500"
                                />
                                {p.name} <span className="text-gray-500">({p.model})</span>
                            </label>
                        ))}
                    </div>

                    {/* Variables */}
                    {variables.length > 0 && (
                        <div className="space-y-2">
                            <label className="block text-xs font-medium text-gray-400">Variables</label>
                            {variables.map(v => (
                                <div key={v}>
                                    <label className="block text-xs text-gray-500 mb-0.5">
                                        {v}
                                        {variableMetadata[v]?.description && (
                                            <span className="text-gray-600"> &mdash; {variableMetadata[v].description}</span>
                                        )}
                                    </label>
                                    <input
                                        value={variableValues[v] || ''}
                                        onChange={(e) => setVariableValues(prev => ({ ...prev, [v]: e.target.value }))}
                                        placeholder={variableMetadata[v]?.default || v}
                                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1.5 outline-none focus:border-indigo-500"
                                    />
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Error */}
                    {error && (
                        <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-2 py-1.5 rounded">
                            {error}
                        </div>
                    )}

                    {/* Success */}
                    {lastRunCount !== null && !error && (
                        <div className="bg-green-900/50 border border-green-700 text-green-300 text-xs px-2 py-1.5 rounded">
                            {lastRunCount} provider(s) completed
                        </div>
                    )}

                    {/* Run button */}
                    <button
                        onClick={handleRun}
                        disabled={selectedProviderIds.length === 0 || isRunning}
                        className="w-full px-3 py-2 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {isRunning ? 'Running...' : 'Run'}
                    </button>
                    <div className="text-[10px] text-gray-600 text-center">Ctrl+Enter</div>
                </>
            )}
        </div>
    );
}
