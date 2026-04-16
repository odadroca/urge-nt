import { memo, useState } from 'react';
import { Handle, Position } from '@xyflow/react';

function EvaluationNode({ data, selected }) {
    const [expanded, setExpanded] = useState(false);
    const score = data.composite_score;
    const scoreColor = score >= 4 ? 'text-green-400' : score >= 3 ? 'text-amber-400' : 'text-red-400';
    const bgColor = score >= 4 ? 'bg-green-900/30' : score >= 3 ? 'bg-amber-900/30' : 'bg-red-900/30';

    return (
        <div className={`bg-gray-800 rounded-lg shadow-lg min-w-[130px] max-w-[180px] ${selected ? 'ring-2 ring-amber-400' : ''}`}
             style={{ borderLeft: '3px solid #f97316' }}>
            <Handle type="target" position={Position.Top} className="!bg-amber-500 !w-2 !h-2" />
            <div className="p-2.5">
                <div className="flex items-center justify-between gap-1 mb-1">
                    <span className={`font-bold text-sm ${scoreColor}`}>
                        {score != null ? Number(score).toFixed(1) : '\u2014'}
                    </span>
                    <span className="text-[8px] bg-amber-900 text-amber-300 px-1.5 py-0.5 rounded-full shrink-0">
                        eval v{data.evaluation_version}
                    </span>
                </div>
                <div className="text-[9px] text-gray-500 mb-1 truncate">{data.evaluator_provider}</div>

                {data.scores && data.scores.length > 0 && (
                    <button
                        onClick={() => setExpanded(!expanded)}
                        className="text-[8px] text-gray-500 hover:text-gray-300"
                    >
                        {expanded ? '\u25BE Hide' : '\u25B8 Dimensions'}
                    </button>
                )}

                {expanded && data.scores && (
                    <div className="mt-1 space-y-0.5">
                        {data.scores.map(s => (
                            <div key={s.dimension} className="flex items-center justify-between text-[8px]">
                                <span className="text-gray-400 truncate">{s.dimension}</span>
                                <span className={`${bgColor} px-1 rounded ${scoreColor}`}>{s.score}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

export default memo(EvaluationNode);
