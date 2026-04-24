import { useState, useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { updatePrompt } from '../../api/prompts.js';

export default function PropertiesPanel({ node, onClose }) {
    const queryClient = useQueryClient();
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [isDirty, setIsDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const d = node?.data;

    useEffect(() => {
        if (d) {
            setName(d.name || d.title || '');
            setDescription(d.description || '');
            setIsDirty(false);
        }
    }, [d]);

    if (!node) return null;

    const isPromptOrFragment = node.type === 'prompt' || node.type === 'fragment';
    const version = d?.active_version;

    const handleSave = async () => {
        if (!isPromptOrFragment || !isDirty) return;
        setSaving(true);
        try {
            await updatePrompt(d.owner, d.slug, { name, description });
            queryClient.invalidateQueries({ queryKey: ['graph'] });
            setIsDirty(false);
        } catch (err) {
            console.error('Save failed:', err);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed right-0 top-12 bottom-0 w-80 bg-gray-800 border-l border-gray-700 z-30 flex flex-col overflow-hidden">
            <div className="flex items-center justify-between p-3 border-b border-gray-700">
                <span className="font-semibold text-sm text-gray-100">Properties</span>
                <button onClick={onClose} className="text-gray-500 hover:text-gray-300 text-lg">&times;</button>
            </div>

            <div className="flex-1 overflow-y-auto p-3 space-y-3">
                {isPromptOrFragment ? (
                    <>
                        <Field label="Name">
                            <input value={name} onChange={(e) => { setName(e.target.value); setIsDirty(true); }}
                                className="w-full bg-gray-900 border border-gray-600 text-gray-100 text-sm rounded px-2 py-1.5 focus:border-indigo-500 outline-none" />
                        </Field>
                        <div className="flex gap-2">
                            <Field label="Type" className="flex-1">
                                <div className={`text-xs px-2 py-1 rounded text-center ${d.type === 'fragment' ? 'bg-blue-900 text-blue-300' : 'bg-indigo-900 text-indigo-300'}`}>{d.type}</div>
                            </Field>
                            <Field label="Category" className="flex-1">
                                <div className="flex items-center gap-1.5 text-xs text-gray-300">
                                    {d.category && <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: d.category.color }} />}
                                    {d.category?.name || 'None'}
                                </div>
                            </Field>
                        </div>
                        <Field label="Description">
                            <textarea value={description} onChange={(e) => { setDescription(e.target.value); setIsDirty(true); }}
                                rows={3} className="w-full bg-gray-900 border border-gray-600 text-gray-100 text-sm rounded px-2 py-1.5 focus:border-indigo-500 outline-none resize-none" />
                        </Field>
                        {version && (
                            <Field label="Version">
                                <div className="bg-gray-900 rounded p-2 text-xs text-gray-300">v{version.version_number}</div>
                            </Field>
                        )}
                        {version?.variables?.length > 0 && (
                            <Field label={`Variables (${version.variables.length})`}>
                                <div className="flex flex-wrap gap-1">
                                    {version.variables.map((v) => (
                                        <span key={v} className="bg-blue-900/50 text-blue-300 text-[10px] px-1.5 py-0.5 rounded font-mono">{v}</span>
                                    ))}
                                </div>
                            </Field>
                        )}
                        {version?.includes?.length > 0 && (
                            <Field label={`Includes (${version.includes.length})`}>
                                <div className="space-y-1">
                                    {version.includes.map((inc) => (
                                        <div key={inc} className="text-indigo-400 text-xs cursor-pointer hover:underline">&rarr; {inc}</div>
                                    ))}
                                </div>
                            </Field>
                        )}
                        {(d.tags || []).length > 0 && (
                            <Field label="Tags">
                                <div className="flex flex-wrap gap-1">
                                    {d.tags.map((t) => (
                                        <span key={t} className="bg-gray-700 text-gray-300 text-[10px] px-1.5 py-0.5 rounded-full">{t}</span>
                                    ))}
                                </div>
                            </Field>
                        )}
                    </>
                ) : (
                    <>
                        <Field label="Title"><div className="text-sm text-gray-100">{d.title}</div></Field>
                        <Field label="Items"><div className="text-sm text-gray-300">{d.items_count} items</div></Field>
                    </>
                )}
            </div>

            <div className="p-3 border-t border-gray-700 space-y-2">
                {isDirty && (
                    <button onClick={handleSave} disabled={saving}
                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm py-2 rounded-lg disabled:opacity-50">
                        {saving ? 'Saving...' : 'Save Changes'}
                    </button>
                )}
                {isPromptOrFragment && (
                    <a href={`/app/workspace/${d.owner}/${d.slug}`}
                        className="block w-full bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm py-2 rounded-lg text-center">
                        Open in Editor
                    </a>
                )}
            </div>
        </div>
    );
}

function Field({ label, className, children }) {
    return (
        <div className={className}>
            <div className="text-[9px] text-gray-500 uppercase tracking-wider mb-1">{label}</div>
            {children}
        </div>
    );
}
