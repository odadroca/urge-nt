export default function AutocompleteDropdown({ items, selectedIndex, position, triggerType, onSelect }) {
    if (!items.length) return null;

    return (
        <div
            className="absolute z-40 bg-gray-800 border border-gray-600 rounded-lg shadow-xl overflow-hidden"
            style={{
                top: `${position.top}px`,
                left: `${position.left}px`,
                minWidth: '180px',
                maxWidth: '280px',
                maxHeight: '200px',
            }}
        >
            <div className="px-2 py-1 border-b border-gray-700">
                <span className="text-[10px] text-gray-500 uppercase tracking-wider">
                    {triggerType === 'fragment' ? 'Fragments' : 'Variables'}
                </span>
            </div>
            <div className="overflow-y-auto" style={{ maxHeight: '168px' }}>
                {items.map((item, i) => (
                    <button
                        key={item.value}
                        onMouseDown={(e) => { e.preventDefault(); onSelect(item); }}
                        className={`w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 transition-colors ${
                            i === selectedIndex
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-300 hover:bg-gray-700'
                        }`}
                    >
                        <span className={`shrink-0 text-[9px] px-1 py-0.5 rounded font-medium ${
                            item.type === 'fragment'
                                ? 'bg-amber-900/50 text-amber-400'
                                : 'bg-blue-900/50 text-blue-400'
                        }`}>
                            {item.type === 'fragment' ? '>' : '$'}
                        </span>
                        <span className="truncate font-mono">{item.value}</span>
                        {item.label !== item.value && (
                            <span className="text-[10px] text-gray-500 truncate ml-auto">{item.label}</span>
                        )}
                    </button>
                ))}
            </div>
        </div>
    );
}
