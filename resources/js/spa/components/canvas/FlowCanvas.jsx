import { useCallback } from 'react';
import {
    ReactFlow, useNodesState, useEdgesState, Background, Controls, MiniMap,
} from '@xyflow/react';
import { savePositions } from '../../api/graph.js';
import PromptNode from './nodes/PromptNode.jsx';
import FragmentNode from './nodes/FragmentNode.jsx';
import CollectionNode from './nodes/CollectionNode.jsx';

// edgeTypes will be added in Task 4
const nodeTypes = { prompt: PromptNode, fragment: FragmentNode, collection: CollectionNode };
const edgeTypes = {};

export default function FlowCanvas({ initialNodes, initialEdges, onNodeSelect }) {
    const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);

    const handleNodeDragStop = useCallback((_event, node) => {
        const [type, id] = node.id.split('-');
        savePositions([{ node_type: type, node_id: parseInt(id), x: node.position.x, y: node.position.y }]);
    }, []);

    const handleSelectionChange = useCallback(({ nodes: selected }) => {
        onNodeSelect?.(selected.length === 1 ? selected[0] : null);
    }, [onNodeSelect]);

    return (
        <div className="w-full h-full">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onNodeDragStop={handleNodeDragStop}
                onSelectionChange={handleSelectionChange}
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
                        return '#7c3aed';
                    }}
                    className="!bg-gray-800"
                />
            </ReactFlow>
        </div>
    );
}
