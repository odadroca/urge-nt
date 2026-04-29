import { useState, useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import useAuth from '../../hooks/useAuth.js';
import { updateProfile, updatePassword, deleteAccount } from '../../api/profile.js';

export default function ProfileTab() {
    const { user } = useAuth();

    return (
        <div className="max-w-2xl space-y-10">
            <ProfileInformationSection user={user} />
            <div className="border-t border-gray-700" />
            <PasswordSection />
            <div className="border-t border-gray-700" />
            <DeleteAccountSection />
        </div>
    );
}

function extractError(err, fallback = 'Something went wrong.') {
    const data = err?.response?.data;
    if (data?.errors && typeof data.errors === 'object') {
        const first = Object.values(data.errors).flat()[0];
        if (first) return first;
    }
    return data?.message || data?.error || fallback;
}

function ProfileInformationSection({ user }) {
    const queryClient = useQueryClient();
    const [form, setForm] = useState({ name: '', email: '' });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [saved, setSaved] = useState(false);

    useEffect(() => {
        if (user) setForm({ name: user.name || '', email: user.email || '' });
    }, [user?.id]);

    useEffect(() => {
        if (!saved) return;
        const id = setTimeout(() => setSaved(false), 2500);
        return () => clearTimeout(id);
    }, [saved]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (saving) return;
        setSaving(true);
        setError('');
        try {
            await updateProfile({ name: form.name.trim(), email: form.email.trim() });
            queryClient.invalidateQueries({ queryKey: ['auth'] });
            setSaved(true);
        } catch (err) {
            setError(extractError(err, 'Could not update profile.'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <section>
            <header className="mb-4">
                <h2 className="text-lg font-medium text-gray-100">Profile Information</h2>
                <p className="mt-1 text-sm text-gray-400">Update your name and email address.</p>
            </header>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <label htmlFor="profile-name" className="block text-sm font-medium text-gray-300 mb-1">Name</label>
                    <input
                        id="profile-name"
                        type="text"
                        required
                        autoComplete="name"
                        value={form.name}
                        onChange={(e) => setForm(f => ({ ...f, name: e.target.value }))}
                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded-md px-3 py-2 focus:border-indigo-500 outline-none"
                    />
                </div>
                <div>
                    <label htmlFor="profile-email" className="block text-sm font-medium text-gray-300 mb-1">Email</label>
                    <input
                        id="profile-email"
                        type="email"
                        required
                        autoComplete="email"
                        value={form.email}
                        onChange={(e) => setForm(f => ({ ...f, email: e.target.value }))}
                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded-md px-3 py-2 focus:border-indigo-500 outline-none"
                    />
                </div>
                {error && <p className="text-red-400 text-xs">{error}</p>}
                <div className="flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={saving}
                        className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg disabled:opacity-50"
                    >
                        {saving ? 'Saving...' : 'Save'}
                    </button>
                    {saved && <p className="text-sm text-green-400">Saved.</p>}
                </div>
            </form>
        </section>
    );
}

function PasswordSection() {
    const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [saved, setSaved] = useState(false);

    useEffect(() => {
        if (!saved) return;
        const id = setTimeout(() => setSaved(false), 2500);
        return () => clearTimeout(id);
    }, [saved]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (saving) return;
        setSaving(true);
        setError('');
        try {
            await updatePassword(form);
            setForm({ current_password: '', password: '', password_confirmation: '' });
            setSaved(true);
        } catch (err) {
            setError(extractError(err, 'Could not update password.'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <section>
            <header className="mb-4">
                <h2 className="text-lg font-medium text-gray-100">Update Password</h2>
                <p className="mt-1 text-sm text-gray-400">Use a strong, unique password to keep your account secure.</p>
            </header>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <label htmlFor="current-password" className="block text-sm font-medium text-gray-300 mb-1">Current Password</label>
                    <input
                        id="current-password"
                        type="password"
                        autoComplete="current-password"
                        value={form.current_password}
                        onChange={(e) => setForm(f => ({ ...f, current_password: e.target.value }))}
                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded-md px-3 py-2 focus:border-indigo-500 outline-none"
                    />
                </div>
                <div>
                    <label htmlFor="new-password" className="block text-sm font-medium text-gray-300 mb-1">New Password</label>
                    <input
                        id="new-password"
                        type="password"
                        autoComplete="new-password"
                        value={form.password}
                        onChange={(e) => setForm(f => ({ ...f, password: e.target.value }))}
                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded-md px-3 py-2 focus:border-indigo-500 outline-none"
                    />
                </div>
                <div>
                    <label htmlFor="confirm-password" className="block text-sm font-medium text-gray-300 mb-1">Confirm Password</label>
                    <input
                        id="confirm-password"
                        type="password"
                        autoComplete="new-password"
                        value={form.password_confirmation}
                        onChange={(e) => setForm(f => ({ ...f, password_confirmation: e.target.value }))}
                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded-md px-3 py-2 focus:border-indigo-500 outline-none"
                    />
                </div>
                {error && <p className="text-red-400 text-xs">{error}</p>}
                <div className="flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={saving}
                        className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg disabled:opacity-50"
                    >
                        {saving ? 'Updating...' : 'Update Password'}
                    </button>
                    {saved && <p className="text-sm text-green-400">Password updated.</p>}
                </div>
            </form>
        </section>
    );
}

function DeleteAccountSection() {
    const [confirming, setConfirming] = useState(false);
    const [password, setPassword] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const handleDelete = async (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setError('');
        try {
            await deleteAccount({ password });
            window.location.href = '/';
        } catch (err) {
            setError(extractError(err, 'Could not delete account.'));
            setSubmitting(false);
        }
    };

    return (
        <section>
            <header className="mb-4">
                <h2 className="text-lg font-medium text-gray-100">Delete Account</h2>
                <p className="mt-1 text-sm text-gray-400">Once deleted, all your data will be permanently removed.</p>
            </header>

            {!confirming && (
                <button
                    onClick={() => setConfirming(true)}
                    className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg"
                >
                    Delete Account
                </button>
            )}

            {confirming && (
                <form onSubmit={handleDelete} className="space-y-4">
                    <p className="text-sm text-red-400">This action cannot be undone. Enter your password to confirm.</p>
                    <input
                        type="password"
                        autoComplete="current-password"
                        placeholder="Your password"
                        required
                        autoFocus
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded-md px-3 py-2 focus:border-red-500 outline-none"
                    />
                    {error && <p className="text-red-400 text-xs">{error}</p>}
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => { setConfirming(false); setPassword(''); setError(''); }}
                            className="px-4 py-2 text-sm text-gray-400 rounded-lg hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={submitting || !password}
                            className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg disabled:opacity-50"
                        >
                            {submitting ? 'Deleting...' : 'Permanently Delete'}
                        </button>
                    </div>
                </form>
            )}
        </section>
    );
}
