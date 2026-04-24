import { Link } from 'react-router-dom';
import AddToCollectionButton from './AddToCollectionButton.jsx';

export default function PromptCard({ prompt }) {
    const owner = prompt.creator?.slug || prompt.creator?.username || prompt.creator?.name;
    const activeVersionId = prompt.active_version?.id || prompt.latest_version?.id;

    return (
        <Link
            to={`/workspace/${owner}/${prompt.slug}`}
            className="block bg-gray-800 border border-gray-700 rounded-lg p-4 hover:border-indigo-500 transition-colors"
        >
            <div className="flex items-start justify-between mb-2">
                <h3 className="text-sm font-medium text-gray-100 truncate">{prompt.name}</h3>
                <div className="flex items-center gap-2 shrink-0 ml-2">
                    {activeVersionId && (
                        <AddToCollectionButton promptVersionId={activeVersionId} />
                    )}
                    {prompt.category && (
                        <span
                            className="text-[10px] px-1.5 py-0.5 rounded"
                            style={{
                                backgroundColor: (prompt.category.color || '#4b5563') + '20',
                                color: prompt.category.color || '#9ca3af',
                            }}
                        >
                            {prompt.category.name}
                        </span>
                    )}
                </div>
            </div>

            {prompt.description && (
                <p className="text-xs text-gray-400 line-clamp-2 mb-2">{prompt.description}</p>
            )}

            <div className="flex items-center gap-2 text-[10px] text-gray-500">
                {prompt.latest_version && (
                    <span>v{prompt.latest_version.version_number}</span>
                )}
                <span>{prompt.results_count || 0} results</span>
                {owner && <span>by {owner}</span>}
            </div>

            {prompt.tags?.length > 0 && (
                <div className="flex gap-1 mt-2 flex-wrap">
                    {prompt.tags.slice(0, 4).map(tag => (
                        <span key={tag} className="text-[10px] bg-blue-900/30 text-blue-400 px-1.5 py-0.5 rounded">
                            {tag}
                        </span>
                    ))}
                </div>
            )}
        </Link>
    );
}
