import { useState, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getPrompt } from '../api/prompts.js';
import { listVersions } from '../api/versions.js';
import Editor from '../components/workspace/Editor.jsx';
import VersionSidebar from '../components/workspace/VersionSidebar.jsx';
import ResultsPanel from '../components/workspace/ResultsPanel.jsx';
import PromptMetadataModal from '../components/workspace/PromptMetadataModal.jsx';
import RunWithLlm from '../components/workspace/RunWithLlm.jsx';

export default function WorkspacePage() {
    const { username, slug } = useParams();
    const queryClient = useQueryClient();
    const [currentVersionId, setCurrentVersionId] = useState(null);
    const [showMetadata, setShowMetadata] = useState(false);
    const [showRunPanel, setShowRunPanel] = useState(false);

    const { data: promptData, isLoading, error } = useQuery({
        queryKey: ['workspace', username, slug, 'prompt'],
        queryFn: () => getPrompt(username, slug),
    });

    const { data: versionsData } = useQuery({
        queryKey: ['workspace', username, slug, 'versions'],
        queryFn: () => listVersions(username, slug),
        enabled: !!promptData,
    });

    const prompt = promptData?.data;
    const versions = versionsData?.data ?? [];

    // Find current version: selected by ID, or fall back to active version
    const currentVersion = currentVersionId
        ? versions.find(v => v.id === currentVersionId) || prompt?.active_version
        : prompt?.active_version;

    const handleVersionCreated = useCallback((newVersion) => {
        setCurrentVersionId(newVersion.id);
        queryClient.invalidateQueries({ queryKey: ['workspace', username, slug] });
    }, [username, slug, queryClient]);

    const handleVersionSelected = useCallback((versionId) => {
        setCurrentVersionId(versionId);
    }, []);

    if (isLoading) {
        return (
            <div className="h-full flex items-center justify-center text-gray-400">
                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    if (error || !prompt) {
        return (
            <div className="h-full flex items-center justify-center text-red-400">
                <div className="text-center">
                    <p className="mb-4">Prompt not found.</p>
                    <Link to="/canvas" className="text-indigo-400 hover:underline">← Back to Canvas</Link>
                </div>
            </div>
        );
    }

    return (
        <div className="h-full flex">
            {/* Left: Version Sidebar */}
            <div className="w-64 border-r border-gray-700 bg-gray-800 overflow-y-auto shrink-0">
                <VersionSidebar
                    prompt={prompt}
                    username={username}
                    slug={slug}
                    currentVersionId={currentVersion?.id}
                    onVersionSelect={handleVersionSelected}
                />
            </div>

            {/* Center: Editor */}
            <div className="flex-1 min-w-0">
                <Editor
                    prompt={prompt}
                    version={currentVersion}
                    username={username}
                    slug={slug}
                    onVersionCreated={handleVersionCreated}
                    onShowMetadata={() => setShowMetadata(true)}
                />
            </div>

            {/* Right: Results Panel + Run LLM */}
            <div className="w-80 border-l border-gray-700 bg-gray-800 overflow-y-auto shrink-0 flex flex-col">
                <div className="flex-1 overflow-y-auto">
                    <ResultsPanel
                        prompt={prompt}
                        username={username}
                        slug={slug}
                        currentVersionId={currentVersion?.id}
                        currentVersionNumber={currentVersion?.version_number}
                        showRunPanel={showRunPanel}
                        onToggleRunPanel={() => setShowRunPanel(p => !p)}
                    />
                </div>
                {showRunPanel && (
                    <RunWithLlm
                        prompt={prompt}
                        version={currentVersion}
                        username={username}
                        slug={slug}
                        onClose={() => setShowRunPanel(false)}
                    />
                )}
            </div>

            <PromptMetadataModal
                isOpen={showMetadata}
                onClose={() => setShowMetadata(false)}
                prompt={prompt}
                username={username}
                slug={slug}
            />
        </div>
    );
}
