import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { listStarredResults } from '../../api/results.js';

export default function StarredResults() {
    const [page, setPage] = useState(1);

    const { data, isLoading } = useQuery({
        queryKey: ['browse', 'starred', page],
        queryFn: () => listStarredResults({ page }),
    });

    const results = data?.data ?? [];
    const pagination = data?.meta ?? {};

    if (isLoading) {
        return (
            <div className="flex justify-center py-12">
                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    if (results.length === 0) {
        return <p className="text-center py-12 text-gray-500">No starred results yet.</p>;
    }

    return (
        <>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {results.map(r => {
                    const owner = r.prompt?.creator?.username || r.prompt?.creator?.name;
                    return (
                        <div key={r.id} className="bg-gray-800 border border-gray-700 rounded-lg p-4">
                            <div className="flex items-start justify-between mb-2">
                                <Link
                                    to={`/workspace/${owner}/${r.prompt?.slug}`}
                                    className="text-sm font-medium text-indigo-400 hover:underline"
                                >
                                    {r.prompt?.name}
                                </Link>
                                <span className="text-amber-400 text-xs">
                                    {'★'.repeat(r.rating || 0)}
                                </span>
                            </div>
                            <p className="text-xs text-gray-300 line-clamp-3 mb-2">{r.response_text}</p>
                            <div className="flex items-center gap-3 text-[10px] text-gray-500">
                                {r.provider_name && <span>{r.provider_name}</span>}
                                {r.model_name && <span>{r.model_name}</span>}
                                {r.prompt_version && <span>v{r.prompt_version.version_number}</span>}
                            </div>
                        </div>
                    );
                })}
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
    );
}
