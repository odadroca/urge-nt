import { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

function FragmentNode({ data, selected }) {
    const content = data.active_version?.content || '';
    const preview = content.length > 80 ? content.slice(0, 80) + '…' : content;

    return (
        <div className={`bg-gray-900 border-2 border-dashed border-blue-500 rounded-lg shadow-lg min-w-[200px] max-w-[240px] ${selected ? 'ring-2 ring-blue-400' : ''}`}>
            <Handle type="target" position={Position.Top} className="!bg-blue-500 !w-2.5 !h-2.5" />
            <div className="p-3">
                <div className="flex items-center justify-between gap-2 mb-1">
                    <span className="font-semibold text-sm text-gray-100 truncate">{data.name}</span>
                    <span className="text-[10px] bg-blue-900 text-blue-300 px-1.5 py-0.5 rounded-full shrink-0">fragment</span>
                </div>
                <div className="text-[10px] text-gray-500 mb-2">{data.owner}/{data.slug}</div>
                {preview && (
                    <div className="bg-gray-800 rounded p-1.5 mb-2 font-mono text-[10px] text-gray-400 leading-relaxed max-h-10 overflow-hidden">{preview}</div>
                )}
                <div className="flex items-center">
                    <span className="bg-blue-900/50 text-blue-300 text-[10px] px-2 py-0.5 rounded-full">
                        Used in {data.incomingEdgeCount} prompt{data.incomingEdgeCount !== 1 ? 's' : ''}
                    </span>
                </div>
            </div>
            <Handle type="source" position={Position.Bottom} className="!bg-blue-500 !w-2.5 !h-2.5" />
        </div>
    );
}

export default memo(FragmentNode);
