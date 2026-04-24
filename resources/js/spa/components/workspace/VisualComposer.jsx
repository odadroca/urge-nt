import { useState, useEffect, useRef, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import Sortable from 'sortablejs';
import client from '../../api/client.js';

const BLOCK_PATTERN = /(\{\{>[a-zA-Z0-9_-]+\}\}|\{\{[a-zA-Z_][a-zA-Z0-9_]*\}\})/g;

let blockIdCounter = 0;
function nextId() {
    return `b_${++blockIdCounter}_${Date.now()}`;
}

function parseContent(text) {
    if (!text) return [{ id: nextId(), type: 'text', value: '' }];

    const parts = text.split(BLOCK_PATTERN);
    const blocks = [];

    for (const part of parts) {
        if (!part) continue;

        const includeMatch = part.match(/^\{\{>([a-zA-Z0-9_-]+)\}\}$/);
        if (includeMatch) {
            blocks.push({ id: nextId(), type: 'include', value: includeMatch[1] });
            continue;
        }

        const varMatch = part.match(/^\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}$/);
        if (varMatch) {
            blocks.push({ id: nextId(), type: 'variable', value: varMatch[1] });
            continue;
        }

        blocks.push({ id: nextId(), type: 'text', value: part });
    }

    return blocks.length > 0 ? blocks : [{ id: nextId(), type: 'text', value: '' }];
}

function serialize(blocks) {
    return blocks.map(b => {
        if (b.type === 'variable') return `{{${b.value}}}`;
        if (b.type === 'include') return `{{>${b.value}}}`;
        return b.value;
    }).join('');
}

export default function VisualComposer({ content, onChange }) {
    const [blocks, setBlocks] = useState(() => parseContent(content));
    const [addingType, setAddingType] = useState(null);
    const [addingValue, setAddingValue] = useState('');
    const [suggestionIndex, setSuggestionIndex] = useState(0);

    const { data: fragmentsData } = useQuery({
        queryKey: ['autocomplete', 'fragments'],
        queryFn: async () => { const { data } = await client.get('/internal/fragments', { baseURL: '' }); return data; },
        staleTime: 60000,
    });

    const { data: variablesData } = useQuery({
        queryKey: ['autocomplete', 'variables'],
        queryFn: async () => { const { data } = await client.get('/internal/variables', { baseURL: '' }); return data; },
        staleTime: 60000,
    });

    const fragments = fragmentsData ?? [];
    const variables = variablesData ?? [];
    const containerRef = useRef(null);
    const sortableRef = useRef(null);

    // Sync blocks → content whenever blocks change
    useEffect(() => {
        const serialized = serialize(blocks);
        onChange(serialized);
    }, [blocks]);

    // Re-parse when content changes externally (e.g., version switch)
    const prevContentRef = useRef(content);
    useEffect(() => {
        const serialized = serialize(blocks);
        if (content !== serialized && content !== prevContentRef.current) {
            setBlocks(parseContent(content));
        }
        prevContentRef.current = content;
    }, [content]);

    // Initialize Sortable.js
    useEffect(() => {
        if (!containerRef.current) return;

        sortableRef.current = Sortable.create(containerRef.current, {
            handle: '.composer-handle',
            animation: 150,
            ghostClass: 'opacity-30',
            onEnd: (evt) => {
                setBlocks(prev => {
                    const updated = [...prev];
                    const [moved] = updated.splice(evt.oldIndex, 1);
                    updated.splice(evt.newIndex, 0, moved);
                    return updated;
                });
            },
        });

        return () => {
            sortableRef.current?.destroy();
        };
    }, []);

    const updateBlock = useCallback((id, value) => {
        setBlocks(prev => prev.map(b => b.id === id ? { ...b, value } : b));
    }, []);

    const removeBlock = useCallback((id) => {
        setBlocks(prev => {
            const updated = prev.filter(b => b.id !== id);
            return updated.length > 0 ? updated : [{ id: nextId(), type: 'text', value: '' }];
        });
    }, []);

    const addBlock = useCallback((type, value) => {
        if (!value.trim()) return;
        setBlocks(prev => [...prev, { id: nextId(), type, value: value.trim() }]);
        setAddingType(null);
        setAddingValue('');
    }, []);

    const addTextBlock = useCallback(() => {
        setBlocks(prev => [...prev, { id: nextId(), type: 'text', value: '' }]);
    }, []);

    return (
        <div className="flex flex-col h-full">
            {/* Block list */}
            <div ref={containerRef} className="flex-1 overflow-y-auto p-3 space-y-1.5">
                {blocks.map(block => (
                    <div key={block.id} className="flex items-start gap-1.5 group" data-id={block.id}>
                        {/* Drag handle */}
                        <div className="composer-handle cursor-grab active:cursor-grabbing pt-1.5 text-gray-600 hover:text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </div>

                        {/* Block content */}
                        <div className="flex-1 min-w-0">
                            {block.type === 'text' ? (
                                <textarea
                                    value={block.value}
                                    onChange={(e) => updateBlock(block.id, e.target.value)}
                                    rows={Math.max(1, block.value.split('\n').length)}
                                    className="w-full bg-gray-900 text-gray-100 font-mono text-sm px-3 py-2 rounded border border-gray-700 resize-none outline-none focus:border-gray-500"
                                    placeholder="Text block..."
                                />
                            ) : (
                                <div className={`flex items-center gap-2 px-3 py-2 rounded border ${
                                    block.type === 'variable'
                                        ? 'bg-blue-900/20 border-blue-800 text-blue-300'
                                        : 'bg-amber-900/20 border-amber-800 text-amber-300'
                                }`}>
                                    <span className={`text-[9px] font-bold px-1 py-0.5 rounded ${
                                        block.type === 'variable' ? 'bg-blue-900/50 text-blue-400' : 'bg-amber-900/50 text-amber-400'
                                    }`}>
                                        {block.type === 'variable' ? 'VAR' : 'INC'}
                                    </span>
                                    <code className="text-sm font-mono">
                                        {block.type === 'variable' ? `{{${block.value}}}` : `{{>${block.value}}}`}
                                    </code>
                                </div>
                            )}
                        </div>

                        {/* Delete button */}
                        <button
                            onClick={() => removeBlock(block.id)}
                            className="pt-1.5 text-gray-600 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Remove block"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                ))}
            </div>

            {/* Add block toolbar */}
            <div className="border-t border-gray-700 px-3 py-2">
                {addingType ? (
                    <AddBlockForm
                        type={addingType}
                        value={addingValue}
                        onChange={setAddingValue}
                        suggestions={addingType === 'include' ? fragments : variables}
                        suggestionIndex={suggestionIndex}
                        onSuggestionIndexChange={setSuggestionIndex}
                        onAdd={(val) => { addBlock(addingType, val); setSuggestionIndex(0); }}
                        onCancel={() => { setAddingType(null); setAddingValue(''); setSuggestionIndex(0); }}
                    />
                ) : (
                    <div className="flex items-center gap-2">
                        <span className="text-[10px] text-gray-500">Add:</span>
                        <button
                            onClick={addTextBlock}
                            className="text-xs text-gray-400 hover:text-gray-200 px-2 py-0.5 rounded hover:bg-gray-700"
                        >
                            + Text
                        </button>
                        <button
                            onClick={() => setAddingType('variable')}
                            className="text-xs text-blue-400 hover:text-blue-300 px-2 py-0.5 rounded hover:bg-gray-700"
                        >
                            + Variable
                        </button>
                        <button
                            onClick={() => setAddingType('include')}
                            className="text-xs text-amber-400 hover:text-amber-300 px-2 py-0.5 rounded hover:bg-gray-700"
                        >
                            + Include
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

function AddBlockForm({ type, value, onChange, suggestions, suggestionIndex, onSuggestionIndexChange, onAdd, onCancel }) {
    const isInclude = type === 'include';
    const isVariable = type === 'variable';
    const items = isInclude
        ? suggestions.map(f => ({ value: f.slug, label: f.name }))
        : suggestions.map(v => ({ value: v, label: v }));

    const q = value.toLowerCase();
    const filtered = q ? items.filter(i => i.value.toLowerCase().includes(q) || i.label.toLowerCase().includes(q)) : items;

    const handleKeyDown = (e) => {
        if (filtered.length > 0 && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault();
            const delta = e.key === 'ArrowDown' ? 1 : -1;
            onSuggestionIndexChange((suggestionIndex + delta + filtered.length) % filtered.length);
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (filtered.length > 0 && filtered[suggestionIndex]) {
                onAdd(filtered[suggestionIndex].value);
            } else if (value.trim()) {
                onAdd(value.trim());
            }
            return;
        }
        if (e.key === 'Tab' && filtered.length > 0) {
            e.preventDefault();
            onChange(filtered[suggestionIndex].value);
            return;
        }
        if (e.key === 'Escape') {
            onCancel();
        }
    };

    return (
        <div className="relative">
            <div className="flex items-center gap-2">
                <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${
                    isVariable ? 'bg-blue-900/50 text-blue-400' : 'bg-amber-900/50 text-amber-400'
                }`}>
                    {isVariable ? 'VAR' : 'INC'}
                </span>
                <input
                    value={value}
                    onChange={(e) => { onChange(e.target.value); onSuggestionIndexChange(0); }}
                    onKeyDown={handleKeyDown}
                    placeholder={isVariable ? 'variable_name' : 'fragment_slug'}
                    className="flex-1 bg-gray-900 border border-gray-600 text-gray-200 text-xs font-mono rounded px-2 py-1 outline-none focus:border-indigo-500"
                    autoFocus
                />
                <button
                    onClick={() => {
                        if (filtered.length > 0 && filtered[suggestionIndex]) {
                            onAdd(filtered[suggestionIndex].value);
                        } else if (value.trim()) {
                            onAdd(value.trim());
                        }
                    }}
                    disabled={!value.trim() && filtered.length === 0}
                    className="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 rounded disabled:opacity-50"
                >
                    Add
                </button>
                <button onClick={onCancel} className="text-xs text-gray-500 hover:text-gray-300">
                    Cancel
                </button>
            </div>

            {filtered.length > 0 && (
                <div className="absolute bottom-full left-0 mb-1 bg-gray-800 border border-gray-600 rounded-lg shadow-xl overflow-hidden z-40"
                     style={{ minWidth: '200px', maxHeight: '160px' }}>
                    <div className="overflow-y-auto" style={{ maxHeight: '160px' }}>
                        {filtered.map((item, i) => (
                            <button
                                key={item.value}
                                onMouseDown={(e) => { e.preventDefault(); onAdd(item.value); }}
                                className={`w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors ${
                                    i === suggestionIndex
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-300 hover:bg-gray-700'
                                }`}
                            >
                                <span className={`shrink-0 text-[9px] px-1 py-0.5 rounded font-medium ${
                                    isInclude ? 'bg-amber-900/50 text-amber-400' : 'bg-blue-900/50 text-blue-400'
                                }`}>
                                    {isInclude ? '>' : '$'}
                                </span>
                                <span className="font-mono truncate">{item.value}</span>
                                {item.label !== item.value && (
                                    <span className="text-[10px] text-gray-500 truncate ml-auto">{item.label}</span>
                                )}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
