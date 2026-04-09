import { useState, useEffect, useCallback } from 'react';
import { ReactFlowProvider } from '@xyflow/react';
import useGraphData from '../hooks/useGraphData.js';
import useElkLayout from '../hooks/useElkLayout.js';
import FlowCanvas from '../components/canvas/FlowCanvas.jsx';
import Sidebar from '../components/canvas/Sidebar.jsx';
import { savePositions } from '../api/graph.js';

export default function CanvasPage() {
    const { nodes, edges, meta, isLoading, error, refetch } = useGraphData();
    const { getLayoutedNodes, isLayouting } = useElkLayout();
    const [sidebarVisible, setSidebarVisible] = useState(true);
    const [layoutMode, setLayoutMode] = useState('free');

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
                />
                <FlowCanvas initialNodes={nodes} initialEdges={edges} />
            </div>
        </ReactFlowProvider>
    );
}
