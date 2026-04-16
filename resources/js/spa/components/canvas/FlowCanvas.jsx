import { useCallback } from 'react';
import {
    ReactFlow, useNodesState, useEdgesState, Background, Controls, MiniMap,
} from '@xyflow/react';
import { useQueryClient } from '@tanstack/react-query';
import { savePositions, appendInclude } from '../../api/graph.js';
import PromptNode from './nodes/PromptNode.jsx';
import FragmentNode from './nodes/FragmentNode.jsx';
import CollectionNode from './nodes/CollectionNode.jsx';
import ResultNode from './nodes/ResultNode.jsx';
import EvaluationNode from './nodes/EvaluationNode.jsx';
import CompositionEdge from './edges/CompositionEdge.jsx';

const nodeTypes = { prompt: PromptNode, fragment: FragmentNode, collection: CollectionNode, result: ResultNode, evaluation: EvaluationNode };
const edgeTypes = { composition: CompositionEdge };

export default function FlowCanvas({ initialNodes, initialEdges, onNodeSelect }) {
    const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);
    const queryClient = useQueryClient();

    const handleNodeDragStop = useCallback((_event, node) => {
        const [type, id] = node.id.split('-');
        savePositions([{ node_type: type, node_id: parseInt(id), x: node.position.x, y: node.position.y }]);
    }, []);

    const handleSelectionChange = useCallback(({ nodes: selected }) => {
        onNodeSelect?.(selected.length === 1 ? selected[0] : null);
    }, [onNodeSelect]);

    const handleConnect = useCallback(async (params) => {
        const sourceNode = nodes.find((n) => n.id === params.source);
        const targetNode = nodes.find((n) => n.id === params.target);
        if (!sourceNode || !targetNode) return;
        if (targetNode.type !== 'fragment') return;

        try {
            await appendInclude(sourceNode.data.owner, sourceNode.data.slug, targetNode.data.slug);
            queryClient.invalidateQueries({ queryKey: ['graph'] });
        } catch (err) {
            console.error('Failed to add include:', err);
        }
    }, [nodes, queryClient]);

    return (
        <div className="w-full h-full">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onNodeDragStop={handleNodeDragStop}
                onSelectionChange={handleSelectionChange}
                onConnect={handleConnect}
                nodeTypes={nodeTypes}
                edgeTypes={edgeTypes}
                fitView
                className="bg-gray-900"
            >
                <Background color="#374151" gap={20} size={1} />
                <Controls />
                <MiniMap
                    nodeColor={(n) => {
                        if (n.type === 'prompt') return '#6366f1';
                        if (n.type === 'fragment') return '#3b82f6';
                        if (n.type === 'result') return '#22c55e';
                        if (n.type === 'evaluation') return '#f97316';
                        return '#7c3aed';
                    }}
                    className="!bg-gray-800"
                />
            </ReactFlow>
        </div>
    );
}
