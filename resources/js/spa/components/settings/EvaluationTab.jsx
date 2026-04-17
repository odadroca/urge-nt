import { useState, useEffect, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getEvaluationSettings, saveEvaluationSettings } from '../../api/evaluationSettings.js';

export default function EvaluationTab() {
    const queryClient = useQueryClient();
    const [form, setForm] = useState(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [successMsg, setSuccessMsg] = useState('');
    const [newDimension, setNewDimension] = useState({ name: '', description: '' });

    const { data: settingsData, isLoading } = useQuery({
        queryKey: ['settings', 'evaluation'],
        queryFn: getEvaluationSettings,
    });

    const settings = settingsData?.data ?? settingsData ?? null;

    // Load settings into local state when fetched
    useEffect(() => {
        if (settings && !form) {
            setForm({
                enabled: settings.enabled ?? false,
                auto_evaluate: settings.auto_evaluate ?? false,
                default_provider_id: settings.default_provider_id || '',
                prompt_slug: settings.prompt_slug || '',
                dimensions: (settings.dimensions ?? []).map(d => ({ ...d })),
            });
        }
    }, [settings, form]);

    const providers = settings?.providers ?? [];
    const evalPrompts = settings?.eval_prompts ?? [];

    const handleToggle = (field) => {
        setForm(f => ({ ...f, [field]: !f[field] }));
    };

    const handleDimensionToggle = (index) => {
        setForm(f => {
            const dims = [...f.dimensions];
            dims[index] = { ...dims[index], enabled: !dims[index].enabled };
            return { ...f, dimensions: dims };
        });
    };

    const handleDimensionWeight = (index, value) => {
        setForm(f => {
            const dims = [...f.dimensions];
            dims[index] = { ...dims[index], weight: parseFloat(value) || 0 };
            return { ...f, dimensions: dims };
        });
    };

    const handleRemoveDimension = (index) => {
        setForm(f => {
            const dims = [...f.dimensions];
            dims.splice(index, 1);
            return { ...f, dimensions: dims };
        });
    };

    const handleAddDimension = () => {
        if (!newDimension.name.trim()) return;
        const slugified = newDimension.name.trim().toLowerCase().replace(/\s+/g, '_');
        setForm(f => ({
            ...f,
            dimensions: [
                ...f.dimensions,
                {
                    name: slugified,
                    description: newDimension.description.trim(),
                    weight: 1.0,
                    enabled: true,
                    builtin: false,
                },
            ],
        }));
        setNewDimension({ name: '', description: '' });
    };

    const handleSave = useCallback(async () => {
        if (!form) return;
        setSaving(true);
        setError('');
        setSuccessMsg('');
        try {
            await saveEvaluationSettings({
                enabled: form.enabled,
                auto_evaluate: form.auto_evaluate,
                default_provider_id: form.default_provider_id || null,
                prompt_slug: form.prompt_slug || null,
                dimensions: form.dimensions,
            });
            queryClient.invalidateQueries({ queryKey: ['settings', 'evaluation'] });
            setSuccessMsg('Settings saved.');
            setTimeout(() => setSuccessMsg(''), 3000);
        } catch (err) {
            setError(err.response?.data?.message || err.response?.data?.error || 'Save failed');
        } finally {
            setSaving(false);
        }
    }, [form, queryClient]);

    if (isLoading || !form) {
        return (
            <div className="flex justify-center py-8">
                <div className="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <h2 className="text-lg font-semibold text-gray-200">Evaluation Settings</h2>

            {error && (
                <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-3 py-2 rounded">{error}</div>
            )}

            {successMsg && (
                <div className="bg-green-900/40 border border-green-700 text-green-300 text-xs px-3 py-2 rounded">{successMsg}</div>
            )}

            {/* Toggles */}
            <div className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
                <label className="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={form.enabled}
                        onChange={() => handleToggle('enabled')}
                        className="rounded border-gray-600 bg-gray-900 text-indigo-600"
                    />
                    <div>
                        <span className="text-sm text-gray-200">Enable Evaluation</span>
                        <p className="text-[10px] text-gray-500">Allow LLM-powered scoring of results</p>
                    </div>
                </label>

                <label className="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={form.auto_evaluate}
                        onChange={() => handleToggle('auto_evaluate')}
                        className="rounded border-gray-600 bg-gray-900 text-indigo-600"
                    />
                    <div>
                        <span className="text-sm text-gray-200">Auto-Evaluate</span>
                        <p className="text-[10px] text-gray-500">Automatically evaluate new results when saved</p>
                    </div>
                </label>
            </div>

            {/* Provider + Prompt selectors */}
            <div className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
                <div>
                    <label className="block text-xs text-gray-400 mb-1">Evaluation Provider</label>
                    <select
                        value={form.default_provider_id}
                        onChange={(e) => setForm(f => ({ ...f, default_provider_id: e.target.value }))}
                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                    >
                        <option value="">-- Select Provider --</option>
                        {providers.map(p => (
                            <option key={p.id} value={p.id}>
                                {p.name}{p.model ? ` (${p.model})` : ''}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-xs text-gray-400 mb-1">Evaluation Prompt</label>
                    <select
                        value={form.prompt_slug}
                        onChange={(e) => setForm(f => ({ ...f, prompt_slug: e.target.value }))}
                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                    >
                        <option value="">-- Select Prompt --</option>
                        {evalPrompts.map(p => (
                            <option key={p.slug} value={p.slug}>{p.name}</option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Dimensions table */}
            <div className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
                <span className="text-xs text-gray-400 uppercase tracking-wider">Scoring Dimensions</span>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-[10px] text-gray-500 uppercase">
                                <th className="text-left py-1.5 px-2 w-10">On</th>
                                <th className="text-left py-1.5 px-2">Name</th>
                                <th className="text-left py-1.5 px-2">Description</th>
                                <th className="text-left py-1.5 px-2 w-24">Weight</th>
                                <th className="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {form.dimensions.map((dim, i) => (
                                <tr key={dim.name + i} className="border-t border-gray-700">
                                    <td className="py-1.5 px-2">
                                        <input
                                            type="checkbox"
                                            checked={dim.enabled}
                                            onChange={() => handleDimensionToggle(i)}
                                            className="rounded border-gray-600 bg-gray-900 text-indigo-600"
                                        />
                                    </td>
                                    <td className="py-1.5 px-2 text-gray-200 text-xs font-medium">{dim.name}</td>
                                    <td className="py-1.5 px-2 text-gray-400 text-xs">{dim.description || '--'}</td>
                                    <td className="py-1.5 px-2">
                                        <input
                                            type="number"
                                            value={dim.weight}
                                            onChange={(e) => handleDimensionWeight(i, e.target.value)}
                                            min="0"
                                            max="5"
                                            step="0.1"
                                            className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1 outline-none focus:border-indigo-500"
                                        />
                                    </td>
                                    <td className="py-1.5 px-2 text-center">
                                        {dim.builtin === false && (
                                            <button
                                                onClick={() => handleRemoveDimension(i)}
                                                className="text-gray-500 hover:text-red-400 text-sm"
                                            >
                                                &times;
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Add custom dimension */}
                <div className="flex items-end gap-2 pt-2 border-t border-gray-700">
                    <div className="flex-1">
                        <label className="block text-[10px] text-gray-400 mb-0.5">Name</label>
                        <input
                            value={newDimension.name}
                            onChange={(e) => setNewDimension(d => ({ ...d, name: e.target.value }))}
                            placeholder="e.g. creativity"
                            className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1.5 outline-none focus:border-indigo-500"
                        />
                    </div>
                    <div className="flex-1">
                        <label className="block text-[10px] text-gray-400 mb-0.5">Description</label>
                        <input
                            value={newDimension.description}
                            onChange={(e) => setNewDimension(d => ({ ...d, description: e.target.value }))}
                            placeholder="What this dimension measures"
                            className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1.5 outline-none focus:border-indigo-500"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={handleAddDimension}
                        disabled={!newDimension.name.trim()}
                        className="bg-gray-700 hover:bg-gray-600 text-gray-200 text-xs px-3 py-1.5 rounded disabled:opacity-50"
                    >
                        Add
                    </button>
                </div>
            </div>

            {/* Save button */}
            <div className="flex justify-end">
                <button
                    onClick={handleSave}
                    disabled={saving}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-5 py-2 rounded disabled:opacity-50"
                >
                    {saving ? 'Saving...' : 'Save Settings'}
                </button>
            </div>
        </div>
    );
}
