import { useState, useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { updatePrompt } from '../../api/prompts.js';
import { listCategories } from '../../api/categories.js';

export default function PromptMetadataModal({ isOpen, onClose, prompt, username, slug }) {
    const queryClient = useQueryClient();
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [type, setType] = useState('prompt');
    const [categoryId, setCategoryId] = useState('');
    const [tags, setTags] = useState('');
    const [visibility, setVisibility] = useState('private');
    const [categories, setCategories] = useState([]);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (prompt && isOpen) {
            setName(prompt.name || '');
            setDescription(prompt.description || '');
            setType(prompt.type || 'prompt');
            setCategoryId(prompt.category_id || prompt.category?.id || '');
            setTags(Array.isArray(prompt.tags) ? prompt.tags.join(', ') : '');
            setVisibility(prompt.visibility || 'private');
            setError('');
        }
    }, [prompt, isOpen]);

    useEffect(() => {
        if (isOpen) {
            listCategories().then(res => setCategories(res.data || [])).catch(() => {});
        }
    }, [isOpen]);

    const handleSave = async () => {
        if (!name.trim()) { setError('Name is required.'); return; }
        setSaving(true);
        setError('');
        try {
            const tagArray = tags.split(',').map(t => t.trim()).filter(Boolean);
            await updatePrompt(username, slug, {
                name: name.trim(),
                description: description.trim() || null,
                type,
                category_id: categoryId || null,
                tags: tagArray.length ? tagArray : null,
                visibility,
            });
            queryClient.invalidateQueries({ queryKey: ['workspace'] });
            queryClient.invalidateQueries({ queryKey: ['browse'] });
            onClose();
        } catch (err) {
            setError(err.response?.data?.message || 'Save failed.');
        } finally {
            setSaving(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div className="bg-gray-800 border border-gray-700 rounded-xl p-6 w-full max-w-md shadow-xl" onClick={e => e.stopPropagation()}>
                <div className="flex items-center justify-between mb-4">
                    <h2 className="text-lg font-semibold text-gray-100">Prompt Metadata</h2>
                    <button onClick={onClose} className="text-gray-500 hover:text-gray-300 text-xl">&times;</button>
                </div>

                {error && (
                    <div className="bg-red-900/50 border border-red-700 text-red-300 text-sm px-3 py-2 rounded mb-4">{error}</div>
                )}

                <div className="space-y-4">
                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Name</label>
                        <input value={name} onChange={e => setName(e.target.value)}
                            className="w-full bg-gray-900 border border-gray-600 text-gray-100 text-sm rounded px-3 py-2 focus:border-indigo-500 outline-none" />
                    </div>

                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Description</label>
                        <textarea value={description} onChange={e => setDescription(e.target.value)} rows={2}
                            className="w-full bg-gray-900 border border-gray-600 text-gray-100 text-sm rounded px-3 py-2 resize-none focus:border-indigo-500 outline-none" />
                    </div>

                    <div className="flex gap-3">
                        <div className="flex-1">
                            <label className="block text-xs text-gray-400 mb-1">Type</label>
                            <select value={type} onChange={e => setType(e.target.value)}
                                className="w-full bg-gray-900 border border-gray-600 text-gray-300 text-sm rounded px-2 py-2 outline-none">
                                <option value="prompt">Prompt</option>
                                <option value="fragment">Fragment</option>
                            </select>
                        </div>
                        <div className="flex-1">
                            <label className="block text-xs text-gray-400 mb-1">Visibility</label>
                            <select value={visibility} onChange={e => setVisibility(e.target.value)}
                                className="w-full bg-gray-900 border border-gray-600 text-gray-300 text-sm rounded px-2 py-2 outline-none">
                                <option value="private">Private</option>
                                <option value="shared">Shared</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Category</label>
                        <select value={categoryId} onChange={e => setCategoryId(e.target.value)}
                            className="w-full bg-gray-900 border border-gray-600 text-gray-300 text-sm rounded px-2 py-2 outline-none">
                            <option value="">None</option>
                            {categories.map(c => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Tags (comma-separated)</label>
                        <input value={tags} onChange={e => setTags(e.target.value)} placeholder="react, api, testing"
                            className="w-full bg-gray-900 border border-gray-600 text-gray-100 text-sm rounded px-3 py-2 focus:border-indigo-500 outline-none" />
                    </div>
                </div>

                <div className="flex justify-end gap-2 mt-6">
                    <button onClick={onClose} className="px-4 py-2 text-sm text-gray-400 hover:text-gray-200">Cancel</button>
                    <button onClick={handleSave} disabled={saving}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-6 py-2 rounded-lg disabled:opacity-50">
                        {saving ? 'Saving...' : 'Save'}
                    </button>
                </div>
            </div>
        </div>
    );
}
