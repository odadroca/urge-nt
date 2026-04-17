import { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

function ResultNode({ data, selected }) {
    const score = data.evaluation_score;
    const scoreColor = score >= 4 ? 'text-green-400' : score >= 3 ? 'text-amber-400' : score ? 'text-red-400' : '';

    return (
        <div className={`bg-gray-800 rounded-lg shadow-lg min-w-[140px] max-w-[160px] ${selected ? 'ring-2 ring-green-400' : ''}`}
             style={{ borderLeft: '3px solid #22c55e' }}>
            <Handle type="target" position={Position.Top} className="!bg-green-500 !w-2 !h-2" />
            <div className="p-2.5">
                <div className="flex items-center justify-between gap-1 mb-1">
                    <span className="font-semibold text-xs text-gray-100 truncate">{data.provider_name || 'Unknown'}</span>
                    <span className="text-[8px] bg-green-900 text-green-300 px-1.5 py-0.5 rounded-full shrink-0">result</span>
                </div>
                {data.model_name && (
                    <div className="text-[9px] text-gray-500 mb-1 truncate">{data.model_name}</div>
                )}
                <div className="flex items-center gap-2 text-[9px] text-gray-500">
                    {data.duration_ms != null && <span>{(data.duration_ms / 1000).toFixed(1)}s</span>}
                    {data.rating && (
                        <span className="text-amber-400">{'\u2605'.repeat(data.rating)}</span>
                    )}
                    {score != null && (
                        <span className={scoreColor}>{'\u2B50'}{Number(score).toFixed(1)}</span>
                    )}
                </div>
                <div className="flex items-center gap-2 text-[8px] text-gray-600 mt-1">
                    <span>{data.source}</span>
                    {data.versionLabel && <span className="text-indigo-400">{data.versionLabel}</span>}
                </div>
                {data.sourceLabel && (
                    <div className="text-[8px] text-purple-400 mt-1 truncate" title={data.sourceLabel}>
                        {data.sourceLabel}
                    </div>
                )}
            </div>
            <Handle type="source" position={Position.Bottom} className="!bg-green-500 !w-2 !h-2" />
        </div>
    );
}

export default memo(ResultNode);
