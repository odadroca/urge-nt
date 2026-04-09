import { useCallback, useState } from 'react';
import ELK from 'elkjs/lib/elk.bundled.js';

const elk = new ELK();

const NODE_DIMENSIONS = {
    prompt: { width: 260, height: 140 },
    fragment: { width: 240, height: 120 },
    collection: { width: 220, height: 80 },
};

export default function useElkLayout() {
    const [isLayouting, setIsLayouting] = useState(false);

    const getLayoutedNodes = useCallback(async (nodes, edges, algorithm) => {
        if (!algorithm || algorithm === 'free') return nodes;

        setIsLayouting(true);
        try {
            const elkGraph = {
                id: 'root',
                layoutOptions: {
                    'elk.algorithm': algorithm,
                    'elk.direction': 'DOWN',
                    'elk.spacing.nodeNode': '80',
                    'elk.layered.spacing.nodeNodeBetweenLayers': '100',
                },
                children: nodes.map((node) => ({
                    id: node.id,
                    width: NODE_DIMENSIONS[node.type]?.width || 240,
                    height: NODE_DIMENSIONS[node.type]?.height || 120,
                })),
                edges: edges.map((edge) => ({
                    id: edge.id,
                    sources: [edge.source],
                    targets: [edge.target],
                })),
            };

            const layouted = await elk.layout(elkGraph);

            return nodes.map((node) => {
                const elkNode = layouted.children?.find((n) => n.id === node.id);
                if (!elkNode) return node;
                return { ...node, position: { x: elkNode.x || 0, y: elkNode.y || 0 } };
            });
        } finally {
            setIsLayouting(false);
        }
    }, []);

    return { getLayoutedNodes, isLayouting };
}
