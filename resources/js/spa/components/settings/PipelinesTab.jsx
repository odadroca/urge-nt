import { useState, useCallback, useRef } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listPipelines, createPipeline, getPipeline, updatePipeline, deletePipeline, addChannel, updateChannel, removeChannel } from '../../api/pipelines.js';
import { listProviders } from '../../api/providers.js';
import AutocompleteDropdown from '../workspace/AutocompleteDropdown.jsx';
import useAutocomplete from '../../hooks/useAutocomplete.js';

export default function PipelinesTab() {
    const queryClient = useQueryClient();
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [expandedSlug, setExpandedSlug] = useState(null);
    const [createForm, setCreateForm] = useState({ name: '', description: '' });
    const [creating, setCreating] = useState(false);
    const [createError, setCreateError] = useState('');

    const { data: pipelinesData, isLoading } = useQuery({
        queryKey: ['settings', 'pipelines'],
        queryFn: listPipelines,
    });

    const pipelines = pipelinesData?.data ?? pipelinesData ?? [];

    const handleToggleActive = useCallback(async (pipeline) => {
        try {
            await updatePipeline(pipeline.slug, { is_active: !pipeline.is_active });
            queryClient.invalidateQueries({ queryKey: ['settings', 'pipelines'] });
            queryClient.invalidateQueries({ queryKey: ['settings', 'pipeline', pipeline.slug] });
        } catch (err) {
            console.error('Toggle active failed:', err);
        }
    }, [queryClient]);

    const handleDelete = useCallback(async (slug) => {
        if (!confirm('Delete this pipeline? This cannot be undone.')) return;
        try {
            await deletePipeline(slug);
            queryClient.invalidateQueries({ queryKey: ['settings', 'pipelines'] });
            if (expandedSlug === slug) setExpandedSlug(null);
        } catch (err) {
            console.error('Delete failed:', err);
        }
    }, [queryClient, expandedSlug]);

    const handleCreate = async (e) => {
        e.preventDefault();
        if (!createForm.name.trim()) return;
        setCreating(true);
        setCreateError('');
        try {
            await createPipeline({ name: createForm.name.trim(), description: createForm.description.trim() });
            queryClient.invalidateQueries({ queryKey: ['settings', 'pipelines'] });
            setCreateForm({ name: '', description: '' });
            setShowCreateForm(false);
        } catch (err) {
            setCreateError(err.response?.data?.message || err.response?.data?.error || 'Failed to create pipeline');
        } finally {
            setCreating(false);
        }
    };

    const toggleExpand = (slug) => {
        setExpandedSlug(prev => prev === slug ? null : slug);
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-200">Pipelines</h2>
                <button
                    onClick={() => { setShowCreateForm(!showCreateForm); setCreateError(''); }}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded"
                >
                    + New Pipeline
                </button>
            </div>

            {/* Create form */}
            {showCreateForm && (
                <form onSubmit={handleCreate} className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-xs text-gray-400 uppercase tracking-wider">Create Pipeline</span>
                        <button type="button" onClick={() => setShowCreateForm(false)} className="text-gray-500 hover:text-gray-300 text-sm">&times;</button>
                    </div>

                    {createError && (
                        <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-2 py-1 rounded">{createError}</div>
                    )}

                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Name</label>
                        <input
                            value={createForm.name}
                            onChange={(e) => setCreateForm(f => ({ ...f, name: e.target.value }))}
                            placeholder="e.g. Multi-Model Compare"
                            className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                            autoFocus
                        />
                    </div>

                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Description</label>
                        <input
                            value={createForm.description}
                            onChange={(e) => setCreateForm(f => ({ ...f, description: e.target.value }))}
                            placeholder="Optional description"
                            className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                        />
                    </div>

                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setShowCreateForm(false)}
                            className="text-xs text-gray-400 hover:text-gray-200 px-3 py-1.5"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={!createForm.name.trim() || creating}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-4 py-1.5 rounded disabled:opacity-50"
                        >
                            {creating ? 'Creating...' : 'Create'}
                        </button>
                    </div>
                </form>
            )}

            {/* Pipeline list */}
            {isLoading && (
                <div className="flex justify-center py-8">
                    <div className="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full" />
                </div>
            )}

            {!isLoading && pipelines.length === 0 && (
                <p className="text-sm text-gray-500 text-center py-8">No pipelines yet. Create one to get started.</p>
            )}

            {!isLoading && pipelines.length > 0 && (
                <div className="space-y-2">
                    {pipelines.map(pipeline => (
                        <div key={pipeline.id || pipeline.slug} className="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
                            {/* Pipeline header */}
                            <div
                                className="p-3 flex items-center gap-4 cursor-pointer hover:bg-gray-750"
                                onClick={() => toggleExpand(pipeline.slug)}
                            >
                                {/* Active dot */}
                                <div className={`w-2 h-2 rounded-full flex-shrink-0 ${pipeline.is_active ? 'bg-green-400' : 'bg-gray-600'}`} />

                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-0.5">
                                        <span className="text-sm text-gray-200 font-medium truncate">{pipeline.name}</span>
                                        {pipeline.channels_count != null && (
                                            <span className="text-[10px] bg-gray-700 text-gray-400 px-1.5 py-0.5 rounded">
                                                {pipeline.channels_count} channel{pipeline.channels_count !== 1 ? 's' : ''}
                                            </span>
                                        )}
                                    </div>
                                    {pipeline.description && (
                                        <p className="text-[10px] text-gray-500 truncate">{pipeline.description}</p>
                                    )}
                                </div>

                                <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
                                    <button
                                        onClick={() => handleToggleActive(pipeline)}
                                        className={`text-[10px] px-2 py-1 rounded font-medium ${
                                            pipeline.is_active
                                                ? 'bg-green-900/30 text-green-400 hover:bg-green-900/50'
                                                : 'bg-gray-700 text-gray-500 hover:bg-gray-600'
                                        }`}
                                    >
                                        {pipeline.is_active ? 'Active' : 'Inactive'}
                                    </button>
                                    <button
                                        onClick={() => handleDelete(pipeline.slug)}
                                        className="text-gray-500 hover:text-red-400 text-sm"
                                    >
                                        &times;
                                    </button>
                                </div>

                                {/* Expand chevron */}
                                <span className={`text-gray-500 text-xs transition-transform ${expandedSlug === pipeline.slug ? 'rotate-90' : ''}`}>
                                    &#9654;
                                </span>
                            </div>

                            {/* Expanded detail */}
                            {expandedSlug === pipeline.slug && (
                                <PipelineDetail slug={pipeline.slug} />
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function PipelineDetail({ slug }) {
    const queryClient = useQueryClient();
    const [editingChannelId, setEditingChannelId] = useState(null);
    const [showAddChannel, setShowAddChannel] = useState(false);

    const { data: pipelineData, isLoading } = useQuery({
        queryKey: ['settings', 'pipeline', slug],
        queryFn: () => getPipeline(slug),
    });

    const { data: providersData } = useQuery({
        queryKey: ['settings', 'providers'],
        queryFn: listProviders,
    });

    const pipeline = pipelineData?.data ?? pipelineData ?? null;
    const channels = pipeline?.channels ?? [];
    const providers = providersData?.data ?? providersData ?? [];

    const handleDeleteChannel = useCallback(async (channelId) => {
        if (!confirm('Remove this channel?')) return;
        try {
            await removeChannel(slug, channelId);
            queryClient.invalidateQueries({ queryKey: ['settings', 'pipeline', slug] });
            queryClient.invalidateQueries({ queryKey: ['settings', 'pipelines'] });
        } catch (err) {
            console.error('Remove channel failed:', err);
        }
    }, [queryClient, slug]);

    const handleChannelSaved = useCallback(() => {
        setEditingChannelId(null);
        setShowAddChannel(false);
        queryClient.invalidateQueries({ queryKey: ['settings', 'pipeline', slug] });
        queryClient.invalidateQueries({ queryKey: ['settings', 'pipelines'] });
    }, [queryClient, slug]);

    if (isLoading) {
        return (
            <div className="border-t border-gray-700 p-4 flex justify-center">
                <div className="animate-spin h-5 w-5 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    return (
        <div className="border-t border-gray-700 p-4 space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-xs text-gray-400 uppercase tracking-wider">Channels</span>
                <button
                    onClick={() => { setShowAddChannel(true); setEditingChannelId(null); }}
                    className="text-[10px] px-2 py-1 rounded bg-indigo-600 hover:bg-indigo-700 text-white"
                >
                    + Add Channel
                </button>
            </div>

            {channels.length === 0 && !showAddChannel && (
                <p className="text-xs text-gray-500 text-center py-4">No channels. Add one to configure this pipeline.</p>
            )}

            {channels.map(channel => (
                editingChannelId === channel.id ? (
                    <ChannelForm
                        key={channel.id}
                        pipelineSlug={slug}
                        channel={channel}
                        providers={providers}
                        onSaved={handleChannelSaved}
                        onCancel={() => setEditingChannelId(null)}
                    />
                ) : (
                    <div key={channel.id} className="bg-gray-900 border border-gray-700 rounded p-2.5 flex items-center gap-3">
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-0.5">
                                <span className="text-xs text-gray-200 font-medium">{channel.role_label || channel.name || 'Channel'}</span>
                                {channel.trigger && (
                                    <span className={`text-[9px] px-1.5 py-0.5 rounded font-medium ${
                                        channel.trigger === 'synthesis'
                                            ? 'bg-purple-900/40 text-purple-400'
                                            : 'bg-blue-900/40 text-blue-400'
                                    }`}>
                                        {channel.trigger}
                                    </span>
                                )}
                                {channel.execution_mode === 'client' ? (
                                    <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-900/40 text-amber-400" title="No active LLM provider — the LLM client must run this channel locally">
                                        runs locally
                                    </span>
                                ) : channel.provider ? (
                                    <span className="text-[10px] text-gray-500">{channel.provider.name}</span>
                                ) : (
                                    <span className="text-[10px] text-red-400">no provider</span>
                                )}
                            </div>
                            {channel.system_prompt && (
                                <p className="text-[10px] text-gray-500 truncate">{channel.system_prompt}</p>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => { setEditingChannelId(channel.id); setShowAddChannel(false); }}
                                className="text-[10px] px-2 py-1 rounded bg-gray-700 text-gray-300 hover:bg-gray-600"
                            >
                                Edit
                            </button>
                            <button
                                onClick={() => handleDeleteChannel(channel.id)}
                                className="text-gray-500 hover:text-red-400 text-sm"
                            >
                                &times;
                            </button>
                        </div>
                    </div>
                )
            ))}

            {showAddChannel && (
                <ChannelForm
                    pipelineSlug={slug}
                    channel={null}
                    providers={providers}
                    onSaved={handleChannelSaved}
                    onCancel={() => setShowAddChannel(false)}
                />
            )}
        </div>
    );
}

function ChannelForm({ pipelineSlug, channel, providers, onSaved, onCancel }) {
    const [form, setForm] = useState({
        role_label: channel?.role_label || channel?.name || '',
        provider_id: channel?.provider_id || channel?.provider?.id || '',
        system_prompt: channel?.system_prompt || '',
        trigger: channel?.trigger || 'parallel',
        sort_order: channel?.sort_order ?? 0,
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const systemPromptRef = useRef(null);
    const autocomplete = useAutocomplete(systemPromptRef);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.role_label.trim()) return;
        setSaving(true);
        setError('');
        try {
            const payload = {
                role_label: form.role_label.trim(),
                name: form.role_label.trim(),
                provider_id: form.provider_id || null,
                system_prompt: form.system_prompt.trim() || null,
                trigger: form.trigger,
                sort_order: parseInt(form.sort_order, 10) || 0,
            };
            if (channel) {
                await updateChannel(pipelineSlug, channel.id, payload);
            } else {
                await addChannel(pipelineSlug, payload);
            }
            onSaved();
        } catch (err) {
            setError(err.response?.data?.message || err.response?.data?.error || 'Save failed');
        } finally {
            setSaving(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="bg-gray-900 border border-gray-600 rounded p-3 space-y-2.5">
            <div className="flex items-center justify-between">
                <span className="text-[10px] text-gray-400 uppercase tracking-wider">
                    {channel ? 'Edit Channel' : 'Add Channel'}
                </span>
                <button type="button" onClick={onCancel} className="text-gray-500 hover:text-gray-300 text-sm">&times;</button>
            </div>

            {error && (
                <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-2 py-1 rounded">{error}</div>
            )}

            <div className="grid grid-cols-2 gap-2">
                <div>
                    <label className="block text-[10px] text-gray-400 mb-0.5">Role Label</label>
                    <input
                        value={form.role_label}
                        onChange={(e) => setForm(f => ({ ...f, role_label: e.target.value }))}
                        placeholder="e.g. Primary Analyst"
                        className="w-full bg-gray-800 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1.5 outline-none focus:border-indigo-500"
                        autoFocus
                    />
                </div>
                <div>
                    <label className="block text-[10px] text-gray-400 mb-0.5">Provider</label>
                    <select
                        value={form.provider_id}
                        onChange={(e) => setForm(f => ({ ...f, provider_id: e.target.value }))}
                        className="w-full bg-gray-800 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1.5 outline-none focus:border-indigo-500"
                    >
                        <option value="">-- Select Provider --</option>
                        {providers.map(p => (
                            <option key={p.id} value={p.id}>{p.name}{p.model ? ` (${p.model})` : ''}</option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="relative">
                <label className="block text-[10px] text-gray-400 mb-0.5">System Prompt <span className="text-gray-600">(type {'{{>'} for fragment suggestions)</span></label>
                <textarea
                    ref={systemPromptRef}
                    value={form.system_prompt}
                    onChange={(e) => setForm(f => ({ ...f, system_prompt: e.target.value }))}
                    onInput={autocomplete.handleInput}
                    onKeyDown={(e) => { if (autocomplete.handleKeyDown(e)) return; }}
                    onBlur={() => { setTimeout(() => autocomplete.dismiss(), 150); }}
                    placeholder="Optional system prompt. Type {{> to include versioned fragments."
                    rows={3}
                    className="w-full bg-gray-800 border border-gray-600 text-gray-200 text-xs font-mono rounded px-2 py-1.5 outline-none focus:border-indigo-500 resize-none"
                />
                {autocomplete.isOpen && (
                    <AutocompleteDropdown
                        items={autocomplete.filteredItems}
                        selectedIndex={autocomplete.selectedIndex}
                        position={autocomplete.position}
                        triggerType={autocomplete.triggerType}
                        onSelect={autocomplete.insertItem}
                    />
                )}
            </div>

            <div className="grid grid-cols-2 gap-2">
                <div>
                    <label className="block text-[10px] text-gray-400 mb-0.5">Trigger</label>
                    <select
                        value={form.trigger}
                        onChange={(e) => setForm(f => ({ ...f, trigger: e.target.value }))}
                        className="w-full bg-gray-800 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1.5 outline-none focus:border-indigo-500"
                    >
                        <option value="parallel">parallel</option>
                        <option value="synthesis">synthesis</option>
                    </select>
                </div>
                <div>
                    <label className="block text-[10px] text-gray-400 mb-0.5">Sort Order</label>
                    <input
                        type="number"
                        value={form.sort_order}
                        onChange={(e) => setForm(f => ({ ...f, sort_order: e.target.value }))}
                        className="w-full bg-gray-800 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1.5 outline-none focus:border-indigo-500"
                    />
                </div>
            </div>

            <div className="flex justify-end gap-2">
                <button
                    type="button"
                    onClick={onCancel}
                    className="text-xs text-gray-400 hover:text-gray-200 px-2 py-1"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={!form.role_label.trim() || saving}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1 rounded disabled:opacity-50"
                >
                    {saving ? 'Saving...' : (channel ? 'Update' : 'Add')}
                </button>
            </div>
        </form>
    );
}
