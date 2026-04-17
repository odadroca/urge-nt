import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listProviders, createProvider, updateProvider, deleteProvider, testProvider } from '../../api/providers.js';

const DRIVERS = ['openai', 'anthropic', 'mistral', 'gemini', 'ollama', 'openrouter'];

const DRIVER_COLORS = {
    openai: 'bg-green-900/40 text-green-400',
    anthropic: 'bg-orange-900/40 text-orange-400',
    mistral: 'bg-blue-900/40 text-blue-400',
    gemini: 'bg-purple-900/40 text-purple-400',
    ollama: 'bg-gray-700 text-gray-300',
    openrouter: 'bg-cyan-900/40 text-cyan-400',
};

const emptyForm = { name: '', driver: 'openai', api_key: '', model: '', endpoint: '', is_active: true };

export default function LlmProvidersTab({ role }) {
    const queryClient = useQueryClient();
    const isAdmin = role === 'admin';
    const [editingId, setEditingId] = useState(null); // null = closed, 'new' = create, number = edit
    const [form, setForm] = useState({ ...emptyForm });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [testResults, setTestResults] = useState({}); // { id: { success, message } }

    const { data: providersData, isLoading } = useQuery({
        queryKey: ['settings', 'providers'],
        queryFn: listProviders,
    });

    const providers = providersData?.data ?? providersData ?? [];

    const showApiKey = form.driver !== 'ollama';
    const showEndpoint = form.driver === 'openai' || form.driver === 'ollama';

    const openCreateForm = () => {
        setEditingId('new');
        setForm({ ...emptyForm });
        setError('');
    };

    const openEditForm = (provider) => {
        setEditingId(provider.id);
        setForm({
            name: provider.name || '',
            driver: provider.driver || 'openai',
            api_key: '',
            model: provider.model || '',
            endpoint: provider.endpoint || '',
            is_active: provider.is_active ?? true,
        });
        setError('');
    };

    const closeForm = () => {
        setEditingId(null);
        setForm({ ...emptyForm });
        setError('');
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.name.trim()) return;
        setSaving(true);
        setError('');
        try {
            const payload = {
                name: form.name.trim(),
                driver: form.driver,
                model: form.model.trim() || null,
                endpoint: showEndpoint && form.endpoint.trim() ? form.endpoint.trim() : null,
                is_active: form.is_active,
            };
            // Only send api_key if it has a value (for create always, for edit only if changed)
            if (showApiKey && form.api_key) {
                payload.api_key = form.api_key;
            }
            if (editingId === 'new') {
                if (showApiKey) {
                    payload.api_key = form.api_key || '';
                }
                await createProvider(payload);
            } else {
                await updateProvider(editingId, payload);
            }
            queryClient.invalidateQueries({ queryKey: ['settings', 'providers'] });
            closeForm();
        } catch (err) {
            setError(err.response?.data?.message || err.response?.data?.error || 'Save failed');
        } finally {
            setSaving(false);
        }
    };

    const handleToggleActive = useCallback(async (provider) => {
        try {
            await updateProvider(provider.id, { is_active: !provider.is_active });
            queryClient.invalidateQueries({ queryKey: ['settings', 'providers'] });
        } catch (err) {
            console.error('Toggle active failed:', err);
        }
    }, [queryClient]);

    const handleDelete = useCallback(async (id) => {
        if (!confirm('Delete this LLM provider?')) return;
        try {
            await deleteProvider(id);
            queryClient.invalidateQueries({ queryKey: ['settings', 'providers'] });
        } catch (err) {
            console.error('Delete failed:', err);
        }
    }, [queryClient]);

    const handleTest = useCallback(async (id) => {
        setTestResults(prev => ({ ...prev, [id]: { loading: true } }));
        try {
            const result = await testProvider(id);
            setTestResults(prev => ({
                ...prev,
                [id]: { success: true, message: result?.message || result?.data?.message || 'Connection successful' },
            }));
        } catch (err) {
            setTestResults(prev => ({
                ...prev,
                [id]: { success: false, message: err.response?.data?.message || err.response?.data?.error || 'Connection failed' },
            }));
        }
    }, []);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-200">LLM Providers</h2>
                {isAdmin && (
                    <button
                        onClick={openCreateForm}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded"
                    >
                        + Add Provider
                    </button>
                )}
            </div>

            {/* Create/Edit form */}
            {editingId !== null && isAdmin && (
                <form onSubmit={handleSubmit} className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-xs text-gray-400 uppercase tracking-wider">
                            {editingId === 'new' ? 'Add Provider' : 'Edit Provider'}
                        </span>
                        <button type="button" onClick={closeForm} className="text-gray-500 hover:text-gray-300 text-sm">&times;</button>
                    </div>

                    {error && (
                        <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-2 py-1 rounded">{error}</div>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">Name</label>
                            <input
                                value={form.name}
                                onChange={(e) => setForm(f => ({ ...f, name: e.target.value }))}
                                placeholder="e.g. OpenAI GPT-4"
                                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                                autoFocus
                            />
                        </div>
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">Driver</label>
                            <select
                                value={form.driver}
                                onChange={(e) => setForm(f => ({ ...f, driver: e.target.value }))}
                                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                            >
                                {DRIVERS.map(d => (
                                    <option key={d} value={d}>{d}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {showApiKey && (
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">API Key</label>
                            <input
                                type="text"
                                value={form.api_key}
                                onChange={(e) => setForm(f => ({ ...f, api_key: e.target.value }))}
                                placeholder={editingId !== 'new' ? 'Leave blank to keep current' : 'Enter API key'}
                                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                            />
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">Model</label>
                            <input
                                value={form.model}
                                onChange={(e) => setForm(f => ({ ...f, model: e.target.value }))}
                                placeholder="e.g. gpt-4o"
                                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                            />
                        </div>
                        {showEndpoint && (
                            <div>
                                <label className="block text-xs text-gray-400 mb-1">Endpoint</label>
                                <input
                                    value={form.endpoint}
                                    onChange={(e) => setForm(f => ({ ...f, endpoint: e.target.value }))}
                                    placeholder="https://api.example.com/v1"
                                    className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                                />
                            </div>
                        )}
                    </div>

                    <label className="flex items-center gap-2 text-xs text-gray-300 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={form.is_active}
                            onChange={(e) => setForm(f => ({ ...f, is_active: e.target.checked }))}
                            className="rounded border-gray-600 bg-gray-900 text-indigo-600"
                        />
                        Active
                    </label>

                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={closeForm}
                            className="text-xs text-gray-400 hover:text-gray-200 px-3 py-1.5"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={!form.name.trim() || saving}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-4 py-1.5 rounded disabled:opacity-50"
                        >
                            {saving ? 'Saving...' : (editingId === 'new' ? 'Create' : 'Update')}
                        </button>
                    </div>
                </form>
            )}

            {/* Provider list */}
            {isLoading && (
                <div className="flex justify-center py-8">
                    <div className="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full" />
                </div>
            )}

            {!isLoading && providers.length === 0 && (
                <p className="text-sm text-gray-500 text-center py-8">No LLM providers configured.</p>
            )}

            {!isLoading && providers.length > 0 && (
                <div className="space-y-2">
                    {providers.map(provider => (
                        <div key={provider.id}>
                            <div className="bg-gray-800 border border-gray-700 rounded-lg p-3 flex items-center gap-4">
                                {/* Active dot */}
                                <div className={`w-2 h-2 rounded-full flex-shrink-0 ${provider.is_active ? 'bg-green-400' : 'bg-gray-600'}`} />

                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className="text-sm text-gray-200 font-medium truncate">{provider.name}</span>
                                        <span className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${DRIVER_COLORS[provider.driver] || 'bg-gray-700 text-gray-400'}`}>
                                            {provider.driver}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-3 text-[10px] text-gray-500">
                                        {provider.model && <span>{provider.model}</span>}
                                        {provider.endpoint && <span className="truncate max-w-[200px]">{provider.endpoint}</span>}
                                    </div>
                                </div>

                                {isAdmin && (
                                    <div className="flex items-center gap-2">
                                        <button
                                            onClick={() => handleTest(provider.id)}
                                            className="text-[10px] px-2 py-1 rounded bg-gray-700 text-gray-300 hover:bg-gray-600"
                                        >
                                            Test
                                        </button>
                                        <button
                                            onClick={() => handleToggleActive(provider)}
                                            className={`text-[10px] px-2 py-1 rounded font-medium ${
                                                provider.is_active
                                                    ? 'bg-green-900/30 text-green-400 hover:bg-green-900/50'
                                                    : 'bg-gray-700 text-gray-500 hover:bg-gray-600'
                                            }`}
                                        >
                                            {provider.is_active ? 'Active' : 'Inactive'}
                                        </button>
                                        <button
                                            onClick={() => openEditForm(provider)}
                                            className="text-[10px] px-2 py-1 rounded bg-gray-700 text-gray-300 hover:bg-gray-600"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() => handleDelete(provider.id)}
                                            className="text-gray-500 hover:text-red-400 text-sm"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* Test result inline */}
                            {testResults[provider.id] && !testResults[provider.id].loading && (
                                <div className={`mt-1 text-xs px-3 py-1.5 rounded ${
                                    testResults[provider.id].success
                                        ? 'bg-green-900/30 text-green-400 border border-green-800'
                                        : 'bg-red-900/30 text-red-400 border border-red-800'
                                }`}>
                                    {testResults[provider.id].message}
                                </div>
                            )}
                            {testResults[provider.id]?.loading && (
                                <div className="mt-1 text-xs px-3 py-1.5 text-gray-500">
                                    Testing connection...
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
