import { useState, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import useAuth from '../hooks/useAuth.js';
import { getTeam, deleteTeam, addMember, removeMember, leaveTeam } from '../api/teams.js';

export default function TeamDetailPage() {
    const { slug } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { user } = useAuth();

    const [inviteEmail, setInviteEmail] = useState('');
    const [inviting, setInviting] = useState(false);
    const [inviteError, setInviteError] = useState(null);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const { data: teamData, isLoading, error } = useQuery({
        queryKey: ['teams', slug],
        queryFn: () => getTeam(slug),
    });

    const team = teamData?.data;

    const isOwner = team?.members?.some(m => m.id === user?.id && m.role === 'owner') || user?.role === 'admin';
    const isMember = team?.members?.some(m => m.id === user?.id);

    const handleInvite = useCallback(async () => {
        if (!inviteEmail.trim() || inviting) return;
        setInviting(true);
        setInviteError(null);
        try {
            await addMember(slug, { email: inviteEmail.trim() });
            setInviteEmail('');
            queryClient.invalidateQueries({ queryKey: ['teams', slug] });
        } catch (err) {
            setInviteError(err.response?.data?.message || 'Failed to add member');
        } finally {
            setInviting(false);
        }
    }, [inviteEmail, inviting, slug, queryClient]);

    const handleRemoveMember = useCallback(async (userId) => {
        if (!confirm('Remove this member from the team?')) return;
        try {
            await removeMember(slug, userId);
            queryClient.invalidateQueries({ queryKey: ['teams', slug] });
        } catch (err) {
            console.error('Remove member failed:', err);
        }
    }, [slug, queryClient]);

    const handleLeave = useCallback(async () => {
        if (!confirm('Leave this team?')) return;
        try {
            await leaveTeam(slug);
            queryClient.invalidateQueries({ queryKey: ['teams'] });
            navigate('/teams');
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to leave team');
        }
    }, [slug, navigate, queryClient]);

    const handleDelete = useCallback(async () => {
        try {
            await deleteTeam(slug);
            queryClient.invalidateQueries({ queryKey: ['teams'] });
            navigate('/teams');
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to delete team');
        }
    }, [slug, navigate, queryClient]);

    if (isLoading) {
        return (
            <div className="flex justify-center items-center h-full">
                <div className="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    if (error || !team) {
        return (
            <div className="flex flex-col items-center justify-center h-full gap-3">
                <p className="text-gray-500 text-sm">Team not found.</p>
                <Link to="/teams" className="text-indigo-400 text-sm hover:text-indigo-300">Back to Teams</Link>
            </div>
        );
    }

    return (
        <div className="h-full flex flex-col overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between px-6 pt-4 pb-3 border-b border-gray-700">
                <div className="flex items-center gap-3">
                    <Link to="/teams" className="text-gray-500 hover:text-gray-300 text-sm">&larr;</Link>
                    <h1 className="text-lg font-semibold text-gray-100">{team.name}</h1>
                    <span className={`text-xs px-2 py-0.5 rounded ${
                        isOwner ? 'bg-indigo-900/50 text-indigo-400' : 'bg-gray-700 text-gray-400'
                    }`}>
                        {isOwner ? 'Owner' : 'Member'}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    {isMember && !isOwner && (
                        <button
                            onClick={handleLeave}
                            className="text-gray-400 hover:text-red-400 text-sm px-3 py-1.5 rounded border border-gray-700 hover:border-red-900 transition-colors"
                        >
                            Leave Team
                        </button>
                    )}
                    {isOwner && (
                        confirmDelete ? (
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-red-400">Delete this team?</span>
                                <button onClick={handleDelete} className="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1 rounded">Yes, delete</button>
                                <button onClick={() => setConfirmDelete(false)} className="text-gray-400 text-xs px-3 py-1">Cancel</button>
                            </div>
                        ) : (
                            <button
                                onClick={() => setConfirmDelete(true)}
                                className="text-gray-500 hover:text-red-400 text-sm px-3 py-1.5 rounded border border-gray-700 hover:border-red-900 transition-colors"
                            >
                                Delete Team
                            </button>
                        )
                    )}
                </div>
            </div>

            <div className="flex-1 overflow-y-auto p-6">
                <div className="max-w-2xl mx-auto space-y-6">
                    {/* Members section */}
                    <section>
                        <h2 className="text-sm font-semibold text-gray-300 mb-3">
                            Members ({team.members?.length || 0})
                        </h2>

                        {/* Invite form (owner only) */}
                        {isOwner && (
                            <div className="flex gap-2 mb-3">
                                <input
                                    value={inviteEmail}
                                    onChange={(e) => { setInviteEmail(e.target.value); setInviteError(null); }}
                                    placeholder="Add member by email..."
                                    className="flex-1 bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-2 outline-none focus:border-indigo-500"
                                    onKeyDown={(e) => e.key === 'Enter' && handleInvite()}
                                />
                                <button
                                    onClick={handleInvite}
                                    disabled={!inviteEmail.trim() || inviting}
                                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded disabled:opacity-50 transition-colors"
                                >
                                    {inviting ? 'Adding...' : 'Add'}
                                </button>
                            </div>
                        )}
                        {inviteError && <p className="text-red-400 text-xs mb-2">{inviteError}</p>}

                        <div className="space-y-1">
                            {team.members?.map(member => (
                                <div key={member.id} className="flex items-center justify-between bg-gray-800 border border-gray-700 rounded px-4 py-2.5">
                                    <div className="flex items-center gap-3">
                                        <div className="flex items-center justify-center w-7 h-7 rounded-full bg-gray-600 text-white text-xs font-semibold">
                                            {member.name?.charAt(0)?.toUpperCase() || '?'}
                                        </div>
                                        <div>
                                            <span className="text-sm text-gray-200">
                                                {member.name}
                                                {member.id === user?.id && <span className="text-xs text-gray-500 ml-1">(you)</span>}
                                            </span>
                                            <span className="text-xs text-gray-500 ml-2">{member.email}</span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className={`text-xs px-2 py-0.5 rounded ${
                                            member.role === 'owner' ? 'bg-indigo-900/50 text-indigo-400' : 'bg-gray-700 text-gray-400'
                                        }`}>
                                            {member.role}
                                        </span>
                                        {isOwner && member.id !== user?.id && (
                                            <button
                                                onClick={() => handleRemoveMember(member.id)}
                                                className="text-gray-500 hover:text-red-400 text-sm"
                                                title="Remove member"
                                            >
                                                &times;
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Shared prompts section */}
                    <section>
                        <h2 className="text-sm font-semibold text-gray-300 mb-3">
                            Shared Prompts ({team.prompts?.length || 0})
                        </h2>

                        {!team.prompts?.length ? (
                            <p className="text-gray-500 text-xs">No prompts shared with this team yet.</p>
                        ) : (
                            <div className="space-y-1">
                                {team.prompts.map(prompt => (
                                    <Link
                                        key={prompt.id}
                                        to={`/workspace/${prompt.creator?.slug || '_'}/${prompt.slug}`}
                                        className="flex items-center justify-between bg-gray-800 border border-gray-700 rounded px-4 py-2.5 hover:border-gray-600 transition-colors"
                                    >
                                        <div>
                                            <span className="text-sm text-gray-200">{prompt.name}</span>
                                            <span className={`text-xs ml-2 px-1.5 py-0.5 rounded ${
                                                prompt.type === 'fragment' ? 'bg-amber-900/30 text-amber-400' : 'bg-gray-700 text-gray-400'
                                            }`}>
                                                {prompt.type}
                                            </span>
                                        </div>
                                        <span className="text-xs text-gray-500">
                                            {prompt.updated_at ? new Date(prompt.updated_at).toLocaleDateString() : ''}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </div>
    );
}
