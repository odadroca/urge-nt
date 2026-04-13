import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { listCollections, getCollection } from '../../api/collections.js';

function CollectionItem({ item }) {
    if (item.item_type === 'prompt_version') {
        return (
            <Link
                to={`/workspace/${item.prompt_owner || '_'}/${item.prompt_slug}`}
                className="block px-3 py-2 text-xs text-gray-300 hover:bg-gray-700 rounded"
            >
                <span className="text-gray-100">{item.prompt_name}</span>
                <span className="text-gray-500 ml-2">v{item.version_number}</span>
            </Link>
        );
    }
    if (item.item_type === 'result') {
        return (
            <div className="px-3 py-2 text-xs text-gray-400">
                <span className="text-gray-300">{item.prompt_name}</span>
                <span className="text-gray-500 ml-2">{item.provider_name} / {item.model_name}</span>
                {item.rating && <span className="text-amber-400 ml-2">{'*'.repeat(item.rating)}</span>}
            </div>
        );
    }
    if (item.item_type === 'collection') {
        return (
            <div className="px-3 py-2 text-xs text-gray-400">
                <span className="text-indigo-400">{item.title}</span>
                {item.children_count != null && (
                    <span className="text-gray-600 ml-2">{item.children_count} items</span>
                )}
            </div>
        );
    }
    return null;
}

function CollectionCard({ collection }) {
    const [expanded, setExpanded] = useState(false);

    const { data: detailData } = useQuery({
        queryKey: ['browse', 'collection', collection.slug],
        queryFn: () => getCollection(collection.slug),
        enabled: expanded,
    });

    const items = detailData?.data?.items ?? [];

    return (
        <div className="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
            <button
                onClick={() => setExpanded(!expanded)}
                className="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-750 transition-colors text-left"
            >
                <div>
                    <h3 className="text-sm font-medium text-gray-100">{collection.title}</h3>
                    {collection.description && (
                        <p className="text-xs text-gray-500 mt-0.5 truncate">{collection.description}</p>
                    )}
                </div>
                <span className="text-gray-500 text-xs">{expanded ? '−' : '+'}</span>
            </button>
            {expanded && (
                <div className="border-t border-gray-700">
                    {items.length === 0 ? (
                        <p className="px-4 py-3 text-xs text-gray-600">Empty collection</p>
                    ) : (
                        items.map(item => <CollectionItem key={item.id} item={item} />)
                    )}
                </div>
            )}
        </div>
    );
}

export default function CollectionList() {
    const { data, isLoading } = useQuery({
        queryKey: ['browse', 'collections'],
        queryFn: () => listCollections(),
    });

    const collections = data?.data ?? [];

    if (isLoading) {
        return (
            <div className="flex justify-center py-12">
                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    if (collections.length === 0) {
        return <p className="text-center py-12 text-gray-500">No collections yet.</p>;
    }

    return (
        <div className="space-y-3">
            {collections.map(c => <CollectionCard key={c.id} collection={c} />)}
        </div>
    );
}
