# Canvas Storyteller Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the canvas from a static prompt map into a full data graph — prompts → results → evaluations — with layer toggles and collapse/expand.

**Architecture:** Extend the graph API to return results and evaluations based on a `layers` query param. Add ResultNode and EvaluationNode React components. Add LayerToggles toolbar component. Collapse/expand state managed per-prompt in CanvasPage. Positions saved via existing GraphPosition model.

**Tech Stack:** React 19, @xyflow/react, React Query, Tailwind CSS, Laravel 12
**PHP Path:** `C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`
**Spec:** `docs/superpowers/specs/2026-04-15-canvas-storyteller-design.md`

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `resources/js/spa/components/canvas/nodes/ResultNode.jsx` | Green result card node |
| `resources/js/spa/components/canvas/nodes/EvaluationNode.jsx` | Amber evaluation card node |
| `resources/js/spa/components/canvas/LayerToggles.jsx` | Toolbar layer pill toggles |

### Modified Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/GraphController.php` | Extend `nodes()` and `edges()` for results/evaluations with `layers` param |
| `resources/js/spa/hooks/useGraphData.js` | Accept layers + expandedPrompts, map results/evaluations to Flow nodes/edges |
| `resources/js/spa/components/canvas/FlowCanvas.jsx` | Register new node types |
| `resources/js/spa/pages/CanvasPage.jsx` | Manage layer state + expanded state, render LayerToggles |
| `resources/js/spa/components/canvas/nodes/PromptNode.jsx` | Add clickable "N results ▸" badge |

---

## Task 1: Extend Graph API

**Files:**
- Modify: `app/Http/Controllers/Api/GraphController.php`

- [ ] **Step 1: Extend `nodes()` method**

Read the file first. In the `nodes()` method, add `layers` query param parsing at the top:

```php
$layers = $request->query('layers', 'prompts,fragments,collections');
$layerList = explode(',', $layers);
$includeResults = in_array('results', $layerList);
$includeEvaluations = in_array('evaluations', $layerList);
```

After the existing prompts and collections mapping, add results loading (before the final `return`):

```php
$resultsData = collect();
$evaluationsData = collect();

if ($includeResults) {
    $resultIds = [];
    foreach ($prompts as $prompt) {
        $resultIds = array_merge($resultIds, $prompt->results()->pluck('results.id')->toArray());
    }

    if (!empty($resultIds)) {
        $results = \App\Models\Result::whereIn('id', $resultIds)
            ->with(['promptVersion'])
            ->orderByDesc('created_at')
            ->get();

        // Load positions for result nodes
        $resultPositions = collect();
        if ($results->isNotEmpty()) {
            $resultPositions = \App\Models\GraphPosition::where('user_id', $user->id)
                ->where('node_type', 'result')
                ->whereIn('node_id', $results->pluck('id'))
                ->get()
                ->keyBy('node_id');
        }

        $resultsData = $results->map(function ($result) use ($resultPositions) {
            $position = $resultPositions->get($result->id);
            return [
                'id'               => $result->id,
                'prompt_id'        => $result->prompt_id,
                'provider_name'    => $result->provider_name,
                'model_name'       => $result->model_name,
                'duration_ms'      => $result->duration_ms,
                'rating'           => $result->rating,
                'evaluation_score' => $result->evaluation_score,
                'source'           => $result->source,
                'created_at'       => $result->created_at,
                'position'         => $position ? ['x' => $position->x, 'y' => $position->y] : null,
            ];
        })->values();
    }
}

if ($includeEvaluations && $includeResults && $resultsData->isNotEmpty()) {
    $evalResultIds = $resultsData->pluck('id')->toArray();

    $evaluations = \App\Models\ResultEvaluation::whereIn('result_id', $evalResultIds)
        ->whereRaw('evaluation_version = (SELECT MAX(re2.evaluation_version) FROM result_evaluations re2 WHERE re2.result_id = result_evaluations.result_id)')
        ->get()
        ->groupBy('result_id');

    // Load positions for evaluation nodes
    $evalPositions = collect();
    if ($evaluations->isNotEmpty()) {
        $evalPositions = \App\Models\GraphPosition::where('user_id', $user->id)
            ->where('node_type', 'evaluation')
            ->whereIn('node_id', $evaluations->keys())
            ->get()
            ->keyBy('node_id');
    }

    $evaluationsData = $evaluations->map(function ($scores, $resultId) use ($evalPositions) {
        $first = $scores->first();
        $position = $evalPositions->get($resultId);
        $totalWeight = $scores->sum('weight');
        $composite = $totalWeight > 0
            ? round($scores->sum(fn ($s) => $s->score * $s->weight) / $totalWeight, 2)
            : null;

        return [
            'result_id'          => $resultId,
            'evaluation_version' => $first->evaluation_version,
            'composite_score'    => $composite,
            'evaluator_provider' => $first->evaluator_provider,
            'scores'             => $scores->map(fn ($s) => [
                'dimension' => $s->dimension,
                'score'     => $s->score,
                'reasoning' => $s->reasoning,
            ])->values()->toArray(),
            'created_at'         => $first->created_at,
            'position'           => $position ? ['x' => $position->x, 'y' => $position->y] : null,
        ];
    })->values();
}
```

Update the return statement to include results and evaluations:

```php
return response()->json([
    'data' => [
        'prompts'     => $promptsData,
        'collections' => $collectionsData,
        'results'     => $resultsData,
        'evaluations' => $evaluationsData,
    ],
    'meta' => [
        'total_count' => $totalCount,
        'truncated'   => $truncated,
    ],
]);
```

- [ ] **Step 2: Extend `edges()` method**

In the `edges()` method, after the existing composition and collection edges, add result and evaluation edges:

```php
$resultEdges = [];
$evaluationEdges = [];

$layers = $request->query('layers', 'prompts,fragments,collections');
$layerList = explode(',', $layers);

if (in_array('results', $layerList)) {
    $promptIds = Prompt::visibleTo($user)->pluck('id');
    $results = \App\Models\Result::whereIn('prompt_id', $promptIds)->get(['id', 'prompt_id']);
    $resultEdges = $results->map(fn ($r) => [
        'prompt_id' => $r->prompt_id,
        'result_id' => $r->id,
    ])->values()->toArray();

    if (in_array('evaluations', $layerList)) {
        $resultIds = $results->pluck('id');
        $evals = \App\Models\ResultEvaluation::whereIn('result_id', $resultIds)
            ->selectRaw('result_id, MAX(evaluation_version) as evaluation_version')
            ->groupBy('result_id')
            ->get();
        $evaluationEdges = $evals->map(fn ($e) => [
            'result_id'          => $e->result_id,
            'evaluation_version' => $e->evaluation_version,
        ])->values()->toArray();
    }
}
```

Add to the return:

```php
return response()->json([
    'data' => [
        'composition'  => $compositionEdges,
        'collection'   => $collectionEdges,
        'result'       => $resultEdges,
        'evaluation'   => $evaluationEdges,
    ],
]);
```

- [ ] **Step 3: Run tests, commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Http/Controllers/Api/GraphController.php
git commit -m "feat: extend graph API with results and evaluations via layers param"
```

---

## Task 2: ResultNode Component

**Files:**
- Create: `resources/js/spa/components/canvas/nodes/ResultNode.jsx`

- [ ] **Step 1: Create ResultNode**

```jsx
import { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

function ResultNode({ data, selected }) {
    const score = data.evaluation_score;
    const scoreColor = score >= 4 ? 'text-green-400' : score >= 3 ? 'text-amber-400' : score ? 'text-red-400' : '';

    return (
        <div className={`bg-gray-800 rounded-lg shadow-lg min-w-[140px] max-w-[160px] ${selected ? 'ring-2 ring-green-400' : ''}`}
             style={{ borderLeft: '3px solid #22c55e' }}>
            <Handle type="target" position={Position.Top} className="!bg-green-500 !w-2 !h-2" />
            <div className="p-2.5">
                <div className="flex items-center justify-between gap-1 mb-1">
                    <span className="font-semibold text-xs text-gray-100 truncate">{data.provider_name || 'Unknown'}</span>
                    <span className="text-[8px] bg-green-900 text-green-300 px-1.5 py-0.5 rounded-full shrink-0">result</span>
                </div>
                {data.model_name && (
                    <div className="text-[9px] text-gray-500 mb-1 truncate">{data.model_name}</div>
                )}
                <div className="flex items-center gap-2 text-[9px] text-gray-500">
                    {data.duration_ms != null && <span>{(data.duration_ms / 1000).toFixed(1)}s</span>}
                    {data.rating && (
                        <span className="text-amber-400">{'★'.repeat(data.rating)}</span>
                    )}
                    {score != null && (
                        <span className={scoreColor}>⭐{Number(score).toFixed(1)}</span>
                    )}
                </div>
                <div className="text-[8px] text-gray-600 mt-1">{data.source}</div>
            </div>
            <Handle type="source" position={Position.Bottom} className="!bg-green-500 !w-2 !h-2" />
        </div>
    );
}

export default memo(ResultNode);
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/spa/components/canvas/nodes/ResultNode.jsx
git commit -m "feat: ResultNode canvas component — green card with provider, score, rating"
```

---

## Task 3: EvaluationNode Component

**Files:**
- Create: `resources/js/spa/components/canvas/nodes/EvaluationNode.jsx`

- [ ] **Step 1: Create EvaluationNode**

```jsx
import { memo, useState } from 'react';
import { Handle, Position } from '@xyflow/react';

function EvaluationNode({ data, selected }) {
    const [expanded, setExpanded] = useState(false);
    const score = data.composite_score;
    const scoreColor = score >= 4 ? 'text-green-400' : score >= 3 ? 'text-amber-400' : 'text-red-400';
    const bgColor = score >= 4 ? 'bg-green-900/30' : score >= 3 ? 'bg-amber-900/30' : 'bg-red-900/30';

    return (
        <div className={`bg-gray-800 rounded-lg shadow-lg min-w-[130px] max-w-[180px] ${selected ? 'ring-2 ring-amber-400' : ''}`}
             style={{ borderLeft: '3px solid #f97316' }}>
            <Handle type="target" position={Position.Top} className="!bg-amber-500 !w-2 !h-2" />
            <div className="p-2.5">
                <div className="flex items-center justify-between gap-1 mb-1">
                    <span className={`font-bold text-sm ${scoreColor}`}>
                        {score != null ? Number(score).toFixed(1) : '—'}
                    </span>
                    <span className="text-[8px] bg-amber-900 text-amber-300 px-1.5 py-0.5 rounded-full shrink-0">
                        eval v{data.evaluation_version}
                    </span>
                </div>
                <div className="text-[9px] text-gray-500 mb-1 truncate">{data.evaluator_provider}</div>

                {data.scores && data.scores.length > 0 && (
                    <button
                        onClick={() => setExpanded(!expanded)}
                        className="text-[8px] text-gray-500 hover:text-gray-300"
                    >
                        {expanded ? '▾ Hide' : '▸ Dimensions'}
                    </button>
                )}

                {expanded && data.scores && (
                    <div className="mt-1 space-y-0.5">
                        {data.scores.map(s => (
                            <div key={s.dimension} className="flex items-center justify-between text-[8px]">
                                <span className="text-gray-400 truncate">{s.dimension}</span>
                                <span className={`${bgColor} px-1 rounded ${scoreColor}`}>{s.score}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

export default memo(EvaluationNode);
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/spa/components/canvas/nodes/EvaluationNode.jsx
git commit -m "feat: EvaluationNode canvas component — amber card with scores, expandable dimensions"
```

---

## Task 4: LayerToggles Component

**Files:**
- Create: `resources/js/spa/components/canvas/LayerToggles.jsx`

- [ ] **Step 1: Create LayerToggles**

```jsx
const LAYERS = [
    { key: 'prompts', label: 'Prompts', color: '#6366f1', bgActive: '#312e81', always: true },
    { key: 'fragments', label: 'Fragments', color: '#3b82f6', bgActive: '#1e3a5f' },
    { key: 'collections', label: 'Collections', color: '#6b7280', bgActive: '#1f2937' },
    { key: 'results', label: 'Results', color: '#22c55e', bgActive: '#14532d' },
    { key: 'evaluations', label: 'Evaluations', color: '#f97316', bgActive: '#431407' },
];

export default function LayerToggles({ activeLayers, onToggle }) {
    return (
        <div className="flex items-center gap-1.5">
            <span className="text-[9px] text-gray-500 uppercase tracking-wider mr-1">Layers</span>
            {LAYERS.map(layer => {
                const isActive = activeLayers.includes(layer.key);
                return (
                    <button
                        key={layer.key}
                        onClick={() => !layer.always && onToggle(layer.key)}
                        className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] transition-all ${
                            layer.always ? 'cursor-default' : 'cursor-pointer'
                        }`}
                        style={{
                            background: isActive ? layer.bgActive : '#1f2937',
                            border: `1px solid ${isActive ? layer.color : '#374151'}`,
                            opacity: isActive ? 1 : 0.4,
                        }}
                    >
                        <div style={{
                            width: 6, height: 6,
                            borderRadius: '50%',
                            background: isActive ? layer.color : '#475569',
                        }} />
                        <span style={{ color: isActive ? layer.color : '#6b7280' }}>
                            {layer.label}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/spa/components/canvas/LayerToggles.jsx
git commit -m "feat: LayerToggles component — pill-shaped layer visibility controls"
```

---

## Task 5: Update useGraphData Hook

**Files:**
- Modify: `resources/js/spa/hooks/useGraphData.js`

- [ ] **Step 1: Extend the hook**

Read the file first. The hook currently fetches `/graph/nodes` and `/graph/edges`, transforms them into React Flow format.

Changes needed:

1. Accept `layers` array and `expandedPrompts` Set as parameters
2. Pass `?layers=...` to both API calls
3. Map results to Flow nodes: `{id: "result-{id}", type: "result", position, data}`
4. Map evaluations to Flow nodes: `{id: "evaluation-{resultId}-v{version}", type: "evaluation", position, data}`
5. Filter: only include result nodes for prompts in `expandedPrompts`
6. Filter: only include evaluation nodes when parent result is visible
7. Generate result edges: `{id: "edge-result-{promptId}-{resultId}", source: "prompt-{promptId}", target: "result-{resultId}", type: "smoothstep", style: {stroke: "#22c55e"}}`
8. Generate evaluation edges: `{id: "edge-eval-{resultId}-v{version}", source: "result-{resultId}", target: "evaluation-{resultId}-v{version}", type: "smoothstep", style: {stroke: "#f97316"}, animated: true}`
9. Auto-layout for nodes without saved positions: results 120px below parent prompt, spaced 140px horizontally. Evaluations 90px below their result.

For auto-layout positioning, find the parent prompt's position and offset:

```javascript
// For results: find parent prompt position
const parentPrompt = promptNodes.find(n => n.id === `prompt-${result.prompt_id}`) 
    || promptNodes.find(n => n.id === `fragment-${result.prompt_id}`);
const parentPos = parentPrompt?.position || { x: 0, y: 0 };
const resultIndex = resultsForThisPrompt.indexOf(result);
const x = parentPos.x + (resultIndex - (resultsForThisPrompt.length - 1) / 2) * 140;
const y = parentPos.y + 120;
```

The query key should include layers and expandedPrompts so React Query refetches when they change.

- [ ] **Step 2: Commit**

```bash
git add resources/js/spa/hooks/useGraphData.js
git commit -m "feat: extend useGraphData for result/evaluation nodes with layer filtering"
```

---

## Task 6: Update FlowCanvas + CanvasPage

**Files:**
- Modify: `resources/js/spa/components/canvas/FlowCanvas.jsx`
- Modify: `resources/js/spa/pages/CanvasPage.jsx`
- Modify: `resources/js/spa/components/canvas/nodes/PromptNode.jsx`

- [ ] **Step 1: Register new node types in FlowCanvas**

In `FlowCanvas.jsx`, add imports and register:

```jsx
import ResultNode from './nodes/ResultNode.jsx';
import EvaluationNode from './nodes/EvaluationNode.jsx';

const nodeTypes = {
    prompt: PromptNode,
    fragment: FragmentNode,
    collection: CollectionNode,
    result: ResultNode,
    evaluation: EvaluationNode,
};
```

- [ ] **Step 2: Add layer state + toggle to CanvasPage**

In `CanvasPage.jsx`, add state and pass to useGraphData:

```jsx
import { useState, useCallback } from 'react';
import LayerToggles from '../components/canvas/LayerToggles.jsx';

// Inside component:
const [activeLayers, setActiveLayers] = useState(['prompts', 'fragments']);
const [expandedPrompts, setExpandedPrompts] = useState(new Set());

const handleToggleLayer = useCallback((layer) => {
    setActiveLayers(prev =>
        prev.includes(layer)
            ? prev.filter(l => l !== layer)
            : [...prev, layer]
    );
}, []);

const handleTogglePromptResults = useCallback((promptId) => {
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
```

Pass `activeLayers` and `expandedPrompts` to `useGraphData`:

```jsx
const { nodes, edges, isLoading } = useGraphData(activeLayers, expandedPrompts);
```

Render `LayerToggles` in the toolbar area (find where the existing toolbar/controls are rendered):

```jsx
<LayerToggles activeLayers={activeLayers} onToggle={handleToggleLayer} />
```

Pass `onToggleResults` to FlowCanvas so it reaches PromptNode:

```jsx
<FlowCanvas
    nodes={nodes}
    edges={edges}
    onTogglePromptResults={handleTogglePromptResults}
    expandedPrompts={expandedPrompts}
/>
```

In FlowCanvas, pass the callback to nodes via `nodeData` or by wrapping nodes with the callback.

- [ ] **Step 3: Add "N results ▸" badge to PromptNode**

In `PromptNode.jsx`, add a clickable results badge. The PromptNode receives `data.results_count`, `data.id`, and a way to trigger expand. Add after the existing info row:

```jsx
{data.results_count > 0 && (
    <button
        onClick={(e) => {
            e.stopPropagation();
            data.onToggleResults?.(data.id);
        }}
        className="text-[9px] text-green-400 hover:text-green-300 mt-1 cursor-pointer"
    >
        {data.results_count} results {data.isExpanded ? '▾' : '▸'}
    </button>
)}
```

The `data.onToggleResults` callback and `data.isExpanded` flag are passed from `useGraphData` when building prompt node data.

- [ ] **Step 4: Build and commit**

```bash
npm run build
git add resources/js/spa/components/canvas/FlowCanvas.jsx resources/js/spa/pages/CanvasPage.jsx resources/js/spa/components/canvas/nodes/PromptNode.jsx public/build/
git commit -m "feat: canvas storyteller — layer toggles, expand/collapse results, new node types"
```

---

## Task 7: Integration + Verify

- [ ] **Step 1: Build**

```bash
npm run build
```

- [ ] **Step 2: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 376+ tests pass.

- [ ] **Step 3: Manual E2E test**

1. Canvas loads normally (prompts, fragments)
2. Layer toggles visible in toolbar
3. Toggle Collections OFF → collection nodes disappear
4. Toggle Results ON → prompt nodes show "N results ▸" badges
5. Click "3 results ▸" on micro-casa → 3 result nodes expand below with green edges
6. Click again → results collapse
7. Toggle Evaluations ON → evaluation nodes appear below evaluated results
8. Toggle Results OFF → results AND evaluations disappear
9. Drag a result node → refresh → position persists
10. API: `GET /graph/nodes?layers=prompts,results` → includes results array
11. API: `GET /graph/nodes` (no param) → no results (backward compat)

- [ ] **Step 4: Commit and push**

```bash
git add -A
git commit -m "feat: canvas storyteller complete — results and evaluations as connected nodes"
git push
```

---

## Verification Summary

| Feature | How to verify |
|---------|--------------|
| Layer toggles | Toolbar shows 5 layer pills, click toggles visibility |
| Prompts always on | Prompts layer cannot be toggled off |
| Results expand | Click "N results ▸" on prompt → result nodes appear below |
| Results collapse | Click again → result nodes disappear |
| Evaluations | Toggle ON → amber evaluation nodes below evaluated results |
| Evaluation dependency | Evaluations hidden when Results layer is OFF |
| ResultNode | Green card with provider, model, duration, score |
| EvaluationNode | Amber card with composite score, expandable dimensions |
| Edges | Green solid for prompt→result, amber for result→evaluation |
| Position saving | Drag result/eval nodes → positions persist |
| API backward compat | No `layers` param → current behavior (no results) |
| Auto-layout | New result nodes positioned below prompt, spaced horizontally |
| PHP tests | 376+ pass |
