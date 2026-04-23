import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listVersions, getVersion } from '../../api/versions.js';
import { listBranches, createBranch } from '../../api/branches.js';
import DiffViewer from './DiffViewer.jsx';

export default function VersionSidebar({ prompt, username, slug, currentVersionId, onVersionSelect }) {
    const queryClient = useQueryClient();
    const [currentBranchId, setCurrentBranchId] = useState(prompt?.default_branch_id);
    const [showBranchForm, setShowBranchForm] = useState(false);
    const [newBranchName, setNewBranchName] = useState('');

    // Compare mode state
    const [compareMode, setCompareMode] = useState(false);
    const [compareSelection, setCompareSelection] = useState([]);
    const [diffData, setDiffData] = useState(null);
    const [loadingDiff, setLoadingDiff] = useState(false);

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

    const toggleCompareMode = useCallback(() => {
        setCompareMode(prev => !prev);
        setCompareSelection([]);
    }, []);

    const toggleCompareSelection = useCallback((versionId) => {
        setCompareSelection(prev => {
            if (prev.includes(versionId)) {
                return prev.filter(id => id !== versionId);
            }
            if (prev.length >= 2) {
                return [prev[1], versionId];
            }
            return [...prev, versionId];
        });
    }, []);

    const handleShowDiff = useCallback(async () => {
        if (compareSelection.length !== 2) return;
        setLoadingDiff(true);

        const [oldId, newId] = compareSelection;
        const oldVer = allVersions.find(v => v.id === oldId);
        const newVer = allVersions.find(v => v.id === newId);

        try {
            const [oldData, newData] = await Promise.all([
                getVersion(username, slug, oldVer.version_number),
                getVersion(username, slug, newVer.version_number),
            ]);

            const branchName = currentBranch?.name || 'main';
            setDiffData({
                oldVersion: oldData.data,
                newVersion: newData.data,
                oldLabel: `${branchName}#${oldVer.branch_version_number} (v${oldVer.version_number})`,
                newLabel: `${branchName}#${newVer.branch_version_number} (v${newVer.version_number})`,
            });
        } catch (err) {
            console.error('Failed to load versions for diff:', err);
        } finally {
            setLoadingDiff(false);
        }
    }, [compareSelection, allVersions, username, slug, currentBranch]);

    const closeDiff = useCallback(() => {
        setDiffData(null);
    }, []);

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

            {/* Version list header with compare toggle */}
            <div className="flex items-center justify-between px-3 py-2 border-b border-gray-700">
                <span className="text-xs text-gray-500 uppercase tracking-wider">Versions</span>
                <div className="flex items-center gap-2">
                    {compareMode && compareSelection.length === 2 && (
                        <button
                            onClick={handleShowDiff}
                            disabled={loadingDiff}
                            className="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-0.5 rounded disabled:opacity-50"
                        >
                            {loadingDiff ? 'Loading...' : 'Diff'}
                        </button>
                    )}
                    <button
                        onClick={toggleCompareMode}
                        className={`text-xs px-2 py-0.5 rounded transition-colors ${
                            compareMode
                                ? 'bg-amber-900/50 text-amber-400 hover:bg-amber-900/70'
                                : 'text-gray-500 hover:text-gray-300'
                        }`}
                    >
                        {compareMode ? 'Cancel' : 'Compare'}
                    </button>
                </div>
            </div>

            {/* Version list */}
            <div className="flex-1 overflow-y-auto">
                <div className="p-2 space-y-1">
                    {versions.length === 0 && (
                        <p className="text-xs text-gray-500 p-2">No versions yet</p>
                    )}
                    {versions.map(v => {
                        const isSelected = compareMode && compareSelection.includes(v.id);
                        const selectionIndex = compareSelection.indexOf(v.id);

                        return (
                            <button
                                key={v.id}
                                onClick={() => compareMode ? toggleCompareSelection(v.id) : onVersionSelect(v.id)}
                                className={`w-full text-left px-3 py-2 rounded text-xs transition-colors ${
                                    compareMode
                                        ? isSelected
                                            ? 'bg-amber-900/40 text-amber-200 border border-amber-700'
                                            : 'text-gray-300 hover:bg-gray-700'
                                        : v.id === currentVersionId
                                            ? 'bg-indigo-900/50 text-indigo-200 border border-indigo-700'
                                            : 'text-gray-300 hover:bg-gray-700'
                                }`}
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">
                                        {compareMode && isSelected && (
                                            <span className="inline-flex items-center justify-center w-4 h-4 rounded-full bg-amber-600 text-white text-[9px] mr-1.5">
                                                {selectionIndex + 1}
                                            </span>
                                        )}
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
                        );
                    })}
                </div>
            </div>

            {/* Back to canvas link */}
            <div className="p-3 border-t border-gray-700">
                <a href="/app/canvas" className="text-xs text-gray-500 hover:text-indigo-400">&larr; Back to Canvas</a>
            </div>

            {/* Diff modal */}
            {diffData && (
                <DiffViewer
                    oldVersion={diffData.oldVersion}
                    newVersion={diffData.newVersion}
                    oldLabel={diffData.oldLabel}
                    newLabel={diffData.newLabel}
                    onClose={closeDiff}
                />
            )}
        </div>
    );
}
