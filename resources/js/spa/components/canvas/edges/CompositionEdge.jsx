import { memo, useState } from 'react';
import { BaseEdge, EdgeLabelRenderer, getBezierPath } from '@xyflow/react';
import { useQueryClient } from '@tanstack/react-query';
import { removeInclude } from '../../../api/graph.js';

function CompositionEdge({ id, sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition, data }) {
    const [isHovered, setIsHovered] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const queryClient = useQueryClient();

    const [edgePath, labelX, labelY] = getBezierPath({
        sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition,
    });

    const handleDelete = async (e) => {
        e.stopPropagation();
        if (isDeleting) return;
        setIsDeleting(true);
        try {
            await removeInclude(data.sourceOwner, data.sourceSlug, data.label);
            queryClient.invalidateQueries({ queryKey: ['graph'] });
        } catch (err) {
            console.error('Failed to remove include:', err);
            setIsDeleting(false);
        }
    };

    return (
        <>
            <BaseEdge
                id={id}
                path={edgePath}
                style={{ stroke: '#818cf8', strokeWidth: 1.5, strokeDasharray: '5 5' }}
                className="animated-edge"
                interactionWidth={20}
            />
            <EdgeLabelRenderer>
                <div
                    className="nodrag nopan pointer-events-auto absolute flex items-center gap-1"
                    style={{ transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)` }}
                    onMouseEnter={() => setIsHovered(true)}
                    onMouseLeave={() => setIsHovered(false)}
                >
                    <span className="bg-gray-900 text-indigo-300 text-[9px] px-1.5 py-0.5 rounded border border-gray-700">
                        {data?.label || 'includes'}
                    </span>
                    {isHovered && (
                        <button
                            onClick={handleDelete}
                            disabled={isDeleting}
                            className="bg-red-900 text-red-300 text-[10px] w-4 h-4 rounded-full flex items-center justify-center hover:bg-red-800"
                        >
                            ×
                        </button>
                    )}
                </div>
            </EdgeLabelRenderer>
        </>
    );
}

export default memo(CompositionEdge);
