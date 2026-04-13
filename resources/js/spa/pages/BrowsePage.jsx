import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { listPrompts } from '../api/prompts.js';
import { listCategories } from '../api/categories.js';
import PromptCard from '../components/browse/PromptCard.jsx';
import CreatePromptForm from '../components/browse/CreatePromptForm.jsx';
import CollectionList from '../components/browse/CollectionList.jsx';
import StarredResults from '../components/browse/StarredResults.jsx';

const TABS = [
    { key: 'prompts', label: 'Prompts' },
    { key: 'fragments', label: 'Fragments' },
    { key: 'collections', label: 'Collections' },
    { key: 'starred', label: 'Starred' },
];

export default function BrowsePage() {
    const [tab, setTab] = useState('prompts');
    const [search, setSearch] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [tagFilter, setTagFilter] = useState('');
    const [scope, setScope] = useState('mine');
    const [showCreate, setShowCreate] = useState(false);
    const [page, setPage] = useState(1);

    const isPromptTab = tab === 'prompts' || tab === 'fragments';
    const type = tab === 'fragments' ? 'fragment' : 'prompt';

    const { data: promptsData, isLoading } = useQuery({
        queryKey: ['browse', 'prompts', { type, search, categoryId, tagFilter, scope, page }],
        queryFn: () => listPrompts({
            type,
            search: search || undefined,
            category_id: categoryId || undefined,
            tag: tagFilter || undefined,
            scope,
            page,
        }),
        enabled: isPromptTab,
    });

    const { data: categoriesData } = useQuery({
        queryKey: ['browse', 'categories'],
        queryFn: listCategories,
    });

    const prompts = promptsData?.data ?? [];
    const pagination = promptsData?.meta ?? {};
    const categories = categoriesData?.data ?? [];

    const handleTabChange = (newTab) => {
        setTab(newTab);
        setPage(1);
    };

    const clearFilters = () => {
        setSearch('');
        setCategoryId('');
        setTagFilter('');
        setPage(1);
    };

    const hasFilters = search || categoryId || tagFilter;

    return (
        <div className="h-full flex flex-col overflow-hidden">
            {/* Tabs + New button */}
            <div className="flex items-center justify-between px-6 pt-4 pb-0">
                <div className="flex gap-1 border-b border-gray-700">
                    {TABS.map(t => (
                        <button
                            key={t.key}
                            onClick={() => handleTabChange(t.key)}
                            className={`px-4 py-2 text-sm transition-colors ${
                                tab === t.key
                                    ? 'text-white border-b-2 border-indigo-500'
                                    : 'text-gray-500 hover:text-gray-300'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
                <button
                    onClick={() => setShowCreate(!showCreate)}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-1.5 rounded"
                >
                    + New
                </button>
            </div>

            {/* Create form */}
            {showCreate && (
                <div className="px-6 pt-3">
                    <CreatePromptForm onClose={() => setShowCreate(false)} />
                </div>
            )}

            {/* Filter bar (prompt/fragment tabs only) */}
            {isPromptTab && (
                <div className="flex items-center gap-3 px-6 py-3">
                    <input
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        placeholder="Search..."
                        className="bg-gray-800 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 w-48 outline-none focus:border-indigo-500"
                    />
                    <select
                        value={categoryId}
                        onChange={(e) => { setCategoryId(e.target.value); setPage(1); }}
                        className="bg-gray-800 border border-gray-600 text-gray-300 text-sm rounded px-2 py-1.5 outline-none"
                    >
                        <option value="">All categories</option>
                        {categories.map(c => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                    <select
                        value={scope}
                        onChange={(e) => { setScope(e.target.value); setPage(1); }}
                        className="bg-gray-800 border border-gray-600 text-gray-300 text-sm rounded px-2 py-1.5 outline-none"
                    >
                        <option value="mine">My prompts</option>
                        <option value="shared">Shared with me</option>
                        <option value="all">All visible</option>
                    </select>
                    {hasFilters && (
                        <button onClick={clearFilters} className="text-xs text-gray-500 hover:text-gray-300">
                            Clear filters
                        </button>
                    )}
                </div>
            )}

            {/* Content */}
            <div className="flex-1 overflow-y-auto px-6 pb-6">
                {isPromptTab && (
                    <>
                        {isLoading ? (
                            <div className="flex justify-center py-12">
                                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
                            </div>
                        ) : prompts.length === 0 ? (
                            <div className="text-center py-12 text-gray-500">
                                <p className="mb-2">No {tab} found.</p>
                                {hasFilters && <button onClick={clearFilters} className="text-indigo-400 text-sm">Clear filters</button>}
                            </div>
                        ) : (
                            <>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {prompts.map(p => <PromptCard key={p.id} prompt={p} />)}
                                </div>
                                {pagination.last_page > 1 && (
                                    <div className="flex justify-center gap-2 mt-6">
                                        <button
                                            onClick={() => setPage(p => Math.max(1, p - 1))}
                                            disabled={page <= 1}
                                            className="text-sm text-gray-400 hover:text-white disabled:opacity-30 px-3 py-1"
                                        >
                                            Prev
                                        </button>
                                        <span className="text-sm text-gray-500">{page} / {pagination.last_page}</span>
                                        <button
                                            onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))}
                                            disabled={page >= pagination.last_page}
                                            className="text-sm text-gray-400 hover:text-white disabled:opacity-30 px-3 py-1"
                                        >
                                            Next
                                        </button>
                                    </div>
                                )}
                            </>
                        )}
                    </>
                )}

                {tab === 'collections' && <CollectionList />}

                {tab === 'starred' && <StarredResults />}
            </div>
        </div>
    );
}
