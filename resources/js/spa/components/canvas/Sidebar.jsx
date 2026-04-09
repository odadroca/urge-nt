import { useState, useMemo } from 'react';

export default function Sidebar({ visible, nodes, layoutMode, onLayoutChange }) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        if (!nodes) return { prompts: [], fragments: [], collections: [] };
        let items = nodes;
        if (search) {
            const q = search.toLowerCase();
            items = items.filter(
                (n) => (n.data.name || n.data.title || '').toLowerCase().includes(q) ||
                       (n.data.slug || '').toLowerCase().includes(q)
            );
        }
        return {
            prompts: items.filter((n) => n.type === 'prompt'),
            fragments: items.filter((n) => n.type === 'fragment'),
            collections: items.filter((n) => n.type === 'collection'),
        };
    }, [nodes, search]);

    const handleDragStart = (event, node) => {
        event.dataTransfer.setData(
            'application/reactflow',
            JSON.stringify({ nodeType: node.type, nodeId: node.id, data: node.data })
        );
        event.dataTransfer.effectAllowed = 'move';
    };

    if (!visible) return null;

    return (
        <div className="fixed left-0 top-12 bottom-0 w-72 bg-gray-800 border-r border-gray-700 z-30 flex flex-col overflow-hidden">
            <div className="p-3 border-b border-gray-700">
                <div className="flex items-center justify-between mb-2">
                    <span className="font-semibold text-sm text-gray-100">Nodes</span>
                    <span className="text-[10px] text-gray-500">Ctrl+B</span>
                </div>
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search prompts..."
                    className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-2.5 py-1.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                />
            </div>

            <div className="flex-1 overflow-y-auto p-3 space-y-4">
                {filtered.prompts.length > 0 && (
                    <Section title="Prompts" items={filtered.prompts} onDragStart={handleDragStart} />
                )}
                {filtered.fragments.length > 0 && (
                    <Section title="Fragments" items={filtered.fragments} onDragStart={handleDragStart} />
                )}
                {filtered.collections.length > 0 && (
                    <Section title="Collections" items={filtered.collections} onDragStart={handleDragStart} />
                )}
                {filtered.prompts.length === 0 && filtered.fragments.length === 0 && filtered.collections.length === 0 && (
                    <p className="text-gray-500 text-sm text-center py-4">No matches</p>
                )}
            </div>

            <div className="p-3 border-t border-gray-700">
                <div className="text-[9px] text-gray-500 uppercase tracking-wider mb-2">Layout</div>
                <div className="flex gap-1">
                    {['free', 'mrtree', 'layered'].map((mode) => (
                        <button
                            key={mode}
                            onClick={() => onLayoutChange(mode)}
                            className={`flex-1 py-1.5 text-xs rounded ${
                                layoutMode === mode
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                            }`}
                        >
                            {mode === 'free' ? 'Free' : mode === 'mrtree' ? 'Tree' : 'Layer'}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}

function Section({ title, items, onDragStart }) {
    return (
        <div>
            <div className="text-[9px] text-gray-500 uppercase tracking-wider mb-1.5">{title}</div>
            <div className="space-y-1">
                {items.map((node) => (
                    <div
                        key={node.id}
                        draggable
                        onDragStart={(e) => onDragStart(e, node)}
                        className="flex items-center gap-2 bg-gray-700/50 hover:bg-gray-700 rounded px-2 py-1.5 cursor-grab text-xs"
                    >
                        {node.type === 'fragment' ? (
                            <span className="text-blue-400">&#9671;</span>
                        ) : node.type === 'collection' ? (
                            <span className="text-purple-400">&#9633;</span>
                        ) : (
                            <span
                                className="w-0.5 h-3.5 rounded-full shrink-0"
                                style={{ backgroundColor: node.data.category?.color || '#6366f1' }}
                            />
                        )}
                        <span className="text-gray-200 truncate">{node.data.name || node.data.title}</span>
                        <span className="ml-auto text-gray-500 text-[10px] shrink-0">
                            {node.type === 'fragment' && `\u00d7${node.data.incomingEdgeCount || 0}`}
                            {node.type === 'prompt' && `v${node.data.active_version?.version_number || '?'}`}
                            {node.type === 'collection' && `${node.data.items_count || 0}`}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
