import { useState, useMemo } from 'react';
import { diffWords, diffChars } from 'diff';

export default function DiffViewer({ oldVersion, newVersion, oldLabel, newLabel, onClose }) {
    const [mode, setMode] = useState('words');

    const oldText = oldVersion?.content || '';
    const newText = newVersion?.content || '';

    const changes = useMemo(() => {
        return mode === 'words' ? diffWords(oldText, newText) : diffChars(oldText, newText);
    }, [oldText, newText, mode]);

    const stats = useMemo(() => {
        let added = 0;
        let removed = 0;
        for (const part of changes) {
            if (part.added) added += part.value.length;
            if (part.removed) removed += part.value.length;
        }
        return { added, removed };
    }, [changes]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div className="bg-gray-800 border border-gray-700 rounded-lg shadow-2xl w-full max-w-3xl max-h-[80vh] flex flex-col mx-4">
                {/* Header */}
                <div className="flex items-center justify-between px-4 py-3 border-b border-gray-700">
                    <div className="flex items-center gap-3">
                        <h3 className="text-sm font-semibold text-gray-200">Diff</h3>
                        <span className="text-xs text-gray-500">
                            {oldLabel} &rarr; {newLabel}
                        </span>
                    </div>
                    <div className="flex items-center gap-3">
                        {/* Mode toggle */}
                        <div className="flex bg-gray-900 rounded text-xs">
                            <button
                                onClick={() => setMode('words')}
                                className={`px-2.5 py-1 rounded-l transition-colors ${
                                    mode === 'words' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-gray-200'
                                }`}
                            >
                                Words
                            </button>
                            <button
                                onClick={() => setMode('chars')}
                                className={`px-2.5 py-1 rounded-r transition-colors ${
                                    mode === 'chars' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-gray-200'
                                }`}
                            >
                                Chars
                            </button>
                        </div>
                        {/* Stats */}
                        <span className="text-xs text-gray-500">
                            <span className="text-green-400">+{stats.added}</span>
                            {' / '}
                            <span className="text-red-400">-{stats.removed}</span>
                        </span>
                        {/* Close */}
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-200 text-lg leading-none"
                        >
                            &times;
                        </button>
                    </div>
                </div>

                {/* Diff content */}
                <div className="flex-1 overflow-y-auto p-4">
                    <pre className="text-sm font-mono whitespace-pre-wrap leading-relaxed">
                        {changes.map((part, i) => {
                            if (part.added) {
                                return (
                                    <span key={i} className="bg-green-900/40 text-green-300 rounded-sm px-0.5">
                                        {part.value}
                                    </span>
                                );
                            }
                            if (part.removed) {
                                return (
                                    <span key={i} className="bg-red-900/40 text-red-300 line-through rounded-sm px-0.5">
                                        {part.value}
                                    </span>
                                );
                            }
                            return <span key={i} className="text-gray-300">{part.value}</span>;
                        })}
                    </pre>
                </div>
            </div>
        </div>
    );
}
