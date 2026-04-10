import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import useAuth from '../hooks/useAuth.js';

export default function LoginPage() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const { login, isAuthenticated } = useAuth();
    const navigate = useNavigate();

    if (isAuthenticated) {
        navigate('/canvas', { replace: true });
        return null;
    }

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await login(email, password);
            navigate('/canvas');
        } catch (err) {
            setError(err.response?.data?.message || err.response?.data?.errors?.email?.[0] || 'Login failed');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-900">
            <div className="w-full max-w-sm">
                <h1 className="text-2xl font-bold text-indigo-400 text-center mb-8">URGE</h1>
                <form onSubmit={handleSubmit} className="bg-gray-800 border border-gray-700 rounded-xl p-6 space-y-4">
                    <h2 className="text-lg font-semibold text-gray-100 text-center">Sign in</h2>
                    {error && (
                        <div className="bg-red-900/50 border border-red-700 text-red-300 text-sm px-3 py-2 rounded">{error}</div>
                    )}
                    <div>
                        <label className="block text-sm text-gray-400 mb-1">Email</label>
                        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus
                            className="w-full bg-gray-900 border border-gray-600 text-gray-100 rounded px-3 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none" />
                    </div>
                    <div>
                        <label className="block text-sm text-gray-400 mb-1">Password</label>
                        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required
                            className="w-full bg-gray-900 border border-gray-600 text-gray-100 rounded px-3 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none" />
                    </div>
                    <button type="submit" disabled={loading}
                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg font-medium disabled:opacity-50">
                        {loading ? 'Signing in...' : 'Sign in'}
                    </button>
                    <p className="text-center text-sm text-gray-500">
                        <a href="/register" className="text-indigo-400 hover:underline">Create account</a>
                    </p>
                </form>
            </div>
        </div>
    );
}
