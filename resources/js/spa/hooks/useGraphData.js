import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getNodes, getEdges } from '../api/graph.js';

const GRID_COLS = 5;
const GRID_SPACING_X = 280;
const GRID_SPACING_Y = 180;

function autoPosition(index) {
    const col = index % GRID_COLS;
    const row = Math.floor(index / GRID_COLS);
    return { x: col * GRID_SPACING_X + 50, y: row * GRID_SPACING_Y + 50 };
}

export default function useGraphData() {
    const nodesQuery = useQuery({ queryKey: ['graph', 'nodes'], queryFn: getNodes });
    const edgesQuery = useQuery({ queryKey: ['graph', 'edges'], queryFn: getEdges });

    const { nodes, edges, meta } = useMemo(() => {
        if (!nodesQuery.data || !edgesQuery.data) {
            return { nodes: [], edges: [], meta: null };
        }

        const apiPrompts = nodesQuery.data.data?.prompts ?? [];
        const apiCollections = nodesQuery.data.data?.collections ?? [];
        const compositionEdges = edgesQuery.data.data?.composition ?? [];
        const meta = nodesQuery.data.meta;

        // Count incoming edges per fragment slug
        const incomingCount = {};
        compositionEdges.forEach((e) => {
            incomingCount[e.target_slug] = (incomingCount[e.target_slug] || 0) + 1;
        });

        // Build slug→nodeId map
        const slugToNodeId = {};
        let posIndex = 0;

        const flowNodes = apiPrompts.map((p) => {
            const nodeType = p.type === 'fragment' ? 'fragment' : 'prompt';
            const nodeId = `${nodeType}-${p.id}`;
            slugToNodeId[p.slug] = nodeId;
            const position = p.position || autoPosition(posIndex++);
            return {
                id: nodeId,
                type: nodeType,
                position,
                data: { ...p, incomingEdgeCount: incomingCount[p.slug] || 0 },
            };
        });

        const collectionNodes = apiCollections.map((c) => {
            const nodeId = `collection-${c.id}`;
            slugToNodeId[c.slug] = nodeId;
            const position = c.position || autoPosition(posIndex++);
            return { id: nodeId, type: 'collection', position, data: c };
        });

        const flowEdges = compositionEdges
            .filter((e) => slugToNodeId[e.source_slug] && slugToNodeId[e.target_slug])
            .map((e) => ({
                id: `edge-${e.source_id}-${e.target_slug}`,
                source: slugToNodeId[e.source_slug],
                target: slugToNodeId[e.target_slug],
                type: 'composition',
                data: {
                    label: e.target_slug,
                    sourceSlug: e.source_slug,
                    sourceOwner: apiPrompts.find((p) => p.slug === e.source_slug)?.owner,
                },
            }));

        return { nodes: [...flowNodes, ...collectionNodes], edges: flowEdges, meta };
    }, [nodesQuery.data, edgesQuery.data]);

    return {
        nodes, edges, meta,
        isLoading: nodesQuery.isLoading || edgesQuery.isLoading,
        error: nodesQuery.error || edgesQuery.error,
        refetch: () => { nodesQuery.refetch(); edgesQuery.refetch(); },
    };
}
