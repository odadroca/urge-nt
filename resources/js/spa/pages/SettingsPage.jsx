import { useState } from 'react';
import useAuth from '../hooks/useAuth.js';

const TABS = [
    { key: 'api-keys', label: 'API Keys', roles: ['admin'] },
    { key: 'llm-providers', label: 'LLM Providers', roles: ['admin', 'editor', 'viewer'] },
    { key: 'categories', label: 'Categories', roles: ['admin', 'editor', 'viewer'] },
    { key: 'pipelines', label: 'Pipelines', roles: ['admin', 'editor'] },
    { key: 'evaluation', label: 'Evaluation', roles: ['admin', 'editor'] },
    { key: 'users', label: 'Users', roles: ['admin'] },
];

export default function SettingsPage() {
    const { user } = useAuth();
    const role = user?.role || 'viewer';

    const visibleTabs = TABS.filter(t => t.roles.includes(role));
    const [activeTab, setActiveTab] = useState(visibleTabs[0]?.key || 'llm-providers');

    return (
        <div className="h-full flex flex-col overflow-hidden">
            {/* Tab bar */}
            <div className="flex items-center gap-1 px-6 pt-4 border-b border-gray-700">
                {visibleTabs.map(t => (
                    <button
                        key={t.key}
                        onClick={() => setActiveTab(t.key)}
                        className={`px-4 py-2 text-sm transition-colors ${
                            activeTab === t.key
                                ? 'text-white border-b-2 border-indigo-500'
                                : 'text-gray-500 hover:text-gray-300'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {/* Tab content */}
            <div className="flex-1 overflow-y-auto p-6">
                <p className="text-gray-400 text-sm">Tab: {activeTab} — components coming next</p>
            </div>
        </div>
    );
}
