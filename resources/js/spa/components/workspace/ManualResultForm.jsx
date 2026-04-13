import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { createResult } from '../../api/results.js';

export default function ManualResultForm({ username, slug, currentVersionNumber, onClose }) {
    const queryClient = useQueryClient();
    const [responseText, setResponseText] = useState('');
    const [providerName, setProviderName] = useState('');
    const [modelName, setModelName] = useState('');
    const [notes, setNotes] = useState('');
    const [rating, setRating] = useState(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const handleSave = async () => {
        if (!responseText.trim()) return;
        if (!currentVersionNumber) { setError('Save a version first'); return; }
        setSaving(true);
        setError('');
        try {
            await createResult(username, slug, {
                version: currentVersionNumber,
                response_text: responseText,
                source: 'manual',
                provider_name: providerName || null,
                model_name: modelName || null,
                notes: notes || null,
                rating: rating,
            });
            queryClient.invalidateQueries({ queryKey: ['workspace'] });
            // Reset form
            setResponseText('');
            setProviderName('');
            setModelName('');
            setNotes('');
            setRating(null);
            onClose?.();
        } catch (err) {
            setError(err.response?.data?.error || 'Save failed');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="p-3 space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-xs text-gray-400 uppercase tracking-wider">Add Result Manually</span>
                <button onClick={onClose} className="text-gray-500 hover:text-gray-300 text-sm">&times;</button>
            </div>

            {error && (
                <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-2 py-1 rounded">{error}</div>
            )}

            <textarea
                value={responseText}
                onChange={(e) => setResponseText(e.target.value)}
                placeholder="Paste the LLM response..."
                rows={6}
                className="w-full bg-gray-900 border border-gray-600 text-gray-100 text-xs rounded px-2 py-1.5 resize-none outline-none focus:border-indigo-500"
            />

            <div className="flex gap-2">
                <input
                    value={providerName}
                    onChange={(e) => setProviderName(e.target.value)}
                    placeholder="Provider"
                    className="flex-1 bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1 outline-none"
                />
                <input
                    value={modelName}
                    onChange={(e) => setModelName(e.target.value)}
                    placeholder="Model"
                    className="flex-1 bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1 outline-none"
                />
            </div>

            <textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Notes (optional)"
                rows={2}
                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1.5 resize-none outline-none"
            />

            <div className="flex items-center gap-1">
                <span className="text-[10px] text-gray-500 mr-1">Rating:</span>
                {[1, 2, 3, 4, 5].map(n => (
                    <button
                        key={n}
                        onClick={() => setRating(rating === n ? null : n)}
                        className={`text-sm ${n <= (rating || 0) ? 'text-amber-400' : 'text-gray-600 hover:text-amber-400'}`}
                    >
                        &#9733;
                    </button>
                ))}
            </div>

            <button
                onClick={handleSave}
                disabled={!responseText.trim() || saving}
                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs py-1.5 rounded disabled:opacity-50"
            >
                {saving ? 'Saving...' : 'Save Result'}
            </button>
        </div>
    );
}
