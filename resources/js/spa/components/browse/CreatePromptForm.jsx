import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { createPrompt } from '../../api/prompts.js';

export default function CreatePromptForm({ onClose }) {
    const [name, setName] = useState('');
    const [type, setType] = useState('prompt');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!name.trim()) return;
        setSaving(true);
        setError('');
        try {
            const result = await createPrompt({ name: name.trim(), type });
            const prompt = result.data;
            queryClient.invalidateQueries({ queryKey: ['browse'] });
            navigate(`/workspace/${prompt.creator?.username || prompt.created_by}/${prompt.slug}`);
        } catch (err) {
            setError(err.response?.data?.message || 'Create failed');
            setSaving(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="flex items-center gap-2 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2">
            <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Prompt name..."
                autoFocus
                className="flex-1 bg-transparent text-gray-100 text-sm outline-none placeholder-gray-500"
            />
            <select
                value={type}
                onChange={(e) => setType(e.target.value)}
                className="bg-gray-900 border border-gray-600 text-gray-300 text-xs rounded px-2 py-1 outline-none"
            >
                <option value="prompt">Prompt</option>
                <option value="fragment">Fragment</option>
            </select>
            {error && <span className="text-red-400 text-xs">{error}</span>}
            <button
                type="submit"
                disabled={!name.trim() || saving}
                className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1 rounded disabled:opacity-50"
            >
                {saving ? '...' : 'Create'}
            </button>
            <button type="button" onClick={onClose} className="text-gray-500 hover:text-gray-300 text-sm">&times;</button>
        </form>
    );
}
