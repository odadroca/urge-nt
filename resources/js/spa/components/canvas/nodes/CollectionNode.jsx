import { memo } from 'react';

function CollectionNode({ data, selected }) {
    return (
        <div className={`bg-purple-900/20 border-2 border-purple-500 rounded-xl shadow-lg min-w-[200px] ${selected ? 'ring-2 ring-purple-400' : ''}`}>
            <div className="p-3">
                <div className="flex items-center justify-between gap-2">
                    <span className="font-semibold text-sm text-gray-100 truncate">{data.title}</span>
                    <span className="text-[10px] bg-purple-900 text-purple-300 px-1.5 py-0.5 rounded-full shrink-0">{data.items_count} items</span>
                </div>
                {data.description && (
                    <div className="text-[10px] text-gray-500 mt-1 truncate">{data.description}</div>
                )}
            </div>
        </div>
    );
}

export default memo(CollectionNode);
