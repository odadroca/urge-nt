import { useState, useEffect, useCallback, useRef } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { createVersion } from '../../api/versions.js';

const VAR_PATTERN = /\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/g;
const INCLUDE_PATTERN = /\{\{>([a-zA-Z0-9_-]+)\}\}/g;

export default function Editor({ prompt, version, username, slug, onVersionCreated }) {
    const [content, setContent] = useState('');
    const [commitMessage, setCommitMessage] = useState('');
    const [isDirty, setIsDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [variables, setVariables] = useState([]);
    const [includes, setIncludes] = useState([]);
    const textareaRef = useRef(null);
    const queryClient = useQueryClient();

    useEffect(() => {
        if (version) {
            setContent(version.content || '');
            setIsDirty(false);
            setCommitMessage('');
        }
    }, [version?.id]);

    useEffect(() => {
        const vars = [...content.matchAll(VAR_PATTERN)].map(m => m[1]);
        const incs = [...content.matchAll(INCLUDE_PATTERN)].map(m => m[1]);
        setVariables([...new Set(vars)]);
        setIncludes([...new Set(incs)]);
    }, [content]);

    const handleSave = useCallback(async () => {
        if (!isDirty || saving) return;
        setSaving(true);
        try {
            const result = await createVersion(username, slug, {
                content,
                commit_message: commitMessage || null,
                variable_metadata: version?.variable_metadata || null,
            });
            setIsDirty(false);
            setCommitMessage('');
            queryClient.invalidateQueries({ queryKey: ['workspace'] });
            onVersionCreated?.(result.data);
        } catch (err) {
            console.error('Save failed:', err);
        } finally {
            setSaving(false);
        }
    }, [content, commitMessage, isDirty, saving, username, slug, version, queryClient, onVersionCreated]);

    useEffect(() => {
        const handler = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                handleSave();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [handleSave]);

    return (
        <div className="flex flex-col h-full">
            <div className="flex items-center justify-between px-4 py-2 border-b border-gray-700 bg-gray-800/50">
                <div className="flex items-center gap-2">
                    <h2 className="text-sm font-semibold text-gray-100 truncate">{prompt?.name}</h2>
                    {version && (
                        <span className="text-[10px] bg-gray-700 text-gray-400 px-1.5 py-0.5 rounded">
                            v{version.version_number}
                        </span>
                    )}
                    {isDirty && <span className="text-[10px] text-amber-400">unsaved</span>}
                </div>
                <div className="flex items-center gap-2">
                    {variables.length > 0 && (
                        <span className="text-[10px] text-blue-400">{variables.length} vars</span>
                    )}
                    {includes.length > 0 && (
                        <span className="text-[10px] text-indigo-400">{includes.length} includes</span>
                    )}
                </div>
            </div>

            <div className="flex-1 overflow-hidden">
                <textarea
                    ref={textareaRef}
                    value={content}
                    onChange={(e) => { setContent(e.target.value); setIsDirty(true); }}
                    className="w-full h-full bg-gray-900 text-gray-100 font-mono text-sm p-4 resize-none outline-none border-none"
                    placeholder="Write your prompt..."
                    spellCheck={false}
                />
            </div>

            <div className="flex items-center gap-2 px-4 py-2 border-t border-gray-700 bg-gray-800/50">
                <input
                    value={commitMessage}
                    onChange={(e) => setCommitMessage(e.target.value)}
                    placeholder="Commit message (optional)"
                    className="flex-1 bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-2.5 py-1.5 focus:border-indigo-500 outline-none"
                    onKeyDown={(e) => { if (e.key === 'Enter') handleSave(); }}
                />
                <button
                    onClick={handleSave}
                    disabled={!isDirty || saving}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-1.5 rounded disabled:opacity-50"
                >
                    {saving ? 'Saving...' : 'Save'}
                </button>
                <span className="text-[10px] text-gray-500">Ctrl+S</span>
            </div>
        </div>
    );
}
