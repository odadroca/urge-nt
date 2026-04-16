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

export default function useGraphData(activeLayers = ['prompts', 'fragments', 'collections'], expandedPrompts = new Set()) {
    const nodesQuery = useQuery({
        queryKey: ['graph', 'nodes', activeLayers.sort().join(',')],
        queryFn: () => getNodes(activeLayers),
    });
    const edgesQuery = useQuery({
        queryKey: ['graph', 'edges', activeLayers.sort().join(',')],
        queryFn: () => getEdges(activeLayers),
    });

    const { nodes, edges, meta } = useMemo(() => {
        if (!nodesQuery.data || !edgesQuery.data) {
            return { nodes: [], edges: [], meta: null };
        }

        const apiPrompts = nodesQuery.data.data?.prompts ?? [];
        const apiCollections = nodesQuery.data.data?.collections ?? [];
        const apiResults = nodesQuery.data.data?.results ?? [];
        const apiEvaluations = nodesQuery.data.data?.evaluations ?? [];

        const compositionEdges = edgesQuery.data.data?.composition ?? [];
        const resultEdges = edgesQuery.data.data?.result ?? [];
        const evaluationEdges = edgesQuery.data.data?.evaluation ?? [];
        const meta = nodesQuery.data.meta;

        // Count incoming edges per fragment slug
        const incomingCount = {};
        compositionEdges.forEach((e) => {
            incomingCount[e.target_slug] = (incomingCount[e.target_slug] || 0) + 1;
        });

        // Build slug→nodeId map and prompt id→position map
        const slugToNodeId = {};
        const promptPositions = {};
        let posIndex = 0;

        // Count results per prompt for the badge
        const resultsPerPrompt = {};
        apiResults.forEach(r => {
            resultsPerPrompt[r.prompt_id] = (resultsPerPrompt[r.prompt_id] || 0) + 1;
        });

        const promptNodes = apiPrompts.map((p) => {
            const nodeType = p.type === 'fragment' ? 'fragment' : 'prompt';
            const nodeId = `${nodeType}-${p.id}`;
            slugToNodeId[p.slug] = nodeId;
            const position = p.position || autoPosition(posIndex++);
            promptPositions[p.id] = position;
            return {
                id: nodeId,
                type: nodeType,
                position,
                data: {
                    ...p,
                    incomingEdgeCount: incomingCount[p.slug] || 0,
                    results_count: resultsPerPrompt[p.id] || p.results_count || 0,
                    isExpanded: expandedPrompts.has(p.id),
                },
            };
        });

        const collectionNodes = activeLayers.includes('collections')
            ? apiCollections.map((c) => {
                const nodeId = `collection-${c.id}`;
                slugToNodeId[c.slug] = nodeId;
                const position = c.position || autoPosition(posIndex++);
                return { id: nodeId, type: 'collection', position, data: c };
            })
            : [];

        // Result nodes — only for expanded prompts
        const resultNodes = [];
        if (activeLayers.includes('results')) {
            // Group results by prompt_id
            const resultsByPrompt = {};
            apiResults.forEach(r => {
                if (!resultsByPrompt[r.prompt_id]) resultsByPrompt[r.prompt_id] = [];
                resultsByPrompt[r.prompt_id].push(r);
            });

            Object.entries(resultsByPrompt).forEach(([promptId, results]) => {
                const pid = parseInt(promptId);
                if (!expandedPrompts.has(pid)) return;

                const parentPos = promptPositions[pid] || { x: 0, y: 0 };

                // Group results by version for visual separation
                const byVersion = {};
                results.forEach(r => {
                    const vKey = `v${r.version_number || '?'}${r.branch_name ? '-' + r.branch_name : ''}`;
                    if (!byVersion[vKey]) byVersion[vKey] = [];
                    byVersion[vKey].push(r);
                });

                const versionGroups = Object.entries(byVersion);
                versionGroups.forEach(([vKey, vResults], groupIdx) => {
                    const yOffset = parentPos.y + 120 + groupIdx * 100;

                    vResults.forEach((r, i) => {
                        const x = r.position?.x ?? (parentPos.x + (i - (vResults.length - 1) / 2) * 150);
                        const y = r.position?.y ?? yOffset;
                        resultNodes.push({
                            id: `result-${r.id}`,
                            type: 'result',
                            position: { x, y },
                            data: { ...r, versionLabel: vKey },
                        });
                    });
                });
            });
        }

        // Evaluation nodes — only when both results and evaluations layers active
        const evaluationNodes = [];
        if (activeLayers.includes('results') && activeLayers.includes('evaluations')) {
            const resultNodeIds = new Set(resultNodes.map(n => parseInt(n.id.split('-')[1])));

            apiEvaluations.forEach(ev => {
                if (!resultNodeIds.has(ev.result_id)) return;

                const parentResult = resultNodes.find(n => n.id === `result-${ev.result_id}`);
                const parentPos = parentResult?.position || { x: 0, y: 0 };
                const x = ev.position?.x ?? parentPos.x;
                const y = ev.position?.y ?? (parentPos.y + 90);

                evaluationNodes.push({
                    id: `evaluation-${ev.result_id}-v${ev.evaluation_version}`,
                    type: 'evaluation',
                    position: { x, y },
                    data: ev,
                });
            });
        }

        // Composition edges (existing)
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

        // Result edges (prompt → result)
        const resultFlowEdges = resultNodes.map(rn => {
            const resultId = parseInt(rn.id.split('-')[1]);
            const result = apiResults.find(r => r.id === resultId);
            if (!result) return null;
            const promptNodeId = slugToNodeId[apiPrompts.find(p => p.id === result.prompt_id)?.slug];
            if (!promptNodeId) return null;
            return {
                id: `edge-result-${result.prompt_id}-${resultId}`,
                source: promptNodeId,
                target: rn.id,
                type: 'smoothstep',
                style: { stroke: '#22c55e', strokeWidth: 1.5 },
            };
        }).filter(Boolean);

        // Evaluation edges (result → evaluation)
        const evalFlowEdges = evaluationNodes.map(en => ({
            id: `edge-eval-${en.data.result_id}-v${en.data.evaluation_version}`,
            source: `result-${en.data.result_id}`,
            target: en.id,
            type: 'smoothstep',
            style: { stroke: '#f97316', strokeWidth: 1.5, strokeDasharray: '5 3' },
        }));

        return {
            nodes: [...promptNodes, ...collectionNodes, ...resultNodes, ...evaluationNodes],
            edges: [...flowEdges, ...resultFlowEdges, ...evalFlowEdges],
            meta,
        };
    }, [nodesQuery.data, edgesQuery.data, activeLayers, expandedPrompts]);

    return {
        nodes, edges, meta,
        isLoading: nodesQuery.isLoading || edgesQuery.isLoading,
        error: nodesQuery.error || edgesQuery.error,
        refetch: () => { nodesQuery.refetch(); edgesQuery.refetch(); },
    };
}
