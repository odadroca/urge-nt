const LAYERS = [
    { key: 'prompts', label: 'Prompts', color: '#6366f1', bgActive: '#312e81', always: true },
    { key: 'fragments', label: 'Fragments', color: '#3b82f6', bgActive: '#1e3a5f' },
    { key: 'collections', label: 'Collections', color: '#6b7280', bgActive: '#1f2937' },
    { key: 'results', label: 'Results', color: '#22c55e', bgActive: '#14532d' },
    { key: 'evaluations', label: 'Evaluations', color: '#f97316', bgActive: '#431407' },
];

export default function LayerToggles({ activeLayers, onToggle }) {
    return (
        <div className="flex items-center gap-1.5">
            <span className="text-[9px] text-gray-500 uppercase tracking-wider mr-1">Layers</span>
            {LAYERS.map(layer => {
                const isActive = activeLayers.includes(layer.key);
                return (
                    <button
                        key={layer.key}
                        onClick={() => !layer.always && onToggle(layer.key)}
                        className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] transition-all ${
                            layer.always ? 'cursor-default' : 'cursor-pointer'
                        }`}
                        style={{
                            background: isActive ? layer.bgActive : '#1f2937',
                            border: `1px solid ${isActive ? layer.color : '#374151'}`,
                            opacity: isActive ? 1 : 0.4,
                        }}
                    >
                        <div style={{
                            width: 6, height: 6,
                            borderRadius: '50%',
                            background: isActive ? layer.color : '#475569',
                        }} />
                        <span style={{ color: isActive ? layer.color : '#6b7280' }}>
                            {layer.label}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
