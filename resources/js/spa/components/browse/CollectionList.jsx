import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { listCollections, getCollection, createCollection, deleteCollection, removeItem } from '../../api/collections.js';

function CollectionItem({ item, collectionSlug, onRemoved }) {
    const queryClient = useQueryClient();

    const handleRemove = async (e) => {
        e.preventDefault();
        e.stopPropagation();
        try {
            await removeItem(collectionSlug, item.id);
            queryClient.invalidateQueries({ queryKey: ['browse', 'collection', collectionSlug] });
            onRemoved?.();
        } catch (err) {
            console.error('Remove item failed:', err);
        }
    };

    if (item.item_type === 'prompt_version') {
        return (
            <div className="flex items-center justify-between group">
                <Link
                    to={`/workspace/${item.prompt_owner || '_'}/${item.prompt_slug}`}
                    className="flex-1 px-3 py-2 text-xs text-gray-300 hover:bg-gray-700 rounded"
                >
                    <span className="text-gray-100">{item.prompt_name}</span>
                    <span className="text-gray-500 ml-2">v{item.version_number}</span>
                </Link>
                <button
                    onClick={handleRemove}
                    className="text-gray-600 hover:text-red-400 text-xs px-2 opacity-0 group-hover:opacity-100 transition-opacity"
                    title="Remove from collection"
                >
                    &times;
                </button>
            </div>
        );
    }
    if (item.item_type === 'result') {
        return (
            <div className="flex items-center justify-between group">
                <div className="flex-1 px-3 py-2 text-xs text-gray-400">
                    <span className="text-gray-300">{item.prompt_name}</span>
                    <span className="text-gray-500 ml-2">{item.provider_name} / {item.model_name}</span>
                    {item.rating && <span className="text-amber-400 ml-2">{'*'.repeat(item.rating)}</span>}
                </div>
                <button
                    onClick={handleRemove}
                    className="text-gray-600 hover:text-red-400 text-xs px-2 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    &times;
                </button>
            </div>
        );
    }
    if (item.item_type === 'collection') {
        return (
            <div className="flex items-center justify-between group">
                <div className="flex-1 px-3 py-2 text-xs text-gray-400">
                    <span className="text-indigo-400">{item.title}</span>
                    {item.children_count != null && (
                        <span className="text-gray-600 ml-2">{item.children_count} items</span>
                    )}
                </div>
                <button
                    onClick={handleRemove}
                    className="text-gray-600 hover:text-red-400 text-xs px-2 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    &times;
                </button>
            </div>
        );
    }
    return null;
}

function CollectionCard({ collection }) {
    const [expanded, setExpanded] = useState(false);
    const queryClient = useQueryClient();

    const { data: detailData } = useQuery({
        queryKey: ['browse', 'collection', collection.slug],
        queryFn: () => getCollection(collection.slug),
        enabled: expanded,
    });

    const items = detailData?.data?.items ?? [];

    const handleDelete = async (e) => {
        e.stopPropagation();
        if (!confirm(`Delete collection "${collection.title}"?`)) return;
        try {
            await deleteCollection(collection.slug);
            queryClient.invalidateQueries({ queryKey: ['browse', 'collections'] });
        } catch (err) {
            console.error('Delete collection failed:', err);
        }
    };

    return (
        <div className="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
            <button
                onClick={() => setExpanded(!expanded)}
                className="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-750 transition-colors text-left group"
            >
                <div>
                    <h3 className="text-sm font-medium text-gray-100">{collection.title}</h3>
                    {collection.description && (
                        <p className="text-xs text-gray-500 mt-0.5 truncate">{collection.description}</p>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <button
                        onClick={handleDelete}
                        className="text-gray-600 hover:text-red-400 text-xs opacity-0 group-hover:opacity-100 transition-opacity"
                        title="Delete collection"
                    >
                        &times;
                    </button>
                    <span className="text-gray-500 text-xs">{expanded ? '−' : '+'}</span>
                </div>
            </button>
            {expanded && (
                <div className="border-t border-gray-700">
                    {items.length === 0 ? (
                        <p className="px-4 py-3 text-xs text-gray-600">Empty collection</p>
                    ) : (
                        items.map(item => (
                            <CollectionItem
                                key={item.id}
                                item={item}
                                collectionSlug={collection.slug}
                                onRemoved={() => queryClient.invalidateQueries({ queryKey: ['browse', 'collections'] })}
                            />
                        ))
                    )}
                </div>
            )}
        </div>
    );
}

export default function CollectionList() {
    const [showCreate, setShowCreate] = useState(false);
    const [title, setTitle] = useState('');
    const [saving, setSaving] = useState(false);
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['browse', 'collections'],
        queryFn: () => listCollections(),
    });

    const collections = data?.data ?? [];

    const handleCreate = useCallback(async () => {
        if (!title.trim() || saving) return;
        setSaving(true);
        try {
            await createCollection({ title: title.trim() });
            setTitle('');
            setShowCreate(false);
            queryClient.invalidateQueries({ queryKey: ['browse', 'collections'] });
        } catch (err) {
            console.error('Create collection failed:', err);
        } finally {
            setSaving(false);
        }
    }, [title, saving, queryClient]);

    if (isLoading) {
        return (
            <div className="flex justify-center py-12">
                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {/* Create collection */}
            <div className="flex justify-end">
                {showCreate ? (
                    <div className="flex items-center gap-2 w-full">
                        <input
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            placeholder="Collection name..."
                            className="flex-1 bg-gray-800 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') handleCreate();
                                if (e.key === 'Escape') { setShowCreate(false); setTitle(''); }
                            }}
                            autoFocus
                        />
                        <button
                            onClick={handleCreate}
                            disabled={!title.trim() || saving}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded disabled:opacity-50"
                        >
                            Create
                        </button>
                        <button
                            onClick={() => { setShowCreate(false); setTitle(''); }}
                            className="text-gray-500 hover:text-gray-300 text-sm"
                        >
                            &times;
                        </button>
                    </div>
                ) : (
                    <button
                        onClick={() => setShowCreate(true)}
                        className="text-xs text-indigo-400 hover:text-indigo-300"
                    >
                        + New Collection
                    </button>
                )}
            </div>

            {collections.length === 0 && !showCreate && (
                <p className="text-center py-12 text-gray-500">No collections yet.</p>
            )}

            {collections.map(c => <CollectionCard key={c.id} collection={c} />)}
        </div>
    );
}
