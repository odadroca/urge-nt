import { useState, useRef, useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listCollections, addItem } from '../../api/collections.js';

export default function AddToCollectionButton({ promptVersionId }) {
    const [isOpen, setIsOpen] = useState(false);
    const [status, setStatus] = useState(null); // 'success' | 'error' | 'exists'
    const dropdownRef = useRef(null);
    const queryClient = useQueryClient();

    const { data: collectionsData } = useQuery({
        queryKey: ['browse', 'collections'],
        queryFn: () => listCollections(),
        enabled: isOpen,
    });

    const collections = collectionsData?.data ?? [];

    useEffect(() => {
        if (!isOpen) return;
        const handler = (e) => {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                setIsOpen(false);
                setStatus(null);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [isOpen]);

    const handleAdd = async (collectionSlug) => {
        try {
            await addItem(collectionSlug, {
                item_type: 'prompt_version',
                item_id: promptVersionId,
            });
            setStatus('success');
            queryClient.invalidateQueries({ queryKey: ['browse', 'collections'] });
            setTimeout(() => { setIsOpen(false); setStatus(null); }, 800);
        } catch (err) {
            const code = err.response?.status;
            setStatus(code === 409 ? 'exists' : 'error');
            setTimeout(() => setStatus(null), 1500);
        }
    };

    if (!promptVersionId) return null;

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={(e) => { e.preventDefault(); e.stopPropagation(); setIsOpen(!isOpen); setStatus(null); }}
                className="text-gray-500 hover:text-indigo-400 transition-colors"
                title="Add to collection"
            >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                </svg>
            </button>

            {isOpen && (
                <div
                    className="absolute right-0 top-6 z-50 bg-gray-800 border border-gray-600 rounded-lg shadow-xl overflow-hidden"
                    style={{ minWidth: '180px', maxHeight: '220px' }}
                    onClick={(e) => { e.preventDefault(); e.stopPropagation(); }}
                >
                    <div className="px-3 py-1.5 border-b border-gray-700">
                        <span className="text-[10px] text-gray-500 uppercase tracking-wider">Add to collection</span>
                    </div>

                    {status === 'success' && (
                        <div className="px-3 py-2 text-xs text-green-400">Added!</div>
                    )}
                    {status === 'exists' && (
                        <div className="px-3 py-2 text-xs text-amber-400">Already in collection</div>
                    )}
                    {status === 'error' && (
                        <div className="px-3 py-2 text-xs text-red-400">Failed to add</div>
                    )}

                    {!status && (
                        <div className="overflow-y-auto" style={{ maxHeight: '168px' }}>
                            {collections.length === 0 ? (
                                <div className="px-3 py-2 text-xs text-gray-500">No collections yet</div>
                            ) : (
                                collections.map(c => (
                                    <button
                                        key={c.id}
                                        onClick={() => handleAdd(c.slug)}
                                        className="w-full text-left px-3 py-1.5 text-xs text-gray-300 hover:bg-indigo-600 hover:text-white transition-colors"
                                    >
                                        {c.title}
                                    </button>
                                ))
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
