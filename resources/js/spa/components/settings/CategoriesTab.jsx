import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listCategories, createCategory, updateCategory, deleteCategory } from '../../api/categories.js';

const COLORS = {
    gray: '#6b7280', red: '#ef4444', orange: '#f97316', amber: '#f59e0b',
    yellow: '#eab308', lime: '#84cc16', green: '#22c55e', emerald: '#10b981',
    teal: '#14b8a6', cyan: '#06b6d4', sky: '#0ea5e9', blue: '#3b82f6',
    indigo: '#6366f1', violet: '#8b5cf6', purple: '#a855f7', fuchsia: '#d946ef',
    pink: '#ec4899', rose: '#f43f5e',
};

const COLOR_ENTRIES = Object.entries(COLORS);

export default function CategoriesTab({ role }) {
    const queryClient = useQueryClient();
    const canEdit = role === 'admin' || role === 'editor';
    const [editingId, setEditingId] = useState(null); // null = closed, 'new' = create, number = edit
    const [form, setForm] = useState({ name: '', color: COLORS.blue });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const { data: categoriesData, isLoading } = useQuery({
        queryKey: ['settings', 'categories'],
        queryFn: listCategories,
    });

    const categories = categoriesData?.data ?? categoriesData ?? [];

    const openCreateForm = () => {
        setEditingId('new');
        setForm({ name: '', color: COLORS.blue });
        setError('');
    };

    const openEditForm = (category) => {
        setEditingId(category.id);
        setForm({
            name: category.name || '',
            color: category.color || COLORS.blue,
        });
        setError('');
    };

    const closeForm = () => {
        setEditingId(null);
        setForm({ name: '', color: COLORS.blue });
        setError('');
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.name.trim()) return;
        setSaving(true);
        setError('');
        try {
            if (editingId === 'new') {
                await createCategory({ name: form.name.trim(), color: form.color });
            } else {
                await updateCategory(editingId, { name: form.name.trim(), color: form.color });
            }
            queryClient.invalidateQueries({ queryKey: ['settings', 'categories'] });
            closeForm();
        } catch (err) {
            setError(err.response?.data?.message || err.response?.data?.error || 'Save failed');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = useCallback(async (id) => {
        if (!confirm('Delete this category? Prompts will be uncategorized.')) return;
        try {
            await deleteCategory(id);
            queryClient.invalidateQueries({ queryKey: ['settings', 'categories'] });
        } catch (err) {
            console.error('Delete failed:', err);
        }
    }, [queryClient]);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-200">Categories</h2>
                {canEdit && (
                    <button
                        onClick={openCreateForm}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded"
                    >
                        + New Category
                    </button>
                )}
            </div>

            {/* Create/Edit form */}
            {editingId !== null && canEdit && (
                <form onSubmit={handleSubmit} className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-xs text-gray-400 uppercase tracking-wider">
                            {editingId === 'new' ? 'New Category' : 'Edit Category'}
                        </span>
                        <button type="button" onClick={closeForm} className="text-gray-500 hover:text-gray-300 text-sm">&times;</button>
                    </div>

                    {error && (
                        <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-2 py-1 rounded">{error}</div>
                    )}

                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Name</label>
                        <input
                            value={form.name}
                            onChange={(e) => setForm(f => ({ ...f, name: e.target.value }))}
                            placeholder="e.g. Marketing"
                            className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                            autoFocus
                        />
                    </div>

                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Color</label>
                        <div className="grid grid-cols-9 gap-2">
                            {COLOR_ENTRIES.map(([name, hex]) => (
                                <button
                                    key={name}
                                    type="button"
                                    onClick={() => setForm(f => ({ ...f, color: hex }))}
                                    title={name}
                                    className={`w-7 h-7 rounded-full transition-transform ${
                                        form.color === hex ? 'ring-2 ring-white scale-110' : 'hover:scale-105'
                                    }`}
                                    style={{ backgroundColor: hex }}
                                />
                            ))}
                        </div>
                    </div>

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

            {/* Category list */}
            {isLoading && (
                <div className="flex justify-center py-8">
                    <div className="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full" />
                </div>
            )}

            {!isLoading && categories.length === 0 && (
                <p className="text-sm text-gray-500 text-center py-8">No categories yet.</p>
            )}

            {!isLoading && categories.length > 0 && (
                <div className="space-y-2">
                    {categories.map(category => (
                        <div key={category.id} className="bg-gray-800 border border-gray-700 rounded-lg p-3 flex items-center gap-3">
                            {/* Color swatch */}
                            <div
                                className="w-5 h-5 rounded-full flex-shrink-0"
                                style={{ backgroundColor: category.color || COLORS.gray }}
                            />

                            <div className="flex-1 min-w-0">
                                <span className="text-sm text-gray-200 font-medium">{category.name}</span>
                            </div>

                            {category.prompts_count != null && (
                                <span className="text-[10px] bg-gray-700 text-gray-400 px-1.5 py-0.5 rounded">
                                    {category.prompts_count} prompt{category.prompts_count !== 1 ? 's' : ''}
                                </span>
                            )}

                            {canEdit && (
                                <div className="flex items-center gap-2">
                                    <button
                                        onClick={() => openEditForm(category)}
                                        className="text-[10px] px-2 py-1 rounded bg-gray-700 text-gray-300 hover:bg-gray-600"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleDelete(category.id)}
                                        className="text-gray-500 hover:text-red-400 text-sm"
                                    >
                                        &times;
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
