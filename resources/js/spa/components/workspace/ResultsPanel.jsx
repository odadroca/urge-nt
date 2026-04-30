import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listResults, updateResult, deleteResult } from '../../api/results.js';
import ManualResultForm from './ManualResultForm.jsx';
import useCopyToClipboard from '../../hooks/useCopyToClipboard.js';

export default function ResultsPanel({ prompt, username, slug, currentVersionId, currentVersionNumber, showRunPanel, onToggleRunPanel }) {
    const queryClient = useQueryClient();
    const [sortBy, setSortBy] = useState('newest');
    const [showAllVersions, setShowAllVersions] = useState(false);
    const [scheduledOnly, setScheduledOnly] = useState(false);
    const [showManualForm, setShowManualForm] = useState(false);

    const { data: resultsData, isLoading } = useQuery({
        queryKey: ['workspace', username, slug, 'results', { sortBy, showAllVersions, scheduledOnly, currentVersionNumber }],
        queryFn: () => listResults(username, slug, {
            sort: sortBy,
            ...(showAllVersions ? {} : (currentVersionNumber ? { version: currentVersionNumber } : {})),
            ...(scheduledOnly ? { run_source: 'scheduled' } : {}),
        }),
    });

    const results = resultsData?.data ?? [];

    const handleToggleStar = useCallback(async (resultId, currentStarred) => {
        try {
            await updateResult(resultId, { starred: !currentStarred });
            queryClient.invalidateQueries({ queryKey: ['workspace', username, slug, 'results'] });
        } catch (err) {
            console.error('Toggle star failed:', err);
        }
    }, [username, slug, queryClient]);

    const handleRate = useCallback(async (resultId, rating) => {
        try {
            await updateResult(resultId, { rating });
            queryClient.invalidateQueries({ queryKey: ['workspace', username, slug, 'results'] });
        } catch (err) {
            console.error('Rate failed:', err);
        }
    }, [username, slug, queryClient]);

    const handleDelete = useCallback(async (resultId) => {
        if (!confirm('Delete this result?')) return;
        try {
            await deleteResult(resultId);
            queryClient.invalidateQueries({ queryKey: ['workspace', username, slug, 'results'] });
        } catch (err) {
            console.error('Delete failed:', err);
        }
    }, [username, slug, queryClient]);

    return (
        <div className="flex flex-col h-full">
            {/* Header with sort */}
            <div className="p-3 border-b border-gray-700">
                <div className="flex items-center justify-between mb-2">
                    <span className="text-xs text-gray-500 uppercase tracking-wider">Results</span>
                    <span className="text-[10px] text-gray-500">{results.length} total</span>
                </div>
                <div className="flex gap-2">
                    <select
                        value={sortBy}
                        onChange={(e) => setSortBy(e.target.value)}
                        className="flex-1 bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1 outline-none"
                    >
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="rating_desc">Top rated</option>
                        <option value="tokens_desc">Most tokens</option>
                        <option value="duration_asc">Fastest</option>
                    </select>
                    <label className="flex items-center gap-1 text-[10px] text-gray-400">
                        <input
                            type="checkbox"
                            checked={showAllVersions}
                            onChange={(e) => setShowAllVersions(e.target.checked)}
                            className="rounded border-gray-600 bg-gray-900 text-indigo-600"
                        />
                        All versions
                    </label>
                    <label className="flex items-center gap-1 text-[10px] text-gray-400" title="Show only results tagged run_source=scheduled (e.g. periodic cron runs)">
                        <input
                            type="checkbox"
                            checked={scheduledOnly}
                            onChange={(e) => setScheduledOnly(e.target.checked)}
                            className="rounded border-gray-600 bg-gray-900 text-indigo-600"
                        />
                        Scheduled
                    </label>
                </div>
                <div className="flex items-center gap-2 mt-2">
                    <button
                        onClick={() => setShowManualForm(!showManualForm)}
                        className={`text-xs px-2 py-1 rounded ${showManualForm ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'}`}
                    >
                        + Add Result
                    </button>
                    <button
                        onClick={() => onToggleRunPanel?.()}
                        className={`text-xs px-2 py-1 rounded ${showRunPanel ? 'bg-green-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'}`}
                    >
                        Run LLM
                    </button>
                </div>
            </div>

            {/* Manual result form */}
            {showManualForm && (
                <div className="border-b border-gray-700">
                    <ManualResultForm
                        username={username}
                        slug={slug}
                        currentVersionNumber={currentVersionNumber}
                        onClose={() => setShowManualForm(false)}
                    />
                </div>
            )}

            {/* Results list */}
            <div className="flex-1 overflow-y-auto p-2 space-y-2">
                {isLoading && (
                    <div className="flex justify-center py-4">
                        <div className="animate-spin h-5 w-5 border-2 border-indigo-500 border-t-transparent rounded-full" />
                    </div>
                )}

                {!isLoading && results.length === 0 && (
                    <p className="text-xs text-gray-500 text-center py-4">No results yet</p>
                )}

                {results.map(result => (
                    <ResultCard
                        key={result.id}
                        result={result}
                        onToggleStar={handleToggleStar}
                        onRate={handleRate}
                        onDelete={handleDelete}
                    />
                ))}
            </div>
        </div>
    );
}

function ResultCard({ result, onToggleStar, onRate, onDelete }) {
    const [expanded, setExpanded] = useState(false);
    const { copied, copy } = useCopyToClipboard();
    const preview = result.response_text || '';
    const isLong = preview.length > 300;
    const displayText = expanded ? preview : preview.slice(0, 300);

    return (
        <div className="bg-gray-900 border border-gray-700 rounded-lg p-3 text-xs">
            {/* Header: provider + model */}
            <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-2">
                    <span className="text-gray-200 font-medium">{result.provider_name || 'Unknown'}</span>
                    {result.model_name && (
                        <span className="bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded text-[10px]">
                            {result.model_name}
                        </span>
                    )}
                    {result.run_source === 'scheduled' && (
                        <span className="bg-indigo-900/40 text-indigo-300 px-1.5 py-0.5 rounded text-[10px]" title="Tagged run_source=scheduled — produced by a periodic run">
                            scheduled
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    <button
                        onClick={() => onToggleStar(result.id, result.starred)}
                        className={`text-sm ${result.starred ? 'text-amber-400' : 'text-gray-600 hover:text-amber-400'}`}
                    >
                        {result.starred ? '\u2605' : '\u2606'}
                    </button>
                    <button
                        onClick={() => copy(result.response_text || '')}
                        title="Copy response"
                        className={`text-[10px] ml-1 ${copied ? 'text-indigo-400' : 'text-gray-600 hover:text-indigo-400'}`}
                    >
                        {copied ? 'Copied' : 'Copy'}
                    </button>
                    <a
                        href={`/api/v1/results/${result.id}/download`}
                        title="Download as .md"
                        className="text-gray-600 hover:text-indigo-400 text-[10px] ml-1"
                    >
                        Download
                    </a>
                    <button
                        onClick={() => onDelete(result.id)}
                        className="text-gray-600 hover:text-red-400 text-sm ml-1"
                    >
                        &times;
                    </button>
                </div>
            </div>

            {/* Response preview */}
            <div className="text-gray-300 leading-relaxed whitespace-pre-wrap mb-2">
                {displayText}
                {isLong && !expanded && '...'}
            </div>
            {isLong && (
                <button
                    onClick={() => setExpanded(!expanded)}
                    className="text-indigo-400 text-[10px] hover:underline mb-2"
                >
                    {expanded ? 'Show less' : 'Show more'}
                </button>
            )}

            {/* Rating */}
            <div className="flex items-center gap-1 mb-2">
                {[1, 2, 3, 4, 5].map(n => (
                    <button
                        key={n}
                        onClick={() => onRate(result.id, n)}
                        className={`text-sm ${n <= (result.rating || 0) ? 'text-amber-400' : 'text-gray-600 hover:text-amber-400'}`}
                    >
                        {'\u2605'}
                    </button>
                ))}
            </div>

            {/* Evaluation badge */}
            {result.evaluation_score != null && (
                <div className="mb-2">
                    <span className={`text-[10px] px-1.5 py-0.5 rounded ${
                        result.evaluation_score >= 4 ? 'bg-green-900/30 text-green-400' :
                        result.evaluation_score >= 3 ? 'bg-amber-900/30 text-amber-400' :
                        'bg-red-900/30 text-red-400'
                    }`}>
                        Eval: {Number(result.evaluation_score).toFixed(1)}
                    </span>
                </div>
            )}

            {/* Meta */}
            <div className="flex items-center gap-3 text-[10px] text-gray-500">
                {result.input_tokens != null && <span>{result.input_tokens} in</span>}
                {result.output_tokens != null && <span>{result.output_tokens} out</span>}
                {result.duration_ms != null && <span>{(result.duration_ms / 1000).toFixed(1)}s</span>}
                <span className="ml-auto">{new Date(result.created_at).toLocaleDateString()}</span>
            </div>

            {/* Notes */}
            {result.notes && (
                <div className="mt-2 text-[10px] text-gray-500 italic">{result.notes}</div>
            )}
        </div>
    );
}
