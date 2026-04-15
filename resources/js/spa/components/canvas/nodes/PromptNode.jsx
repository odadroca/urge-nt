import { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

function PromptNode({ data, selected }) {
    const borderColor = data.category?.color || '#6366f1';
    const tags = data.tags || [];
    const visibleTags = tags.slice(0, 3);
    const extraCount = tags.length - 3;
    const version = data.active_version;
    const includeCount = version?.includes?.length || 0;
    const varCount = version?.variables?.length || 0;

    return (
        <div
            className={`bg-gray-800 rounded-lg shadow-lg min-w-[220px] max-w-[260px] ${selected ? 'ring-2 ring-indigo-400' : ''}`}
            style={{ borderLeft: `4px solid ${borderColor}` }}
        >
            <Handle type="target" position={Position.Top} className="!bg-indigo-500 !w-2.5 !h-2.5" />
            <div className="p-3">
                <div className="flex items-center justify-between gap-2 mb-1">
                    <span className="font-semibold text-sm text-gray-100 truncate">{data.name}</span>
                    <span className="text-[10px] bg-indigo-900 text-indigo-300 px-1.5 py-0.5 rounded-full shrink-0">prompt</span>
                </div>
                <div className="text-[10px] text-gray-500 mb-2">{data.owner}/{data.slug} · v{version?.version_number || '?'}</div>
                {visibleTags.length > 0 && (
                    <div className="flex gap-1 flex-wrap mb-2">
                        {visibleTags.map((tag) => (
                            <span key={tag} className="bg-gray-700 text-gray-300 text-[9px] px-1.5 py-0.5 rounded-full">{tag}</span>
                        ))}
                        {extraCount > 0 && <span className="text-gray-500 text-[9px] py-0.5">+{extraCount}</span>}
                    </div>
                )}
                <div className="flex items-center gap-3 text-[10px] text-gray-500">
                    {includeCount > 0 && <span>🔗 {includeCount}</span>}
                    <span>📊 {data.results_count || 0}</span>
                    {varCount > 0 && <span>⚙ {varCount}</span>}
                    {data.avg_evaluation_score != null && (
                        <span className={
                            data.avg_evaluation_score >= 4 ? 'text-green-400' :
                            data.avg_evaluation_score >= 3 ? 'text-amber-400' : 'text-red-400'
                        }>
                            ⭐ {Number(data.avg_evaluation_score).toFixed(1)}
                        </span>
                    )}
                </div>
            </div>
            <Handle type="source" position={Position.Bottom} className="!bg-indigo-500 !w-2.5 !h-2.5" />
        </div>
    );
}

export default memo(PromptNode);
