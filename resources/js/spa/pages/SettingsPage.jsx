import { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import useAuth from '../hooks/useAuth.js';
import ProfileTab from '../components/settings/ProfileTab.jsx';
import ApiKeysTab from '../components/settings/ApiKeysTab.jsx';
import LlmProvidersTab from '../components/settings/LlmProvidersTab.jsx';
import CategoriesTab from '../components/settings/CategoriesTab.jsx';
import EvaluationTab from '../components/settings/EvaluationTab.jsx';
import UserManagementTab from '../components/settings/UserManagementTab.jsx';

const TAB_COMPONENTS = {
    'profile': ProfileTab,
    'api-keys': ApiKeysTab,
    'llm-providers': LlmProvidersTab,
    'categories': CategoriesTab,
    'evaluation': EvaluationTab,
    'users': UserManagementTab,
};

const TABS = [
    { key: 'profile', label: 'Profile', roles: ['admin', 'editor', 'viewer'] },
    { key: 'api-keys', label: 'API Keys', roles: ['admin'] },
    { key: 'llm-providers', label: 'LLM Providers', roles: ['admin', 'editor', 'viewer'] },
    { key: 'categories', label: 'Categories', roles: ['admin', 'editor', 'viewer'] },
    { key: 'evaluation', label: 'Evaluation', roles: ['admin', 'editor'] },
    { key: 'users', label: 'Users', roles: ['admin'] },
];

export default function SettingsPage() {
    const { user } = useAuth();
    const role = user?.role || 'viewer';
    const [searchParams, setSearchParams] = useSearchParams();

    const visibleTabs = TABS.filter(t => t.roles.includes(role));
    const requestedTab = searchParams.get('tab');
    const initialTab = visibleTabs.some(t => t.key === requestedTab)
        ? requestedTab
        : (visibleTabs[0]?.key || 'profile');
    const [activeTab, setActiveTab] = useState(initialTab);

    useEffect(() => {
        if (requestedTab && requestedTab !== activeTab && visibleTabs.some(t => t.key === requestedTab)) {
            setActiveTab(requestedTab);
        }
    }, [requestedTab]);

    const selectTab = (key) => {
        setActiveTab(key);
        setSearchParams({ tab: key }, { replace: true });
    };

    return (
        <div className="h-full flex flex-col overflow-hidden">
            {/* Tab bar */}
            <div className="flex items-center gap-1 px-6 pt-4 border-b border-gray-700">
                {visibleTabs.map(t => (
                    <button
                        key={t.key}
                        onClick={() => selectTab(t.key)}
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
                {(() => {
                    const TabComponent = TAB_COMPONENTS[activeTab];
                    return TabComponent ? <TabComponent role={role} /> : null;
                })()}
            </div>
        </div>
    );
}
