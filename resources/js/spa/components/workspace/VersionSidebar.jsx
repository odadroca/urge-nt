import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listVersions } from '../../api/versions.js';
import { listBranches, createBranch } from '../../api/branches.js';

export default function VersionSidebar({ prompt, username, slug, currentVersionId, onVersionSelect }) {
    const queryClient = useQueryClient();
    const [currentBranchId, setCurrentBranchId] = useState(prompt?.default_branch_id);
    const [showBranchForm, setShowBranchForm] = useState(false);
    const [newBranchName, setNewBranchName] = useState('');

    const { data: branchesData } = useQuery({
        queryKey: ['workspace', username, slug, 'branches'],
        queryFn: () => listBranches(username, slug),
    });

    const { data: versionsData } = useQuery({
        queryKey: ['workspace', username, slug, 'versions'],
        queryFn: () => listVersions(username, slug),
    });

    const branches = branchesData?.data ?? [];
    const allVersions = versionsData?.data ?? [];
    const currentBranch = branches.find(b => b.id === currentBranchId) || branches.find(b => b.is_default);
    const versions = currentBranch
        ? allVersions.filter(v => v.branch_id === currentBranch.id)
        : allVersions;

    const handleCreateBranch = async () => {
        if (!newBranchName.trim()) return;
        try {
            await createBranch(username, slug, {
                name: newBranchName.trim(),
                from_version: currentVersionId,
            });
            queryClient.invalidateQueries({ queryKey: ['workspace'] });
            setNewBranchName('');
            setShowBranchForm(false);
        } catch (err) {
            console.error('Create branch failed:', err);
        }
    };

    return (
        <div className="flex flex-col h-full">
            {/* Branch selector */}
            <div className="p-3 border-b border-gray-700">
                <div className="flex items-center justify-between mb-2">
                    <span className="text-xs text-gray-500 uppercase tracking-wider">Branch</span>
                    <button
                        onClick={() => setShowBranchForm(!showBranchForm)}
                        className="text-xs text-indigo-400 hover:text-indigo-300"
                    >
                        + New
                    </button>
                </div>
                <select
                    value={currentBranch?.id || ''}
                    onChange={(e) => {
                        const branchId = parseInt(e.target.value);
                        setCurrentBranchId(branchId);
                        const branch = branches.find(b => b.id === branchId);
                        if (branch?.head_version_id) {
                            onVersionSelect(branch.head_version_id);
                        }
                    }}
                    className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-2 py-1.5 outline-none"
                >
                    {branches.map(b => (
                        <option key={b.id} value={b.id}>
                            {b.name} {b.is_default ? '(default)' : ''}
                        </option>
                    ))}
                </select>

                {showBranchForm && (
                    <div className="mt-2 flex gap-1">
                        <input
                            value={newBranchName}
                            onChange={(e) => setNewBranchName(e.target.value)}
                            placeholder="Branch name"
                            className="flex-1 bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1 outline-none"
                            onKeyDown={(e) => e.key === 'Enter' && handleCreateBranch()}
                        />
                        <button onClick={handleCreateBranch} className="bg-indigo-600 text-white text-xs px-2 py-1 rounded">
                            Create
                        </button>
                    </div>
                )}
            </div>

            {/* Version list */}
            <div className="flex-1 overflow-y-auto">
                <div className="p-2 space-y-1">
                    {versions.length === 0 && (
                        <p className="text-xs text-gray-500 p-2">No versions yet</p>
                    )}
                    {versions.map(v => (
                        <button
                            key={v.id}
                            onClick={() => onVersionSelect(v.id)}
                            className={`w-full text-left px-3 py-2 rounded text-xs transition-colors ${
                                v.id === currentVersionId
                                    ? 'bg-indigo-900/50 text-indigo-200 border border-indigo-700'
                                    : 'text-gray-300 hover:bg-gray-700'
                            }`}
                        >
                            <div className="flex items-center justify-between">
                                <span className="font-medium">
                                    {currentBranch?.name || 'main'}#{v.branch_version_number} (v{v.version_number})
                                </span>
                            </div>
                            {v.commit_message && (
                                <div className="text-[10px] text-gray-500 mt-0.5 truncate">{v.commit_message}</div>
                            )}
                            <div className="text-[10px] text-gray-600 mt-0.5">
                                {new Date(v.created_at).toLocaleDateString()}
                            </div>
                        </button>
                    ))}
                </div>
            </div>

            {/* Back to canvas link */}
            <div className="p-3 border-t border-gray-700">
                <a href="/app/canvas" className="text-xs text-gray-500 hover:text-indigo-400">&larr; Back to Canvas</a>
            </div>
        </div>
    );
}
