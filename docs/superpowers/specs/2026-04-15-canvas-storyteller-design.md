# Canvas Storyteller Design

## Context

The canvas currently shows only prompts, fragments, and collections — a static prompt map. Results and evaluations are hidden inside the workspace. This design transforms the canvas into a **storyteller** — a full data graph where prompts connect to results connect to evaluations, making the entire workflow visible as a story/timeline.

## Decision

Add result and evaluation nodes to the canvas with a layer toggle system. Results are collapsed by default on prompt nodes (click to expand). Each node type is a toggleable layer. The graph API is extended with a `layers` query param so only requested data is loaded.

## 1. New Node Types

### ResultNode

Compact green card showing:
- Provider name (bold) + model name
- Duration (e.g. "1.2s")
- Evaluation composite score badge (color-coded green ≥4, amber ≥3, red <3)
- Star rating (if human-rated)
- Source handle (top) — connects to parent prompt
- Target handle (bottom) — for evaluation edge

### EvaluationNode

Compact amber card showing:
- Composite score (large, color-coded)
- Evaluator name (e.g. "Claude Opus 4.6")
- Evaluation version badge
- Per-dimension scores visible (compact list)
- Source handle (top) — connects to parent result

### Node ID Format

Follows existing `{type}-{id}` convention:
- `result-{id}` — e.g. `result-42`
- `evaluation-{resultId}-v{version}` — e.g. `evaluation-42-v1`

### Edge Types

| Type | Source → Target | Style |
|------|----------------|-------|
| `composition` | prompt → fragment | Existing (gray, bezier) |
| `result` | prompt → result | Green, solid |
| `evaluation` | result → evaluation | Amber, dashed |

## 2. Layer Toggle System

### Toolbar

Canvas toolbar gets a "Layers" section with pill-shaped toggles. Each layer shows/hides its node type and associated edges.

| Layer | Color | Default | Notes |
|-------|-------|---------|-------|
| Prompts | Indigo | On | Always on, cannot be toggled off |
| Fragments | Blue | On | Fragment nodes + include edges |
| Collections | Gray | Off | Collection nodes |
| Results | Green | Off | Result nodes + prompt→result edges |
| Evaluations | Amber | Off | Evaluation nodes + result→evaluation edges |

### State

Layer toggles stored in React local state (not persisted — view preference). Toggling instantly shows/hides nodes and edges.

### Collapse/Expand

When Results layer is ON:
- Each prompt node shows "N results ▸" as a clickable badge
- Click expands: result nodes appear below the prompt, connected by edges
- Click again collapses: result nodes hidden
- Per-prompt — expand one prompt's results without affecting others

### Dependency

Evaluations only visible when BOTH Results AND Evaluations layers are on. Evaluations attach to results — hiding results hides evaluations too.

## 3. Graph API Extension

### `GET /api/v1/graph/nodes`

Add `layers` query param:

```
GET /graph/nodes?layers=prompts,fragments,results,evaluations
```

Default (no param): `prompts,fragments,collections` — current behavior, backward compatible.

Response extended:

```json
{
  "data": {
    "prompts": [...],
    "collections": [...],
    "results": [
      {
        "id": 42,
        "prompt_id": 1,
        "provider_name": "Mistral",
        "model_name": "mistral-small-latest",
        "duration_ms": 1200,
        "rating": 4,
        "evaluation_score": 4.2,
        "source": "mcp",
        "created_at": "...",
        "position": {"x": 100, "y": 200}
      }
    ],
    "evaluations": [
      {
        "result_id": 42,
        "evaluation_version": 1,
        "composite_score": 4.2,
        "evaluator_provider": "Claude Opus 4.6",
        "scores": [
          {"dimension": "relevance", "score": 4, "reasoning": "..."},
          {"dimension": "clarity", "score": 5, "reasoning": "..."}
        ],
        "created_at": "...",
        "position": {"x": 100, "y": 280}
      }
    ]
  }
}
```

Results and evaluations only loaded when their layers are requested.

### `GET /api/v1/graph/edges`

Extended to include result and evaluation edges:

```json
{
  "composition": [...],
  "collection": [...],
  "result": [
    {"prompt_id": 1, "result_id": 42}
  ],
  "evaluation": [
    {"result_id": 42, "evaluation_version": 1}
  ]
}
```

## 4. React Components

### New Files

| File | Purpose |
|------|---------|
| `resources/js/spa/components/canvas/nodes/ResultNode.jsx` | Green result card |
| `resources/js/spa/components/canvas/nodes/EvaluationNode.jsx` | Amber evaluation card |
| `resources/js/spa/components/canvas/LayerToggles.jsx` | Toolbar pill toggles |

### Modified Files

| File | Change |
|------|--------|
| `resources/js/spa/hooks/useGraphData.js` | Accept layers param, map results/evaluations to Flow nodes, generate result/evaluation edges |
| `resources/js/spa/components/canvas/FlowCanvas.jsx` | Register new node/edge types, receive layer state |
| `resources/js/spa/pages/CanvasPage.jsx` | Manage layer toggle state + expanded prompts state, pass to hooks and toolbar |
| `resources/js/spa/components/canvas/nodes/PromptNode.jsx` | Add clickable "N results ▸" badge with expand/collapse callback |
| `app/Http/Controllers/Api/GraphController.php` | Extend nodes() and edges() to return results/evaluations based on layers param |

### useGraphData Changes

- Accept `layers` array and `expandedPrompts` Set as params
- Pass `?layers=...` to API
- Map results to `{id: "result-42", type: "result", data, position}`
- Map evaluations to `{id: "evaluation-42-v1", type: "evaluation", data, position}`
- Filter: only include result nodes for expanded prompts
- Filter: only include evaluation nodes when evaluations layer is on AND parent result is visible
- Generate edges: `prompt-{id} → result-{id}` (type `result`), `result-{id} → evaluation-{resultId}-v{version}` (type `evaluation`)
- Auto-layout for nodes without saved positions: results 100px below parent prompt, spaced 120px horizontally. Evaluations 80px below their result.

### Collapse/Expand State

- `CanvasPage` holds `expandedPrompts` state (Set of prompt IDs)
- `PromptNode` gets `onToggleResults` callback — clicking badge toggles the prompt ID in the Set
- When prompt is NOT in Set, its result nodes are filtered out before passing to FlowCanvas
- Toggling triggers a React Query refetch (results may not be loaded yet)

### Position Saving

Existing `GraphPosition` model already supports arbitrary `node_type` strings. Result and evaluation node positions saved as:
- `node_type: "result"`, `node_id: 42`
- `node_type: "evaluation"`, `node_id: 42` (using result_id, since evaluation version may change)

## 5. Verification

1. Canvas loads with current behavior (prompts, fragments, collections)
2. Layer toggles appear in toolbar
3. Toggle Results ON → prompt nodes show "N results ▸" badges
4. Click badge → result nodes expand below prompt with green edges
5. Click again → results collapse back to badge
6. Toggle Evaluations ON → evaluation nodes appear below evaluated results with amber dashed edges
7. Toggle Results OFF → result AND evaluation nodes disappear
8. Toggle Collections OFF → collection nodes disappear
9. Drag result/evaluation nodes → positions saved and restored on reload
10. API: `GET /graph/nodes?layers=prompts,results` returns results data
11. API: `GET /graph/nodes` (no param) → current behavior, no results
12. `php artisan test` → all 376+ tests pass
13. `npm run build` → clean build
