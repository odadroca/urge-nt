import { useState, useEffect, useCallback, useRef } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { createVersion } from '../../api/versions.js';
import PreviewPanel from './PreviewPanel.jsx';
import AutocompleteDropdown from './AutocompleteDropdown.jsx';
import VisualComposer from './VisualComposer.jsx';
import useAutocomplete from '../../hooks/useAutocomplete.js';

const VAR_PATTERN = /\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/g;
const INCLUDE_PATTERN = /\{\{>([a-zA-Z0-9_-]+)\}\}/g;

export default function Editor({ prompt, version, username, slug, onVersionCreated, onShowMetadata }) {
    const [content, setContent] = useState('');
    const [commitMessage, setCommitMessage] = useState('');
    const [isDirty, setIsDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [variables, setVariables] = useState([]);
    const [includes, setIncludes] = useState([]);
    const [showPreview, setShowPreview] = useState(false);
    const [editorMode, setEditorMode] = useState('text');
    const textareaRef = useRef(null);
    const queryClient = useQueryClient();
    const autocomplete = useAutocomplete(textareaRef);

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
                    <button
                        onClick={() => onShowMetadata?.()}
                        title="Prompt metadata"
                        className="text-gray-500 hover:text-gray-300 ml-1"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
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
                    <div className="flex bg-gray-900 rounded text-xs">
                        <button
                            onClick={() => setEditorMode('text')}
                            className={`px-2 py-0.5 rounded-l transition-colors ${
                                editorMode === 'text' ? 'bg-gray-700 text-gray-200' : 'text-gray-500 hover:text-gray-300'
                            }`}
                        >
                            Text
                        </button>
                        <button
                            onClick={() => setEditorMode('visual')}
                            className={`px-2 py-0.5 rounded-r transition-colors ${
                                editorMode === 'visual' ? 'bg-gray-700 text-gray-200' : 'text-gray-500 hover:text-gray-300'
                            }`}
                        >
                            Visual
                        </button>
                    </div>
                    <button
                        onClick={() => setShowPreview(!showPreview)}
                        className={`text-xs px-2 py-0.5 rounded transition-colors ${
                            showPreview
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700'
                        }`}
                    >
                        Preview
                    </button>
                </div>
            </div>

            <div className="flex-1 overflow-hidden flex flex-col">
                <div className="relative" style={{ flex: showPreview ? '0 0 50%' : '1 1 auto', overflow: 'hidden' }}>
                    {editorMode === 'text' ? (
                        <>
                            <textarea
                                ref={textareaRef}
                                value={content}
                                onChange={(e) => { setContent(e.target.value); setIsDirty(true); }}
                                onInput={autocomplete.handleInput}
                                onKeyDown={(e) => { if (autocomplete.handleKeyDown(e)) return; }}
                                onBlur={() => { setTimeout(() => autocomplete.dismiss(), 150); }}
                                className="w-full h-full bg-gray-900 text-gray-100 font-mono text-sm p-4 resize-none outline-none border-none"
                                placeholder="Write your prompt... (type {{ for variables, {{> for includes)"
                                spellCheck={false}
                            />
                            {autocomplete.isOpen && (
                                <AutocompleteDropdown
                                    items={autocomplete.filteredItems}
                                    selectedIndex={autocomplete.selectedIndex}
                                    position={autocomplete.position}
                                    triggerType={autocomplete.triggerType}
                                    onSelect={autocomplete.insertItem}
                                />
                            )}
                        </>
                    ) : (
                        <VisualComposer
                            content={content}
                            onChange={(newContent) => { setContent(newContent); setIsDirty(true); }}
                        />
                    )}
                </div>
                {showPreview && (
                    <PreviewPanel
                        username={username}
                        slug={slug}
                        versionNumber={version?.version_number}
                        variables={variables}
                    />
                )}
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
