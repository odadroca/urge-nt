import { useState, useMemo } from 'react';

const LAYERS = [
    { key: 'prompts', label: 'Prompts', color: '#6366f1', always: true },
    { key: 'fragments', label: 'Fragments', color: '#3b82f6' },
    { key: 'collections', label: 'Collections', color: '#6b7280' },
    { key: 'results', label: 'Results', color: '#22c55e' },
    { key: 'evaluations', label: 'Evaluations', color: '#f97316' },
];

export default function Sidebar({ visible, nodes, layoutMode, onLayoutChange, activeLayers, onToggleLayer, hiddenPrompts, onTogglePromptVisibility }) {
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
                    <span className="font-semibold text-sm text-gray-100">Canvas</span>
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

            {/* Layer toggles */}
            <div className="p-3 border-b border-gray-700">
                <div className="text-[9px] text-gray-500 uppercase tracking-wider mb-2">Layers</div>
                <div className="flex flex-wrap gap-1">
                    {LAYERS.map(layer => {
                        const isActive = activeLayers?.includes(layer.key);
                        return (
                            <button
                                key={layer.key}
                                onClick={() => !layer.always && onToggleLayer?.(layer.key)}
                                className={`flex items-center gap-1 px-2 py-1 rounded-full text-[10px] transition-all ${
                                    layer.always ? 'cursor-default' : 'cursor-pointer'
                                }`}
                                style={{
                                    border: `1px solid ${isActive ? layer.color : '#374151'}`,
                                    opacity: isActive ? 1 : 0.35,
                                }}
                            >
                                <div style={{
                                    width: 5, height: 5, borderRadius: '50%',
                                    background: isActive ? layer.color : '#475569',
                                }} />
                                <span style={{ color: isActive ? layer.color : '#6b7280' }}>
                                    {layer.label}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Node list with visibility toggles */}
            <div className="flex-1 overflow-y-auto p-3 space-y-4">
                {filtered.prompts.length > 0 && (
                    <Section
                        title="Prompts"
                        items={filtered.prompts}
                        onDragStart={handleDragStart}
                        hiddenIds={hiddenPrompts}
                        onToggleVisibility={onTogglePromptVisibility}
                    />
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

function Section({ title, items, onDragStart, hiddenIds, onToggleVisibility }) {
    return (
        <div>
            <div className="text-[9px] text-gray-500 uppercase tracking-wider mb-1.5">{title}</div>
            <div className="space-y-1">
                {items.map((node) => {
                    const nodeNumId = parseInt(node.id.split('-')[1]);
                    const isHidden = hiddenIds?.has(nodeNumId);
                    return (
                        <div
                            key={node.id}
                            draggable
                            onDragStart={(e) => onDragStart(e, node)}
                            className={`flex items-center gap-2 bg-gray-700/50 hover:bg-gray-700 rounded px-2 py-1.5 cursor-grab text-xs ${isHidden ? 'opacity-40' : ''}`}
                        >
                            {onToggleVisibility && (
                                <button
                                    onClick={(e) => { e.stopPropagation(); onToggleVisibility(nodeNumId); }}
                                    className="shrink-0 text-[10px] hover:text-indigo-400 transition-colors"
                                    title={isHidden ? 'Show on canvas' : 'Hide from canvas'}
                                    draggable={false}
                                    onDragStart={(e) => e.preventDefault()}
                                >
                                    {isHidden ? (
                                        <svg className="w-3.5 h-3.5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                    ) : (
                                        <svg className="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    )}
                                </button>
                            )}
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
                    );
                })}
            </div>
        </div>
    );
}
