import { useReactFlow } from '@xyflow/react';

export default function Toolbar({ layoutMode, onLayoutChange, onMermaidExport, onToggleSidebar, isLayouting }) {
    const { fitView } = useReactFlow();

    return (
        <div className="fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-800 border border-gray-700 rounded-xl px-4 py-2 z-30 flex items-center gap-2 shadow-xl">
            <div className="flex bg-gray-900 rounded-lg p-0.5 gap-0.5">
                {[
                    { mode: 'free', label: 'Free' },
                    { mode: 'mrtree', label: 'Tree' },
                    { mode: 'layered', label: 'Layer' },
                ].map(({ mode, label }) => (
                    <button key={mode} onClick={() => onLayoutChange(mode)} disabled={isLayouting}
                        className={`px-3 py-1 text-xs rounded-md transition-colors ${layoutMode === mode ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-gray-200'}`}>
                        {label}
                    </button>
                ))}
            </div>
            <span className="text-gray-600">|</span>
            <button onClick={() => fitView({ duration: 300 })} className="text-gray-400 hover:text-white text-xs flex items-center gap-1">Fit</button>
            <button onClick={onMermaidExport} className="text-gray-400 hover:text-white text-xs flex items-center gap-1">Mermaid</button>
            <span className="text-gray-600">|</span>
            <button onClick={onToggleSidebar} className="text-gray-400 hover:text-white text-xs flex items-center gap-1">Sidebar</button>
        </div>
    );
}
