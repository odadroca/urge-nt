import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { listTeams, createTeam } from '../api/teams.js';

export default function TeamsPage() {
    const queryClient = useQueryClient();
    const [showCreate, setShowCreate] = useState(false);
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const { data: teamsData, isLoading } = useQuery({
        queryKey: ['teams'],
        queryFn: listTeams,
    });

    const teams = teamsData?.data ?? [];

    const handleCreate = useCallback(async () => {
        if (!name.trim() || saving) return;
        setSaving(true);
        setError(null);
        try {
            await createTeam({ name: name.trim() });
            setName('');
            setShowCreate(false);
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to create team');
        } finally {
            setSaving(false);
        }
    }, [name, saving, queryClient]);

    return (
        <div className="h-full flex flex-col overflow-hidden">
            <div className="flex items-center justify-between px-6 pt-4 pb-3 border-b border-gray-700">
                <h1 className="text-lg font-semibold text-gray-100">Teams</h1>
                <button
                    onClick={() => setShowCreate(!showCreate)}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-1.5 rounded transition-colors"
                >
                    + New Team
                </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6">
                <div className="max-w-2xl mx-auto space-y-4">
                    {/* Create form */}
                    {showCreate && (
                        <div className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
                            <input
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="Team name..."
                                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-2 outline-none focus:border-indigo-500"
                                onKeyDown={(e) => e.key === 'Enter' && handleCreate()}
                                autoFocus
                            />
                            {error && <p className="text-red-400 text-xs">{error}</p>}
                            <div className="flex gap-2">
                                <button
                                    onClick={handleCreate}
                                    disabled={!name.trim() || saving}
                                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-1.5 rounded disabled:opacity-50 transition-colors"
                                >
                                    {saving ? 'Creating...' : 'Create Team'}
                                </button>
                                <button
                                    onClick={() => { setShowCreate(false); setError(null); }}
                                    className="text-gray-400 text-sm px-4 py-1.5 hover:text-gray-300"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Teams list */}
                    {isLoading ? (
                        <div className="flex justify-center py-12">
                            <div className="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full" />
                        </div>
                    ) : teams.length === 0 ? (
                        <div className="text-center py-12">
                            <p className="text-gray-500 text-sm">No teams yet. Create one to start collaborating.</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {teams.map(team => (
                                <Link
                                    key={team.id}
                                    to={`/teams/${team.slug}`}
                                    className="block bg-gray-800 border border-gray-700 rounded-lg px-5 py-4 hover:border-gray-600 transition-colors"
                                >
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-200">{team.name}</h3>
                                            <div className="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                                <span>{team.member_count} member{team.member_count !== 1 ? 's' : ''}</span>
                                                <span>{team.prompt_count} prompt{team.prompt_count !== 1 ? 's' : ''}</span>
                                            </div>
                                        </div>
                                        <span className={`text-xs px-2 py-0.5 rounded ${
                                            team.role === 'owner'
                                                ? 'bg-indigo-900/50 text-indigo-400'
                                                : 'bg-gray-700 text-gray-400'
                                        }`}>
                                            {team.role}
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
