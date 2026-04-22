import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listApiKeys, createApiKey, updateApiKey, deleteApiKey } from '../../api/apiKeys.js';
import { listPrompts } from '../../api/prompts.js';

export default function ApiKeysTab() {
    const queryClient = useQueryClient();
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [newKeyBanner, setNewKeyBanner] = useState(null);

    const { data: keysData, isLoading } = useQuery({
        queryKey: ['settings', 'apiKeys'],
        queryFn: listApiKeys,
    });

    const keys = keysData?.data ?? keysData ?? [];

    const handleToggleActive = useCallback(async (key) => {
        try {
            await updateApiKey(key.id, { is_active: !key.is_active });
            queryClient.invalidateQueries({ queryKey: ['settings', 'apiKeys'] });
        } catch (err) {
            console.error('Toggle active failed:', err);
        }
    }, [queryClient]);

    const handleDelete = useCallback(async (id) => {
        if (!confirm('Delete this API key? This cannot be undone.')) return;
        try {
            await deleteApiKey(id);
            queryClient.invalidateQueries({ queryKey: ['settings', 'apiKeys'] });
        } catch (err) {
            console.error('Delete failed:', err);
        }
    }, [queryClient]);

    const handleKeyCreated = useCallback((plainTextKey) => {
        setShowCreateForm(false);
        setNewKeyBanner(plainTextKey);
        queryClient.invalidateQueries({ queryKey: ['settings', 'apiKeys'] });
    }, [queryClient]);

    const handleCopyKey = useCallback(async () => {
        if (!newKeyBanner) return;
        try {
            await navigator.clipboard.writeText(newKeyBanner);
        } catch (err) {
            console.error('Copy failed:', err);
        }
    }, [newKeyBanner]);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-200">API Keys</h2>
                <button
                    onClick={() => { setShowCreateForm(!showCreateForm); setNewKeyBanner(null); }}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded"
                >
                    + New Key
                </button>
            </div>

            {/* New key banner */}
            {newKeyBanner && (
                <div className="bg-green-900/40 border border-green-700 rounded-lg p-3">
                    <div className="flex items-center justify-between mb-1">
                        <span className="text-xs text-green-300 font-medium">Key created! Copy it now -- it won't be shown again.</span>
                        <button
                            onClick={() => setNewKeyBanner(null)}
                            className="text-green-400 hover:text-green-200 text-sm"
                        >
                            &times;
                        </button>
                    </div>
                    <div className="flex items-center gap-2">
                        <code className="flex-1 bg-gray-900 text-green-300 text-xs px-2 py-1.5 rounded font-mono break-all">
                            {newKeyBanner}
                        </code>
                        <button
                            onClick={handleCopyKey}
                            className="bg-green-700 hover:bg-green-600 text-white text-xs px-3 py-1.5 rounded whitespace-nowrap"
                        >
                            Copy
                        </button>
                    </div>
                </div>
            )}

            {/* Create form */}
            {showCreateForm && (
                <CreateKeyForm
                    onCreated={handleKeyCreated}
                    onCancel={() => setShowCreateForm(false)}
                />
            )}

            {/* Key list */}
            {isLoading && (
                <div className="flex justify-center py-8">
                    <div className="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full" />
                </div>
            )}

            {!isLoading && keys.length === 0 && (
                <p className="text-sm text-gray-500 text-center py-8">No API keys yet. Create one to get started.</p>
            )}

            {!isLoading && keys.length > 0 && (
                <div className="space-y-2">
                    {keys.map(key => (
                        <div key={key.id} className="bg-gray-800 border border-gray-700 rounded-lg p-3 flex items-center gap-4">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                    <span className="text-sm text-gray-200 font-medium truncate">{key.name}</span>
                                    <code className="text-[10px] text-gray-500 bg-gray-900 px-1.5 py-0.5 rounded font-mono">
                                        {key.key_preview || 'urge_***'}
                                    </code>
                                </div>
                                <div className="flex items-center gap-3 text-[10px] text-gray-500">
                                    {key.last_used_at && (
                                        <span>Last used: {new Date(key.last_used_at).toLocaleDateString()}</span>
                                    )}
                                    {!key.last_used_at && <span>Never used</span>}
                                    {key.prompts_count != null && (
                                        <span>{key.prompts_count} prompt{key.prompts_count !== 1 ? 's' : ''} scoped</span>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={() => handleToggleActive(key)}
                                    className={`text-[10px] px-2 py-1 rounded font-medium ${
                                        key.is_active
                                            ? 'bg-green-900/30 text-green-400 hover:bg-green-900/50'
                                            : 'bg-gray-700 text-gray-500 hover:bg-gray-600'
                                    }`}
                                >
                                    {key.is_active ? 'Active' : 'Inactive'}
                                </button>
                                <button
                                    onClick={() => handleDelete(key.id)}
                                    className="text-gray-500 hover:text-red-400 text-sm"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function CreateKeyForm({ onCreated, onCancel }) {
    const [name, setName] = useState('');
    const [selectedPromptIds, setSelectedPromptIds] = useState([]);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const { data: promptsData } = useQuery({
        queryKey: ['settings', 'apiKeys', 'prompts'],
        queryFn: () => listPrompts({ per_page: 100 }),
    });

    const prompts = promptsData?.data ?? [];

    const handleTogglePrompt = (id) => {
        setSelectedPromptIds(prev =>
            prev.includes(id) ? prev.filter(p => p !== id) : [...prev, id]
        );
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!name.trim()) return;
        setSaving(true);
        setError('');
        try {
            const result = await createApiKey({
                name: name.trim(),
                prompt_ids: selectedPromptIds.length > 0 ? selectedPromptIds : undefined,
            });
            console.log('createApiKey result:', JSON.stringify(result));
            const plainKey = result?.data?.key ?? result?.key;
            console.log('plainKey:', plainKey);
            onCreated(plainKey);
        } catch (err) {
            setError(err.response?.data?.message || err.response?.data?.error || 'Failed to create key');
            setSaving(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-xs text-gray-400 uppercase tracking-wider">Create API Key</span>
                <button type="button" onClick={onCancel} className="text-gray-500 hover:text-gray-300 text-sm">&times;</button>
            </div>

            {error && (
                <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-2 py-1 rounded">{error}</div>
            )}

            <div>
                <label className="block text-xs text-gray-400 mb-1">Name</label>
                <input
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g. My App Key"
                    className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                    autoFocus
                />
            </div>

            {prompts.length > 0 && (
                <div>
                    <label className="block text-xs text-gray-400 mb-1">
                        Scope to prompts <span className="text-gray-600">(optional -- leave unchecked for full access)</span>
                    </label>
                    <div className="max-h-40 overflow-y-auto bg-gray-900 border border-gray-700 rounded p-2 space-y-1">
                        {prompts.map(prompt => (
                            <label key={prompt.id} className="flex items-center gap-2 text-xs text-gray-300 cursor-pointer hover:text-gray-100">
                                <input
                                    type="checkbox"
                                    checked={selectedPromptIds.includes(prompt.id)}
                                    onChange={() => handleTogglePrompt(prompt.id)}
                                    className="rounded border-gray-600 bg-gray-800 text-indigo-600"
                                />
                                <span className="truncate">{prompt.name}</span>
                                <span className="text-[10px] text-gray-600 ml-auto">{prompt.owner?.username}/{prompt.slug}</span>
                            </label>
                        ))}
                    </div>
                </div>
            )}

            <div className="flex justify-end gap-2">
                <button
                    type="button"
                    onClick={onCancel}
                    className="text-xs text-gray-400 hover:text-gray-200 px-3 py-1.5"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={!name.trim() || saving}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-4 py-1.5 rounded disabled:opacity-50"
                >
                    {saving ? 'Creating...' : 'Create Key'}
                </button>
            </div>
        </form>
    );
}
