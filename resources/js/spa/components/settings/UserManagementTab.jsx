import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listUsers, createUser, updateUser, deleteUser } from '../../api/users.js';
import useAuth from '../../hooks/useAuth.js';

const ROLES = ['admin', 'editor', 'viewer'];

export default function UserManagementTab() {
    const queryClient = useQueryClient();
    const { user: currentUser } = useAuth();
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [createForm, setCreateForm] = useState({ name: '', email: '', password: '', role: 'editor' });
    const [creating, setCreating] = useState(false);
    const [createError, setCreateError] = useState('');

    const { data: usersData, isLoading } = useQuery({
        queryKey: ['settings', 'users'],
        queryFn: listUsers,
    });

    const users = usersData?.data ?? usersData ?? [];

    const handleRoleChange = useCallback(async (id, role) => {
        try {
            await updateUser(id, { role });
            queryClient.invalidateQueries({ queryKey: ['settings', 'users'] });
        } catch (err) {
            console.error('Role update failed:', err);
        }
    }, [queryClient]);

    const handleDelete = useCallback(async (id) => {
        if (!confirm('Delete this user? This cannot be undone.')) return;
        try {
            await deleteUser(id);
            queryClient.invalidateQueries({ queryKey: ['settings', 'users'] });
        } catch (err) {
            console.error('Delete failed:', err);
        }
    }, [queryClient]);

    const handleCreate = async (e) => {
        e.preventDefault();
        if (!createForm.name.trim() || !createForm.email.trim() || !createForm.password.trim()) return;
        setCreating(true);
        setCreateError('');
        try {
            await createUser({
                name: createForm.name.trim(),
                email: createForm.email.trim(),
                password: createForm.password.trim(),
                role: createForm.role,
            });
            queryClient.invalidateQueries({ queryKey: ['settings', 'users'] });
            setCreateForm({ name: '', email: '', password: '', role: 'editor' });
            setShowCreateForm(false);
        } catch (err) {
            setCreateError(err.response?.data?.message || err.response?.data?.error || 'Failed to create user');
        } finally {
            setCreating(false);
        }
    };

    const isSelf = (id) => currentUser && currentUser.id === id;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-200">Users</h2>
                <button
                    onClick={() => { setShowCreateForm(!showCreateForm); setCreateError(''); }}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded"
                >
                    + New User
                </button>
            </div>

            {/* Create form */}
            {showCreateForm && (
                <form onSubmit={handleCreate} className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-xs text-gray-400 uppercase tracking-wider">Create User</span>
                        <button type="button" onClick={() => setShowCreateForm(false)} className="text-gray-500 hover:text-gray-300 text-sm">&times;</button>
                    </div>

                    {createError && (
                        <div className="bg-red-900/50 border border-red-700 text-red-300 text-xs px-2 py-1 rounded">{createError}</div>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">Name</label>
                            <input
                                value={createForm.name}
                                onChange={(e) => setCreateForm(f => ({ ...f, name: e.target.value }))}
                                placeholder="John Doe"
                                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                                autoFocus
                            />
                        </div>
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">Email</label>
                            <input
                                type="email"
                                value={createForm.email}
                                onChange={(e) => setCreateForm(f => ({ ...f, email: e.target.value }))}
                                placeholder="john@example.com"
                                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">Password</label>
                            <input
                                type="password"
                                value={createForm.password}
                                onChange={(e) => setCreateForm(f => ({ ...f, password: e.target.value }))}
                                placeholder="Minimum 8 characters"
                                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">Role</label>
                            <select
                                value={createForm.role}
                                onChange={(e) => setCreateForm(f => ({ ...f, role: e.target.value }))}
                                className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 outline-none focus:border-indigo-500"
                            >
                                {ROLES.map(r => (
                                    <option key={r} value={r}>{r}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setShowCreateForm(false)}
                            className="text-xs text-gray-400 hover:text-gray-200 px-3 py-1.5"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={!createForm.name.trim() || !createForm.email.trim() || !createForm.password.trim() || creating}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-4 py-1.5 rounded disabled:opacity-50"
                        >
                            {creating ? 'Creating...' : 'Create User'}
                        </button>
                    </div>
                </form>
            )}

            {/* User list */}
            {isLoading && (
                <div className="flex justify-center py-8">
                    <div className="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full" />
                </div>
            )}

            {!isLoading && users.length === 0 && (
                <p className="text-sm text-gray-500 text-center py-8">No users found.</p>
            )}

            {!isLoading && users.length > 0 && (
                <div className="space-y-2">
                    {users.map(u => (
                        <div key={u.id} className="bg-gray-800 border border-gray-700 rounded-lg p-3 flex items-center gap-4">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-0.5">
                                    <span className="text-sm text-gray-200 font-medium truncate">{u.name}</span>
                                    {isSelf(u.id) && (
                                        <span className="text-[9px] bg-indigo-900/40 text-indigo-400 px-1.5 py-0.5 rounded font-medium">You</span>
                                    )}
                                </div>
                                <span className="text-[10px] text-gray-500">{u.email}</span>
                            </div>

                            <select
                                value={u.role}
                                onChange={(e) => handleRoleChange(u.id, e.target.value)}
                                disabled={isSelf(u.id)}
                                className={`bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1 outline-none focus:border-indigo-500 ${
                                    isSelf(u.id) ? 'opacity-50 cursor-not-allowed' : ''
                                }`}
                            >
                                {ROLES.map(r => (
                                    <option key={r} value={r}>{r}</option>
                                ))}
                            </select>

                            <button
                                onClick={() => handleDelete(u.id)}
                                disabled={isSelf(u.id)}
                                className={`text-sm ${
                                    isSelf(u.id)
                                        ? 'text-gray-700 cursor-not-allowed'
                                        : 'text-gray-500 hover:text-red-400'
                                }`}
                            >
                                &times;
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
