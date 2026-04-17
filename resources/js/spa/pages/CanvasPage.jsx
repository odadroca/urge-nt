import { useState, useEffect, useCallback, useMemo } from 'react';
import { ReactFlowProvider } from '@xyflow/react';
import useGraphData from '../hooks/useGraphData.js';
import useElkLayout from '../hooks/useElkLayout.js';
import useMermaidExport from '../hooks/useMermaidExport.js';
import FlowCanvas from '../components/canvas/FlowCanvas.jsx';
import Sidebar from '../components/canvas/Sidebar.jsx';
import PropertiesPanel from '../components/canvas/PropertiesPanel.jsx';
import Toolbar from '../components/canvas/Toolbar.jsx';
import { savePositions } from '../api/graph.js';

export default function CanvasPage() {
    const [activeLayers, setActiveLayers] = useState(['prompts', 'fragments']);
    const [expandedPrompts, setExpandedPrompts] = useState(new Set());
    const [hiddenNodes, setHiddenNodes] = useState(new Set());
    const { nodes, edges, meta, isLoading, error, refetch } = useGraphData(activeLayers, expandedPrompts);
    const { getLayoutedNodes, isLayouting } = useElkLayout();
    const [sidebarVisible, setSidebarVisible] = useState(true);
    const [layoutMode, setLayoutMode] = useState('free');
    const [selectedNode, setSelectedNode] = useState(null);
    const { copyToClipboard } = useMermaidExport(nodes, edges);

    const handleToggleLayer = useCallback((layer) => {
        setActiveLayers(prev =>
            prev.includes(layer)
                ? prev.filter(l => l !== layer)
                : [...prev, layer]
        );
    }, []);

    const handleTogglePromptResults = useCallback((promptId) => {
        // Auto-enable results layer when expanding
        setActiveLayers(prev => {
            if (!prev.includes('results')) {
                return [...prev, 'results'];
            }
            return prev;
        });
        setExpandedPrompts(prev => {
            const next = new Set(prev);
            if (next.has(promptId)) {
                next.delete(promptId);
            } else {
                next.add(promptId);
            }
            return next;
        });
    }, []);

    const handleToggleNodeVisibility = useCallback((nodeKey) => {
        setHiddenNodes(prev => {
            const next = new Set(prev);
            if (next.has(nodeKey)) {
                next.delete(nodeKey);
            } else {
                next.add(nodeKey);
            }
            return next;
        });
    }, []);

    const handleToggleAllVisibility = useCallback((nodeType) => {
        const typeNodes = nodes.filter(n => n.type === nodeType);
        const typeKeys = typeNodes.map(n => n.id);
        setHiddenNodes(prev => {
            const allHidden = typeKeys.every(k => prev.has(k));
            const next = new Set(prev);
            if (allHidden) {
                typeKeys.forEach(k => next.delete(k));
            } else {
                typeKeys.forEach(k => next.add(k));
            }
            return next;
        });
    }, [nodes]);

    const nodesWithCallbacks = useMemo(() => {
        // Build set of hidden prompt numeric IDs (for filtering child results/evaluations)
        const hiddenPromptIds = new Set();
        hiddenNodes.forEach(key => {
            if (key.startsWith('prompt-')) hiddenPromptIds.add(parseInt(key.split('-')[1]));
        });

        return nodes
            .filter(node => {
                // Filter out any directly hidden node (prompt, fragment, collection)
                if (hiddenNodes.has(node.id)) return false;
                // Filter out results belonging to hidden prompts
                if (node.type === 'result') {
                    return !hiddenPromptIds.has(node.data.prompt_id);
                }
                // Filter out evaluations for hidden results
                if (node.type === 'evaluation') {
                    const parentResult = nodes.find(n => n.id === `result-${node.data.result_id}`);
                    if (parentResult && hiddenPromptIds.has(parentResult.data.prompt_id)) return false;
                }
                return true;
            })
            .map(node => {
                if (node.type === 'prompt') {
                    return {
                        ...node,
                        data: {
                            ...node.data,
                            onToggleResults: handleTogglePromptResults,
                        },
                    };
                }
                return node;
            });
    }, [nodes, handleTogglePromptResults, hiddenNodes]);

    // Filter edges to exclude those connecting to/from hidden nodes
    const filteredEdges = useMemo(() => {
        const visibleNodeIds = new Set(nodesWithCallbacks.map(n => n.id));
        return edges.filter(e => visibleNodeIds.has(e.source) && visibleNodeIds.has(e.target));
    }, [edges, nodesWithCallbacks]);

    const handleMermaidExport = useCallback(async () => {
        await copyToClipboard();
    }, [copyToClipboard]);

    useEffect(() => {
        const handler = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                setSidebarVisible((v) => !v);
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    const handleLayoutChange = useCallback(async (mode) => {
        setLayoutMode(mode);
        if (mode === 'free') return;
        const layouted = await getLayoutedNodes(nodes, edges, mode);
        const positions = layouted.map((n) => {
            const [type, id] = n.id.split('-');
            return { node_type: type, node_id: parseInt(id), x: n.position.x, y: n.position.y };
        });
        await savePositions(positions);
        refetch();
    }, [nodes, edges, getLayoutedNodes, refetch]);

    if (isLoading) {
        return (
            <div className="h-full flex items-center justify-center text-gray-400">
                <div className="text-center">
                    <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full mx-auto mb-4" />
                    <p>Loading canvas...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="h-full flex items-center justify-center text-red-400">
                <p>Failed to load graph data. Please refresh.</p>
            </div>
        );
    }

    if (nodes.length === 0) {
        return (
            <div className="h-full flex items-center justify-center text-gray-400">
                <div className="text-center">
                    <div className="text-4xl mb-4">🗂️</div>
                    <h2 className="text-lg mb-2">No prompts yet</h2>
                    <p className="text-sm mb-4">Create your first prompt to see it on the canvas.</p>
                    <a href="/browse" className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm">
                        Go to Browse →
                    </a>
                </div>
            </div>
        );
    }

    return (
        <ReactFlowProvider>
            <div className="h-full relative">
                {meta?.truncated && (
                    <div className="absolute top-2 left-1/2 -translate-x-1/2 z-40 bg-amber-900/80 text-amber-200 text-sm px-4 py-2 rounded-lg">
                        Showing 500 of {meta.total_count} nodes.
                    </div>
                )}
                <Sidebar
                    visible={sidebarVisible}
                    nodes={nodes}
                    layoutMode={layoutMode}
                    onLayoutChange={handleLayoutChange}
                    activeLayers={activeLayers}
                    onToggleLayer={handleToggleLayer}
                    hiddenNodes={hiddenNodes}
                    onToggleNodeVisibility={handleToggleNodeVisibility}
                    onToggleAllVisibility={handleToggleAllVisibility}
                />
                <FlowCanvas initialNodes={nodesWithCallbacks} initialEdges={filteredEdges} onNodeSelect={setSelectedNode} />
                <PropertiesPanel node={selectedNode} onClose={() => setSelectedNode(null)} />
                <Toolbar
                    layoutMode={layoutMode}
                    onLayoutChange={handleLayoutChange}
                    onMermaidExport={handleMermaidExport}
                    onToggleSidebar={() => setSidebarVisible((v) => !v)}
                    isLayouting={isLayouting}
                />
            </div>
        </ReactFlowProvider>
    );
}
