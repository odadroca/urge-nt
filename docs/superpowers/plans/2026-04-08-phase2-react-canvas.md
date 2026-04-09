# Phase 2: React Scaffold + Canvas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a React SPA with a full-viewport React Flow canvas showing prompts, fragments, and collections as custom nodes with composition edges, floating panels, Elk.js auto-layout, and Mermaid export.

**Architecture:** React SPA served from `/app/*` via the Blade wrapper from Phase 1. API client uses session cookies (same-origin) with a dual-auth middleware that accepts both session and Bearer token auth. React Flow v12 in controlled mode with custom node/edge types. Three floating overlay panels: node palette sidebar, properties panel, and bottom toolbar.

**Tech Stack:** React 19, React Flow v12 (`@xyflow/react`), React Query v5, Elk.js, Axios, react-router-dom, Vite 7, Tailwind CSS 3

**Spec:** `docs/superpowers/specs/2026-04-08-react-flow-migration-design.md`
**PHP Path:** `C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `app/Http/Middleware/DualAuthentication.php` | Tries session auth, falls back to Bearer token |
| `resources/js/spa/main.jsx` | React entry point: providers + router |
| `resources/js/spa/App.jsx` | Route definitions |
| `resources/js/spa/api/client.js` | Axios instance with CSRF + credentials |
| `resources/js/spa/api/graph.js` | Graph API wrappers |
| `resources/js/spa/api/prompts.js` | Prompt update API wrapper |
| `resources/js/spa/hooks/useGraphData.js` | Transform API → React Flow nodes/edges |
| `resources/js/spa/hooks/useDebounce.js` | Debounce utility |
| `resources/js/spa/hooks/useElkLayout.js` | Elk.js layout computation |
| `resources/js/spa/hooks/useMermaidExport.js` | Mermaid flowchart export |
| `resources/js/spa/components/Layout.jsx` | Minimal nav bar |
| `resources/js/spa/components/canvas/FlowCanvas.jsx` | React Flow wrapper |
| `resources/js/spa/components/canvas/nodes/PromptNode.jsx` | Prompt node card |
| `resources/js/spa/components/canvas/nodes/FragmentNode.jsx` | Fragment node card |
| `resources/js/spa/components/canvas/nodes/CollectionNode.jsx` | Collection node card |
| `resources/js/spa/components/canvas/edges/CompositionEdge.jsx` | Animated edge with delete |
| `resources/js/spa/components/canvas/Sidebar.jsx` | Node palette float |
| `resources/js/spa/components/canvas/PropertiesPanel.jsx` | Properties float |
| `resources/js/spa/components/canvas/Toolbar.jsx` | Bottom toolbar float |
| `resources/js/spa/pages/CanvasPage.jsx` | Main graph view |
| `resources/js/spa/pages/WorkspacePage.jsx` | Placeholder |
| `resources/js/spa/pages/BrowsePage.jsx` | Placeholder |
| `resources/js/spa/pages/SettingsPage.jsx` | Placeholder |
| `resources/js/spa/styles/flow.css` | React Flow dark theme overrides |

### Modified Files

| File | Change |
|------|--------|
| `package.json` | Add React, React Flow, React Query, Elk.js, react-router-dom |
| `vite.config.js` | Add React plugin, SPA entry point |
| `tailwind.config.js` | Add `.jsx` to content paths |
| `bootstrap/app.php` | Register `dual.auth` middleware alias |
| `routes/api.php` | Move graph routes to `dual.auth` middleware |

---

## Task 1: Toolchain + API Client + Auth Bridge

**Files:**
- Modify: `package.json` (via npm install)
- Modify: `vite.config.js`
- Modify: `tailwind.config.js`
- Create: `app/Http/Middleware/DualAuthentication.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/api.php`
- Create: `resources/js/spa/api/client.js`
- Create: `resources/js/spa/api/graph.js`
- Create: `resources/js/spa/api/prompts.js`

- [ ] **Step 1: Install npm packages**

```bash
cd "C:/#DATA/Onedrive/Apps/URGEnt"
npm install react react-dom react-router-dom @xyflow/react @tanstack/react-query elkjs
npm install -D @vitejs/plugin-react
```

- [ ] **Step 2: Update vite.config.js**

Replace the entire file with:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/spa/main.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
```

- [ ] **Step 3: Update tailwind.config.js**

Change the content array to include `.jsx` files:

```javascript
content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{js,jsx}',
],
```

- [ ] **Step 4: Create DualAuthentication middleware**

```php
<?php
// app/Http/Middleware/DualAuthentication.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DualAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try session auth first (SPA with cookies)
        if ($request->user()) {
            return $next($request);
        }

        // Fall back to API key auth (Bearer token)
        return app(ApiKeyAuthentication::class)->handle($request, $next);
    }
}
```

- [ ] **Step 5: Register middleware and update routes**

In `bootstrap/app.php`, add to the alias array:

```php
'dual.auth' => \App\Http\Middleware\DualAuthentication::class,
```

In `routes/api.php`, change the 5 graph routes from `api.auth` to `dual.auth` middleware. Move these lines:

```php
Route::get('graph/nodes', [GraphController::class, 'nodes']);
Route::post('graph/positions', [GraphController::class, 'positions']);
Route::get('graph/edges', [GraphController::class, 'edges']);
Route::post('prompts/{username}/{promptSlug}/append-include', [GraphController::class, 'appendInclude']);
Route::delete('prompts/{username}/{promptSlug}/remove-include', [GraphController::class, 'removeInclude']);
```

Into a new group:

```php
// Graph endpoints — accept both session auth (SPA) and Bearer token (API)
Route::middleware('dual.auth')->group(function () {
    Route::get('graph/nodes', [GraphController::class, 'nodes']);
    Route::post('graph/positions', [GraphController::class, 'positions']);
    Route::get('graph/edges', [GraphController::class, 'edges']);
    Route::post('prompts/{username}/{promptSlug}/append-include', [GraphController::class, 'appendInclude']);
    Route::delete('prompts/{username}/{promptSlug}/remove-include', [GraphController::class, 'removeInclude']);
});
```

Remove those 5 routes from the `api.auth` group (they're now in `dual.auth`).

- [ ] **Step 6: Create API client**

```javascript
// resources/js/spa/api/client.js
import axios from 'axios';

const client = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Add CSRF token from meta tag
client.interceptors.request.use((config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
});

export default client;
```

- [ ] **Step 7: Create graph API module**

```javascript
// resources/js/spa/api/graph.js
import client from './client';

export async function getNodes() {
    const { data } = await client.get('/graph/nodes');
    return data;
}

export async function getEdges() {
    const { data } = await client.get('/graph/edges');
    return data;
}

export async function savePositions(positions) {
    const { data } = await client.post('/graph/positions', { positions });
    return data;
}

export async function appendInclude(username, slug, fragmentSlug) {
    const { data } = await client.post(`/prompts/${username}/${slug}/append-include`, {
        fragment_slug: fragmentSlug,
    });
    return data;
}

export async function removeInclude(username, slug, fragmentSlug) {
    const { data } = await client.delete(`/prompts/${username}/${slug}/remove-include`, {
        data: { fragment_slug: fragmentSlug },
    });
    return data;
}
```

- [ ] **Step 8: Create prompts API module**

```javascript
// resources/js/spa/api/prompts.js
import client from './client';

export async function updatePrompt(username, slug, data) {
    const { data: response } = await client.patch(`/prompts/${username}/${slug}`, data);
    return response;
}
```

- [ ] **Step 9: Verify build**

```bash
npm run build
```

Expected: Vite builds without errors. Three entry points in manifest.

- [ ] **Step 10: Run PHP tests to verify auth bridge**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test tests/Feature/Api/GraphApiTest.php
```

Expected: All 14 graph tests still pass (Bearer token auth continues to work through `DualAuthentication` fallback).

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: install React toolchain, API client, dual-auth middleware"
```

---

## Task 2: App Shell + FlowCanvas + useGraphData

**Files:**
- Create: `resources/js/spa/main.jsx`
- Create: `resources/js/spa/App.jsx`
- Create: `resources/js/spa/components/Layout.jsx`
- Create: `resources/js/spa/hooks/useGraphData.js`
- Create: `resources/js/spa/hooks/useDebounce.js`
- Create: `resources/js/spa/components/canvas/FlowCanvas.jsx`
- Create: `resources/js/spa/styles/flow.css`
- Create: `resources/js/spa/pages/CanvasPage.jsx`
- Create: `resources/js/spa/pages/WorkspacePage.jsx`
- Create: `resources/js/spa/pages/BrowsePage.jsx`
- Create: `resources/js/spa/pages/SettingsPage.jsx`

- [ ] **Step 1: Create main.jsx**

```jsx
// resources/js/spa/main.jsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './App';
import './styles/flow.css';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 30_000,
            retry: 1,
        },
    },
});

ReactDOM.createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <QueryClientProvider client={queryClient}>
            <BrowserRouter basename="/app">
                <App />
            </BrowserRouter>
        </QueryClientProvider>
    </React.StrictMode>
);
```

- [ ] **Step 2: Create App.jsx with routes**

```jsx
// resources/js/spa/App.jsx
import { Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import CanvasPage from './pages/CanvasPage';
import WorkspacePage from './pages/WorkspacePage';
import BrowsePage from './pages/BrowsePage';
import SettingsPage from './pages/SettingsPage';

export default function App() {
    return (
        <Layout>
            <Routes>
                <Route path="/canvas" element={<CanvasPage />} />
                <Route path="/workspace/:username/:slug" element={<WorkspacePage />} />
                <Route path="/browse" element={<BrowsePage />} />
                <Route path="/settings" element={<SettingsPage />} />
                <Route path="*" element={<Navigate to="/canvas" replace />} />
            </Routes>
        </Layout>
    );
}
```

- [ ] **Step 3: Create Layout.jsx**

```jsx
// resources/js/spa/components/Layout.jsx
export default function Layout({ children }) {
    return (
        <div className="h-screen w-screen flex flex-col bg-gray-900 text-gray-100">
            <nav className="h-12 flex items-center justify-between px-4 bg-gray-800 border-b border-gray-700 shrink-0 z-50">
                <a href="/app/canvas" className="text-lg font-bold text-indigo-400">URGE</a>
                <div className="flex items-center gap-4 text-sm">
                    <a href="/app/canvas" className="text-gray-300 hover:text-white">Canvas</a>
                    <a href="/browse" className="text-gray-400 hover:text-white">Browse (Classic)</a>
                    <a href="/settings" className="text-gray-400 hover:text-white">Settings</a>
                </div>
            </nav>
            <main className="flex-1 overflow-hidden">
                {children}
            </main>
        </div>
    );
}
```

- [ ] **Step 4: Create useDebounce hook**

```javascript
// resources/js/spa/hooks/useDebounce.js
import { useRef, useCallback } from 'react';

export default function useDebounce(fn, delay = 300) {
    const timeoutRef = useRef(null);

    return useCallback((...args) => {
        if (timeoutRef.current) clearTimeout(timeoutRef.current);
        timeoutRef.current = setTimeout(() => fn(...args), delay);
    }, [fn, delay]);
}
```

- [ ] **Step 5: Create useGraphData hook**

```jsx
// resources/js/spa/hooks/useGraphData.js
import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getNodes, getEdges } from '../api/graph';

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
        const collectionEdges = edgesQuery.data.data?.collection ?? [];
        const meta = nodesQuery.data.meta;

        // Count incoming edges per fragment slug for "Used in N" badge
        const incomingCount = {};
        compositionEdges.forEach((e) => {
            incomingCount[e.target_slug] = (incomingCount[e.target_slug] || 0) + 1;
        });

        // Build slug→nodeId map for edge resolution
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
                data: {
                    ...p,
                    incomingEdgeCount: incomingCount[p.slug] || 0,
                },
            };
        });

        const collectionNodes = apiCollections.map((c) => {
            const nodeId = `collection-${c.id}`;
            slugToNodeId[c.slug] = nodeId;
            const position = c.position || autoPosition(posIndex++);
            return {
                id: nodeId,
                type: 'collection',
                position,
                data: c,
            };
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

        return {
            nodes: [...flowNodes, ...collectionNodes],
            edges: flowEdges,
            meta,
        };
    }, [nodesQuery.data, edgesQuery.data]);

    return {
        nodes,
        edges,
        meta,
        isLoading: nodesQuery.isLoading || edgesQuery.isLoading,
        error: nodesQuery.error || edgesQuery.error,
        refetch: () => {
            nodesQuery.refetch();
            edgesQuery.refetch();
        },
    };
}
```

- [ ] **Step 6: Create flow.css**

```css
/* resources/js/spa/styles/flow.css */
@import '@xyflow/react/dist/style.css';

/* Dark theme overrides */
.react-flow__background {
    background-color: #111827; /* gray-900 */
}

.react-flow__minimap {
    background-color: #1f2937; /* gray-800 */
}

.react-flow__controls button {
    background-color: #374151; /* gray-700 */
    color: #e5e7eb; /* gray-200 */
    border-color: #4b5563; /* gray-600 */
}

.react-flow__controls button:hover {
    background-color: #4b5563;
}

/* Edge animation */
@keyframes dash {
    to {
        stroke-dashoffset: -10;
    }
}

.animated-edge {
    animation: dash 0.5s linear infinite;
}
```

- [ ] **Step 7: Create FlowCanvas.jsx**

```jsx
// resources/js/spa/components/canvas/FlowCanvas.jsx
import { useCallback, useRef } from 'react';
import {
    ReactFlow,
    useNodesState,
    useEdgesState,
    Background,
    Controls,
    MiniMap,
    useReactFlow,
} from '@xyflow/react';
import { useQueryClient } from '@tanstack/react-query';
import { savePositions } from '../../api/graph';
import useDebounce from '../../hooks/useDebounce';
import PromptNode from './nodes/PromptNode';
import FragmentNode from './nodes/FragmentNode';
import CollectionNode from './nodes/CollectionNode';
import CompositionEdge from './edges/CompositionEdge';

const nodeTypes = { prompt: PromptNode, fragment: FragmentNode, collection: CollectionNode };
const edgeTypes = { composition: CompositionEdge };

export default function FlowCanvas({
    initialNodes,
    initialEdges,
    onNodeSelect,
    onDragFromPalette,
}) {
    const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);
    const reactFlowWrapper = useRef(null);
    const queryClient = useQueryClient();

    // Sync when initialNodes/initialEdges change (e.g., after refetch)
    // (React Flow's useNodesState doesn't auto-update from props)

    const debouncedSave = useDebounce(async (changedNodes) => {
        const positions = changedNodes.map((n) => {
            const [type, id] = n.id.split('-');
            return {
                node_type: type,
                node_id: parseInt(id),
                x: n.position.x,
                y: n.position.y,
            };
        });
        await savePositions(positions);
    }, 300);

    const handleNodesChange = useCallback(
        (changes) => {
            onNodesChange(changes);
            // Save positions when drag ends
            const positionChanges = changes.filter(
                (c) => c.type === 'position' && c.dragging === false
            );
            if (positionChanges.length > 0) {
                const changedIds = positionChanges.map((c) => c.id);
                // Get current nodes after change is applied
                setTimeout(() => {
                    const currentNodes = document.querySelectorAll ? [] : []; // We need the actual nodes
                    // Use a callback to get the latest nodes
                }, 0);
            }
        },
        [onNodesChange]
    );

    const handleNodeDragStop = useCallback(
        (_event, node) => {
            const [type, id] = node.id.split('-');
            savePositions([{
                node_type: type,
                node_id: parseInt(id),
                x: node.position.x,
                y: node.position.y,
            }]);
        },
        []
    );

    const handleSelectionChange = useCallback(
        ({ nodes: selectedNodes }) => {
            if (selectedNodes.length === 1) {
                onNodeSelect?.(selectedNodes[0]);
            } else {
                onNodeSelect?.(null);
            }
        },
        [onNodeSelect]
    );

    const handleDragOver = useCallback((event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }, []);

    const handleDrop = useCallback(
        (event) => {
            event.preventDefault();
            const raw = event.dataTransfer.getData('application/reactflow');
            if (!raw) return;

            const { nodeType, nodeId, data } = JSON.parse(raw);
            const reactFlowBounds = reactFlowWrapper.current?.getBoundingClientRect();
            if (!reactFlowBounds) return;

            // This requires useReactFlow — we'll handle in CanvasPage via ref
            onDragFromPalette?.({ nodeType, nodeId, data, event });
        },
        [onDragFromPalette]
    );

    return (
        <div ref={reactFlowWrapper} className="w-full h-full">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onNodeDragStop={handleNodeDragStop}
                onSelectionChange={handleSelectionChange}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
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
```

- [ ] **Step 8: Create CanvasPage and placeholder pages**

```jsx
// resources/js/spa/pages/CanvasPage.jsx
import { ReactFlowProvider } from '@xyflow/react';
import useGraphData from '../hooks/useGraphData';
import FlowCanvas from '../components/canvas/FlowCanvas';

export default function CanvasPage() {
    const { nodes, edges, meta, isLoading, error } = useGraphData();

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
                <FlowCanvas initialNodes={nodes} initialEdges={edges} />
            </div>
        </ReactFlowProvider>
    );
}
```

Placeholder pages:

```jsx
// resources/js/spa/pages/WorkspacePage.jsx
import { useParams, Link } from 'react-router-dom';

export default function WorkspacePage() {
    const { username, slug } = useParams();
    return (
        <div className="h-full flex items-center justify-center text-gray-400">
            <div className="text-center">
                <h2 className="text-xl mb-2">Workspace: {username}/{slug}</h2>
                <p className="mb-4">Coming in Phase 4</p>
                <a href={`/prompts/${username}/${slug}`} className="text-indigo-400 hover:underline">
                    Open in Classic UI →
                </a>
                <br />
                <Link to="/canvas" className="text-gray-500 hover:text-gray-300 text-sm mt-2 inline-block">
                    ← Back to Canvas
                </Link>
            </div>
        </div>
    );
}
```

```jsx
// resources/js/spa/pages/BrowsePage.jsx
export default function BrowsePage() {
    return (
        <div className="h-full flex items-center justify-center text-gray-400">
            <div className="text-center">
                <h2 className="text-xl mb-2">Browse</h2>
                <p className="mb-4">Coming in Phase 5</p>
                <a href="/browse" className="text-indigo-400 hover:underline">Open Classic Browse →</a>
            </div>
        </div>
    );
}
```

```jsx
// resources/js/spa/pages/SettingsPage.jsx
export default function SettingsPage() {
    return (
        <div className="h-full flex items-center justify-center text-gray-400">
            <div className="text-center">
                <h2 className="text-xl mb-2">Settings</h2>
                <p className="mb-4">Coming in Phase 5</p>
                <a href="/settings" className="text-indigo-400 hover:underline">Open Classic Settings →</a>
            </div>
        </div>
    );
}
```

- [ ] **Step 9: Verify the canvas renders**

```bash
npm run dev
```

Navigate to `http://127.0.0.1:8080/app/canvas` (must be logged in). The React Flow canvas should render with nodes as default rectangles (custom node components come in Task 3). Nodes should be draggable and positions should persist on refresh.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: React SPA shell with FlowCanvas and graph data hooks"
```

---

## Task 3: Custom Node Components

**Files:**
- Create: `resources/js/spa/components/canvas/nodes/PromptNode.jsx`
- Create: `resources/js/spa/components/canvas/nodes/FragmentNode.jsx`
- Create: `resources/js/spa/components/canvas/nodes/CollectionNode.jsx`

- [ ] **Step 1: Create PromptNode**

```jsx
// resources/js/spa/components/canvas/nodes/PromptNode.jsx
import { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

function PromptNode({ data, selected }) {
    const borderColor = data.category?.color || '#6366f1';
    const tags = data.tags || [];
    const visibleTags = tags.slice(0, 3);
    const extraCount = tags.length - 3;
    const version = data.active_version;
    const includeCount = version?.includes?.length || 0;
    const varCount = version?.variables?.length || 0;

    return (
        <div
            className={`bg-gray-800 rounded-lg shadow-lg min-w-[220px] max-w-[260px] ${
                selected ? 'ring-2 ring-indigo-400' : ''
            }`}
            style={{ borderLeft: `4px solid ${borderColor}` }}
        >
            <Handle type="target" position={Position.Top} className="!bg-indigo-500 !w-2.5 !h-2.5" />

            <div className="p-3">
                <div className="flex items-center justify-between gap-2 mb-1">
                    <span className="font-semibold text-sm text-gray-100 truncate">{data.name}</span>
                    <span className="text-[10px] bg-indigo-900 text-indigo-300 px-1.5 py-0.5 rounded-full shrink-0">
                        prompt
                    </span>
                </div>

                <div className="text-[10px] text-gray-500 mb-2">
                    {data.owner}/{data.slug} · v{version?.version_number || '?'}
                </div>

                {visibleTags.length > 0 && (
                    <div className="flex gap-1 flex-wrap mb-2">
                        {visibleTags.map((tag) => (
                            <span key={tag} className="bg-gray-700 text-gray-300 text-[9px] px-1.5 py-0.5 rounded-full">
                                {tag}
                            </span>
                        ))}
                        {extraCount > 0 && (
                            <span className="text-gray-500 text-[9px] py-0.5">+{extraCount}</span>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-3 text-[10px] text-gray-500">
                    {includeCount > 0 && <span>🔗 {includeCount}</span>}
                    <span>📊 {data.results_count || 0}</span>
                    {varCount > 0 && <span>⚙ {varCount}</span>}
                </div>
            </div>

            <Handle type="source" position={Position.Bottom} className="!bg-indigo-500 !w-2.5 !h-2.5" />
        </div>
    );
}

export default memo(PromptNode);
```

- [ ] **Step 2: Create FragmentNode**

```jsx
// resources/js/spa/components/canvas/nodes/FragmentNode.jsx
import { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

function FragmentNode({ data, selected }) {
    const content = data.active_version?.content || '';
    const preview = content.length > 80 ? content.slice(0, 80) + '…' : content;

    return (
        <div
            className={`bg-gray-900 border-2 border-dashed border-blue-500 rounded-lg shadow-lg min-w-[200px] max-w-[240px] ${
                selected ? 'ring-2 ring-blue-400' : ''
            }`}
        >
            <Handle type="target" position={Position.Top} className="!bg-blue-500 !w-2.5 !h-2.5" />

            <div className="p-3">
                <div className="flex items-center justify-between gap-2 mb-1">
                    <span className="font-semibold text-sm text-gray-100 truncate">{data.name}</span>
                    <span className="text-[10px] bg-blue-900 text-blue-300 px-1.5 py-0.5 rounded-full shrink-0">
                        fragment
                    </span>
                </div>

                <div className="text-[10px] text-gray-500 mb-2">
                    {data.owner}/{data.slug}
                </div>

                {preview && (
                    <div className="bg-gray-800 rounded p-1.5 mb-2 font-mono text-[10px] text-gray-400 leading-relaxed max-h-10 overflow-hidden">
                        {preview}
                    </div>
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
```

- [ ] **Step 3: Create CollectionNode**

```jsx
// resources/js/spa/components/canvas/nodes/CollectionNode.jsx
import { memo } from 'react';

function CollectionNode({ data, selected }) {
    return (
        <div
            className={`bg-purple-900/20 border-2 border-purple-500 rounded-xl shadow-lg min-w-[200px] ${
                selected ? 'ring-2 ring-purple-400' : ''
            }`}
        >
            <div className="p-3">
                <div className="flex items-center justify-between gap-2">
                    <span className="font-semibold text-sm text-gray-100 truncate">{data.title}</span>
                    <span className="text-[10px] bg-purple-900 text-purple-300 px-1.5 py-0.5 rounded-full shrink-0">
                        {data.items_count} items
                    </span>
                </div>
                {data.description && (
                    <div className="text-[10px] text-gray-500 mt-1 truncate">{data.description}</div>
                )}
            </div>
        </div>
    );
}

export default memo(CollectionNode);
```

- [ ] **Step 4: Verify nodes render with custom styles**

Navigate to `/app/canvas`. Prompts should show as styled cards with category-colored left borders, tag chips, and stats. Fragments should have dashed blue borders with content previews. Collections should have purple borders.

- [ ] **Step 5: Commit**

```bash
git add resources/js/spa/components/canvas/nodes/
git commit -m "feat: custom PromptNode, FragmentNode, CollectionNode components"
```

---

## Task 4: Custom Edge + Connect

**Files:**
- Create: `resources/js/spa/components/canvas/edges/CompositionEdge.jsx`
- Modify: `resources/js/spa/components/canvas/FlowCanvas.jsx` (onConnect handler)

- [ ] **Step 1: Create CompositionEdge**

```jsx
// resources/js/spa/components/canvas/edges/CompositionEdge.jsx
import { memo, useState } from 'react';
import { BaseEdge, EdgeLabelRenderer, getBezierPath } from '@xyflow/react';
import { useQueryClient } from '@tanstack/react-query';
import { removeInclude } from '../../../api/graph';

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
                style={{
                    stroke: '#818cf8',
                    strokeWidth: 1.5,
                    strokeDasharray: '5 5',
                }}
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
```

- [ ] **Step 2: Add onConnect handler to FlowCanvas**

In `FlowCanvas.jsx`, add an `onConnect` callback that creates a new include when the user draws an edge from a prompt to a fragment:

```jsx
import { appendInclude } from '../../api/graph';

// Inside FlowCanvas component:
const handleConnect = useCallback(
    async (params) => {
        const sourceNode = nodes.find((n) => n.id === params.source);
        const targetNode = nodes.find((n) => n.id === params.target);
        if (!sourceNode || !targetNode) return;

        // Only allow prompt → fragment connections
        if (targetNode.type !== 'fragment') return;

        const owner = sourceNode.data.owner;
        const slug = sourceNode.data.slug;
        const fragmentSlug = targetNode.data.slug;

        try {
            await appendInclude(owner, slug, fragmentSlug);
            queryClient.invalidateQueries({ queryKey: ['graph'] });
        } catch (err) {
            console.error('Failed to add include:', err);
        }
    },
    [nodes, queryClient]
);
```

Add `onConnect={handleConnect}` to the `<ReactFlow>` component props.

- [ ] **Step 3: Verify edges and connections**

Edges should render as animated dashed indigo lines with labels. Hover over label shows delete (×) button. Drawing a new edge from prompt to fragment should create an include.

- [ ] **Step 4: Commit**

```bash
git add resources/js/spa/components/canvas/edges/ resources/js/spa/components/canvas/FlowCanvas.jsx
git commit -m "feat: CompositionEdge with delete, onConnect for new includes"
```

---

## Task 5: Sidebar + useElkLayout

**Files:**
- Create: `resources/js/spa/hooks/useElkLayout.js`
- Create: `resources/js/spa/components/canvas/Sidebar.jsx`
- Modify: `resources/js/spa/pages/CanvasPage.jsx` (sidebar state + keyboard shortcut)

- [ ] **Step 1: Create useElkLayout hook**

```javascript
// resources/js/spa/hooks/useElkLayout.js
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
                return {
                    ...node,
                    position: { x: elkNode.x || 0, y: elkNode.y || 0 },
                };
            });
        } finally {
            setIsLayouting(false);
        }
    }, []);

    return { getLayoutedNodes, isLayouting };
}
```

- [ ] **Step 2: Create Sidebar**

```jsx
// resources/js/spa/components/canvas/Sidebar.jsx
import { useState, useMemo } from 'react';

export default function Sidebar({ visible, nodes, layoutMode, onLayoutChange }) {
    const [search, setSearch] = useState('');
    const [scope, setScope] = useState('all');

    const filtered = useMemo(() => {
        if (!nodes) return { prompts: [], fragments: [], collections: [] };
        let items = nodes;
        if (search) {
            const q = search.toLowerCase();
            items = items.filter(
                (n) => (n.data.name || n.data.title || '').toLowerCase().includes(q) ||
                       (n.data.slug || '').toLowerCase().includes(q)
            );
        }
        return {
            prompts: items.filter((n) => n.type === 'prompt'),
            fragments: items.filter((n) => n.type === 'fragment'),
            collections: items.filter((n) => n.type === 'collection'),
        };
    }, [nodes, search]);

    const handleDragStart = (event, node) => {
        event.dataTransfer.setData(
            'application/reactflow',
            JSON.stringify({ nodeType: node.type, nodeId: node.id, data: node.data })
        );
        event.dataTransfer.effectAllowed = 'move';
    };

    if (!visible) return null;

    return (
        <div className="fixed left-0 top-12 bottom-0 w-72 bg-gray-800 border-r border-gray-700 z-30 flex flex-col overflow-hidden transition-transform">
            <div className="p-3 border-b border-gray-700">
                <div className="flex items-center justify-between mb-2">
                    <span className="font-semibold text-sm text-gray-100">Nodes</span>
                    <span className="text-[10px] text-gray-500">Ctrl+B</span>
                </div>
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search prompts..."
                    className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-2.5 py-1.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                />
            </div>

            <div className="flex-1 overflow-y-auto p-3 space-y-4">
                {filtered.prompts.length > 0 && (
                    <Section title="Prompts" items={filtered.prompts} onDragStart={handleDragStart} />
                )}
                {filtered.fragments.length > 0 && (
                    <Section title="Fragments" items={filtered.fragments} onDragStart={handleDragStart} />
                )}
                {filtered.collections.length > 0 && (
                    <Section title="Collections" items={filtered.collections} onDragStart={handleDragStart} />
                )}
                {filtered.prompts.length === 0 && filtered.fragments.length === 0 && filtered.collections.length === 0 && (
                    <p className="text-gray-500 text-sm text-center py-4">No matches</p>
                )}
            </div>

            <div className="p-3 border-t border-gray-700">
                <div className="text-[9px] text-gray-500 uppercase tracking-wider mb-2">Layout</div>
                <div className="flex gap-1">
                    {['free', 'mrtree', 'layered'].map((mode) => (
                        <button
                            key={mode}
                            onClick={() => onLayoutChange(mode)}
                            className={`flex-1 py-1.5 text-xs rounded ${
                                layoutMode === mode
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                            }`}
                        >
                            {mode === 'free' ? 'Free' : mode === 'mrtree' ? 'Tree' : 'Layer'}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}

function Section({ title, items, onDragStart }) {
    return (
        <div>
            <div className="text-[9px] text-gray-500 uppercase tracking-wider mb-1.5">{title}</div>
            <div className="space-y-1">
                {items.map((node) => (
                    <div
                        key={node.id}
                        draggable
                        onDragStart={(e) => onDragStart(e, node)}
                        className="flex items-center gap-2 bg-gray-700/50 hover:bg-gray-700 rounded px-2 py-1.5 cursor-grab text-xs"
                    >
                        {node.type === 'fragment' ? (
                            <span className="text-blue-400">◇</span>
                        ) : node.type === 'collection' ? (
                            <span className="text-purple-400">□</span>
                        ) : (
                            <span
                                className="w-0.5 h-3.5 rounded-full shrink-0"
                                style={{ backgroundColor: node.data.category?.color || '#6366f1' }}
                            />
                        )}
                        <span className="text-gray-200 truncate">{node.data.name || node.data.title}</span>
                        <span className="ml-auto text-gray-500 text-[10px] shrink-0">
                            {node.type === 'fragment' && `×${node.data.incomingEdgeCount || 0}`}
                            {node.type === 'prompt' && `v${node.data.active_version?.version_number || '?'}`}
                            {node.type === 'collection' && `${node.data.items_count || 0}`}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 3: Wire sidebar into CanvasPage with Ctrl+B**

Update `CanvasPage.jsx` to manage sidebar state and keyboard shortcut:

```jsx
import { useState, useEffect, useCallback } from 'react';
import { ReactFlowProvider } from '@xyflow/react';
import useGraphData from '../hooks/useGraphData';
import useElkLayout from '../hooks/useElkLayout';
import FlowCanvas from '../components/canvas/FlowCanvas';
import Sidebar from '../components/canvas/Sidebar';
import { savePositions } from '../api/graph';

export default function CanvasPage() {
    const { nodes, edges, meta, isLoading, error } = useGraphData();
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

    const handleLayoutChange = useCallback(
        async (mode) => {
            setLayoutMode(mode);
            if (mode === 'free') return;
            const layouted = await getLayoutedNodes(nodes, edges, mode);
            // Save all positions after layout
            const positions = layouted.map((n) => {
                const [type, id] = n.id.split('-');
                return { node_type: type, node_id: parseInt(id), x: n.position.x, y: n.position.y };
            });
            await savePositions(positions);
            // Trigger refetch so FlowCanvas gets new positions
            // (or pass layouted nodes directly — handled in integration)
        },
        [nodes, edges, getLayoutedNodes]
    );

    if (isLoading) {
        return (
            <div className="h-full flex items-center justify-center text-gray-400">
                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full mx-auto mb-4" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="h-full flex items-center justify-center text-red-400">
                <p>Failed to load graph data.</p>
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
```

- [ ] **Step 4: Verify sidebar and layout**

Ctrl+B toggles sidebar. Search filters nodes. Layout buttons trigger Elk.js layout.

- [ ] **Step 5: Commit**

```bash
git add resources/js/spa/hooks/useElkLayout.js resources/js/spa/components/canvas/Sidebar.jsx resources/js/spa/pages/CanvasPage.jsx
git commit -m "feat: Sidebar with search, drag-to-canvas, Elk.js auto-layout"
```

---

## Task 6: PropertiesPanel + Toolbar + Mermaid Export

**Files:**
- Create: `resources/js/spa/hooks/useMermaidExport.js`
- Create: `resources/js/spa/components/canvas/PropertiesPanel.jsx`
- Create: `resources/js/spa/components/canvas/Toolbar.jsx`
- Modify: `resources/js/spa/pages/CanvasPage.jsx` (wire panels)

- [ ] **Step 1: Create useMermaidExport hook**

```javascript
// resources/js/spa/hooks/useMermaidExport.js
import { useCallback } from 'react';

function sanitize(str) {
    return str.replace(/"/g, '#quot;').replace(/[[\]]/g, '');
}

export default function useMermaidExport(nodes, edges) {
    const mermaidString = useCallback(() => {
        const lines = ['flowchart LR'];

        nodes.forEach((node) => {
            const name = sanitize(node.data.name || node.data.title || node.id);
            if (node.type === 'prompt') {
                lines.push(`    ${node.id}["${name}"]`);
            } else if (node.type === 'fragment') {
                lines.push(`    ${node.id}("${name}")`);
            } else if (node.type === 'collection') {
                lines.push(`    ${node.id}{{"${name}"}}`);
            }
        });

        edges.forEach((edge) => {
            const label = edge.data?.label || 'includes';
            lines.push(`    ${edge.source} -->|${label}| ${edge.target}`);
        });

        return lines.join('\n');
    }, [nodes, edges]);

    const copyToClipboard = useCallback(async () => {
        const text = mermaidString();
        await navigator.clipboard.writeText(text);
        return text;
    }, [mermaidString]);

    return { getMermaidString: mermaidString, copyToClipboard };
}
```

- [ ] **Step 2: Create PropertiesPanel**

```jsx
// resources/js/spa/components/canvas/PropertiesPanel.jsx
import { useState, useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { updatePrompt } from '../../api/prompts';

export default function PropertiesPanel({ node, onClose }) {
    const queryClient = useQueryClient();
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [isDirty, setIsDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const d = node?.data;

    useEffect(() => {
        if (d) {
            setName(d.name || d.title || '');
            setDescription(d.description || '');
            setIsDirty(false);
        }
    }, [d]);

    if (!node) return null;

    const isPromptOrFragment = node.type === 'prompt' || node.type === 'fragment';
    const version = d?.active_version;

    const handleSave = async () => {
        if (!isPromptOrFragment || !isDirty) return;
        setSaving(true);
        try {
            await updatePrompt(d.owner, d.slug, { name, description });
            queryClient.invalidateQueries({ queryKey: ['graph'] });
            setIsDirty(false);
        } catch (err) {
            console.error('Save failed:', err);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed right-0 top-12 bottom-0 w-80 bg-gray-800 border-l border-gray-700 z-30 flex flex-col overflow-hidden">
            <div className="flex items-center justify-between p-3 border-b border-gray-700">
                <span className="font-semibold text-sm text-gray-100">Properties</span>
                <button onClick={onClose} className="text-gray-500 hover:text-gray-300 text-lg">×</button>
            </div>

            <div className="flex-1 overflow-y-auto p-3 space-y-3">
                {isPromptOrFragment ? (
                    <>
                        <Field label="Name">
                            <input
                                value={name}
                                onChange={(e) => { setName(e.target.value); setIsDirty(true); }}
                                className="w-full bg-gray-900 border border-gray-600 text-gray-100 text-sm rounded px-2 py-1.5 focus:border-indigo-500 outline-none"
                            />
                        </Field>

                        <div className="flex gap-2">
                            <Field label="Type" className="flex-1">
                                <div className={`text-xs px-2 py-1 rounded text-center ${
                                    d.type === 'fragment' ? 'bg-blue-900 text-blue-300' : 'bg-indigo-900 text-indigo-300'
                                }`}>
                                    {d.type}
                                </div>
                            </Field>
                            <Field label="Category" className="flex-1">
                                <div className="flex items-center gap-1.5 text-xs text-gray-300">
                                    {d.category && (
                                        <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: d.category.color }} />
                                    )}
                                    {d.category?.name || 'None'}
                                </div>
                            </Field>
                        </div>

                        <Field label="Description">
                            <textarea
                                value={description}
                                onChange={(e) => { setDescription(e.target.value); setIsDirty(true); }}
                                rows={3}
                                className="w-full bg-gray-900 border border-gray-600 text-gray-100 text-sm rounded px-2 py-1.5 focus:border-indigo-500 outline-none resize-none"
                            />
                        </Field>

                        {version && (
                            <Field label="Version">
                                <div className="bg-gray-900 rounded p-2 text-xs text-gray-300">
                                    v{version.version_number}
                                </div>
                            </Field>
                        )}

                        {version?.variables?.length > 0 && (
                            <Field label={`Variables (${version.variables.length})`}>
                                <div className="flex flex-wrap gap-1">
                                    {version.variables.map((v) => (
                                        <span key={v} className="bg-blue-900/50 text-blue-300 text-[10px] px-1.5 py-0.5 rounded font-mono">{v}</span>
                                    ))}
                                </div>
                            </Field>
                        )}

                        {version?.includes?.length > 0 && (
                            <Field label={`Includes (${version.includes.length})`}>
                                <div className="space-y-1">
                                    {version.includes.map((inc) => (
                                        <div key={inc} className="text-indigo-400 text-xs cursor-pointer hover:underline">
                                            → {inc}
                                        </div>
                                    ))}
                                </div>
                            </Field>
                        )}

                        {(d.tags || []).length > 0 && (
                            <Field label="Tags">
                                <div className="flex flex-wrap gap-1">
                                    {d.tags.map((t) => (
                                        <span key={t} className="bg-gray-700 text-gray-300 text-[10px] px-1.5 py-0.5 rounded-full">{t}</span>
                                    ))}
                                </div>
                            </Field>
                        )}
                    </>
                ) : (
                    <>
                        <Field label="Title">
                            <div className="text-sm text-gray-100">{d.title}</div>
                        </Field>
                        <Field label="Items">
                            <div className="text-sm text-gray-300">{d.items_count} items</div>
                        </Field>
                    </>
                )}
            </div>

            <div className="p-3 border-t border-gray-700 space-y-2">
                {isDirty && (
                    <button
                        onClick={handleSave}
                        disabled={saving}
                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm py-2 rounded-lg disabled:opacity-50"
                    >
                        {saving ? 'Saving...' : 'Save Changes'}
                    </button>
                )}
                {isPromptOrFragment && (
                    <a
                        href={`/prompts/${d.owner}/${d.slug}`}
                        className="block w-full bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm py-2 rounded-lg text-center"
                    >
                        Open in Editor
                    </a>
                )}
            </div>
        </div>
    );
}

function Field({ label, className, children }) {
    return (
        <div className={className}>
            <div className="text-[9px] text-gray-500 uppercase tracking-wider mb-1">{label}</div>
            {children}
        </div>
    );
}
```

- [ ] **Step 3: Create Toolbar**

```jsx
// resources/js/spa/components/canvas/Toolbar.jsx
import { useReactFlow } from '@xyflow/react';

export default function Toolbar({ layoutMode, onLayoutChange, onMermaidExport, onToggleSidebar, isLayouting }) {
    const { fitView } = useReactFlow();

    return (
        <div className="fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-800 border border-gray-700 rounded-xl px-4 py-2 z-30 flex items-center gap-2 shadow-xl">
            <div className="flex bg-gray-900 rounded-lg p-0.5 gap-0.5">
                {[
                    { mode: 'free', label: 'Free' },
                    { mode: 'mrtree', label: 'Tree' },
                    { mode: 'layered', label: 'Layer' },
                ].map(({ mode, label }) => (
                    <button
                        key={mode}
                        onClick={() => onLayoutChange(mode)}
                        disabled={isLayouting}
                        className={`px-3 py-1 text-xs rounded-md transition-colors ${
                            layoutMode === mode
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-400 hover:text-gray-200'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <span className="text-gray-600">|</span>

            <button onClick={() => fitView({ duration: 300 })} className="text-gray-400 hover:text-white text-xs flex items-center gap-1">
                📐 Fit
            </button>

            <button onClick={onMermaidExport} className="text-gray-400 hover:text-white text-xs flex items-center gap-1">
                🔀 Mermaid
            </button>

            <span className="text-gray-600">|</span>

            <button onClick={onToggleSidebar} className="text-gray-400 hover:text-white text-xs flex items-center gap-1">
                📋 Sidebar
            </button>
        </div>
    );
}
```

- [ ] **Step 4: Wire PropertiesPanel and Toolbar into CanvasPage**

Update `CanvasPage.jsx` — add `selectedNode` state, pass `onNodeSelect` to FlowCanvas, render PropertiesPanel and Toolbar:

Add state:
```jsx
const [selectedNode, setSelectedNode] = useState(null);
```

Add mermaid export:
```jsx
import useMermaidExport from '../hooks/useMermaidExport';

// Inside component:
const { copyToClipboard } = useMermaidExport(nodes, edges);

const handleMermaidExport = async () => {
    await copyToClipboard();
    // Optionally show a toast
};
```

Add to JSX (inside the ReactFlowProvider div):
```jsx
<PropertiesPanel node={selectedNode} onClose={() => setSelectedNode(null)} />
<Toolbar
    layoutMode={layoutMode}
    onLayoutChange={handleLayoutChange}
    onMermaidExport={handleMermaidExport}
    onToggleSidebar={() => setSidebarVisible((v) => !v)}
    isLayouting={isLayouting}
/>
```

Pass `onNodeSelect={setSelectedNode}` to FlowCanvas.

- [ ] **Step 5: Verify all panels**

Select node → properties panel appears. Edit name → save → prompt updates. Toolbar layout buttons work. Mermaid export copies to clipboard. Fit View centers graph.

- [ ] **Step 6: Commit**

```bash
git add resources/js/spa/hooks/useMermaidExport.js resources/js/spa/components/canvas/PropertiesPanel.jsx resources/js/spa/components/canvas/Toolbar.jsx resources/js/spa/pages/CanvasPage.jsx
git commit -m "feat: PropertiesPanel, Toolbar, Mermaid export"
```

---

## Task 7: Integration + Polish + Build Verification

**Files:**
- Modify: various (polish, edge cases, build verification)

- [ ] **Step 1: Handle empty state**

In `CanvasPage.jsx`, if `nodes.length === 0`:

```jsx
if (nodes.length === 0) {
    return (
        <ReactFlowProvider>
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
        </ReactFlowProvider>
    );
}
```

- [ ] **Step 2: Production build**

```bash
npm run build
```

Expected: Vite builds all entry points. No errors.

- [ ] **Step 3: Manual end-to-end verification**

Start the dev server:
```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan serve --port=8080
npm run dev
```

Test checklist:
1. Navigate to `http://localhost:8080/app/canvas` (must be logged in)
2. Nodes render as styled cards (prompt with category border, fragment with dashed blue)
3. Edges render as animated dashed indigo lines with labels
4. Drag a node → position persists on page refresh
5. Ctrl+B toggles sidebar
6. Search in sidebar filters nodes
7. Click a node → properties panel shows on right
8. Edit name in properties → Save → refreshes graph
9. Click layout mode (Tree/Layer) → Elk.js rearranges nodes
10. Click Mermaid export → valid Mermaid syntax in clipboard
11. Click Fit View → canvas centers on all nodes
12. No console errors

- [ ] **Step 4: Run PHP tests (regression check)**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 351+ tests pass (graph tests still work through DualAuthentication).

- [ ] **Step 5: Commit and push**

```bash
git add -A
git commit -m "feat: Phase 2 complete — React Flow canvas with floating panels"
git push
```

---

## Verification Summary

| Feature | How to verify |
|---------|--------------|
| Canvas renders | `/app/canvas` shows React Flow with nodes |
| Custom nodes | Prompt=solid card, Fragment=dashed blue, Collection=purple |
| Edges | Animated dashed indigo lines with slug labels |
| Drag persistence | Drag node → refresh → position preserved |
| Sidebar toggle | Ctrl+B shows/hides palette |
| Search | Type in sidebar → nodes filter |
| Properties panel | Click node → panel shows metadata |
| Edit + save | Change name → Save → graph updates |
| Elk.js layout | Tree/Layer buttons rearrange nodes |
| Mermaid export | Toolbar button → clipboard has valid Mermaid |
| Connect | Draw edge prompt→fragment → creates include |
| Delete edge | Hover edge label → click × → removes include |
| Auth works | Session cookies (SPA) + Bearer tokens (API) both work |
| Build | `npm run build` succeeds |
| PHP tests | `php artisan test` → 351+ pass |
