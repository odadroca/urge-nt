# Result Evaluation Design

## Context

URGE stores LLM results but has no structured way to assess their quality against the original prompt. Users can star-rate results (1-5), but there's no automated evaluation. This design adds LLM-powered evaluation as a first-class feature — treating evaluation as a pipeline run, with configurable dimensions, versioned evaluation prompts, and human-in-the-loop scoring unified in one model.

## Decision

Evaluation is a pipeline template run. The evaluation prompt is a regular URGE prompt (versioned, branchable). Dimensions are configurable in settings. Human star-ratings are a sixth dimension in the same scoring table. Evaluation scores are exposed via REST API, MCP tools, and the canvas.

## 1. Data Model

### `result_evaluations` table

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `result_id` | FK → results | cascadeOnDelete |
| `evaluation_version` | integer, default 1 | increments per result on re-evaluation |
| `pipeline_run_id` | string(36), nullable | links to the pipeline run that produced this |
| `evaluation_prompt_version_id` | FK → prompt_versions, nullable | which version of the evaluation prompt was used |
| `evaluator_provider` | string | provider name (or `human`) |
| `evaluator_model` | string | model name (or `human`) |
| `dimension` | string(50) | e.g. `relevance`, `completeness`, `human` |
| `score` | tinyInteger | 1-5 |
| `reasoning` | text, nullable | LLM's explanation for the score |
| `weight` | decimal(3,2), default 1.00 | weight for composite calculation |
| `created_by` | FK → users | |
| `timestamps` | | |

**Unique index:** `(result_id, evaluation_version, dimension)` — one score per dimension per evaluation version.

### `evaluation_settings` table

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `key` | string, unique | setting key |
| `value` | JSON | setting value |
| `timestamps` | | |

Stores instance-wide evaluation configuration (not per-user).

### Human dimension

When a user star-rates a result (existing `rating` field on Result), a `result_evaluation` row is created/updated:
- `dimension` = `human`
- `score` = the star rating (1-5)
- `evaluator_provider` = `human`
- `evaluator_model` = `human`
- `reasoning` = null
- `evaluation_version` = latest version for that result (or 1 if no LLM evaluation exists)

This unifies human and LLM scoring in one table for composite calculation.

### Composite score

Calculated on read — weighted average of all dimensions for the latest evaluation version of a result. Not stored (avoids staleness when weights change). Formula:

```
composite = sum(score * weight) / sum(weight)
```

If human dimension exists, it's included with its configured weight.

## 2. Evaluation Prompt & Pipeline

### Evaluation prompt

A regular URGE prompt (type `fragment`, slug `system/evaluation-template`). Variables:

- `{{original_prompt}}` — the rendered prompt content sent to the LLM
- `{{response_text}}` — the LLM's response being evaluated
- `{{dimensions}}` — JSON list of active dimensions with descriptions
- `{{scale}}` — rating scale description ("1 = poor, 5 = excellent")

The prompt instructs the evaluator to return structured JSON:

```json
{
  "scores": [
    {"dimension": "relevance", "score": 4, "reasoning": "Addresses the core request but..."},
    {"dimension": "completeness", "score": 3, "reasoning": "Two of five requirements..."}
  ]
}
```

### Evaluation pipeline template

A regular pipeline template linked to the evaluation prompt. Its channels define which LLMs evaluate. Multiple channels = multiple LLMs evaluate independently. Synthesis step can average or reconcile scores.

For single-provider mode (no pipeline), evaluation dispatches directly via `LlmDispatchService::dispatchWithSystem()` using the configured default evaluator provider.

### Bootstrap

Artisan command `php artisan urge:seed-evaluation` creates:
1. The default evaluation prompt (`system/evaluation-template`) with first version
2. A default evaluation pipeline template with one channel

Users can then version/branch the prompt and reconfigure the pipeline like any other.

## 3. Settings

### Configuration keys (stored in `evaluation_settings` table)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | boolean | false | Master toggle |
| `auto_evaluate` | boolean | false | Auto-run after `run_prompt` |
| `prompt_slug` | string | `system/evaluation-template` | Which URGE prompt to use |
| `pipeline_slug` | string, nullable | null | Which pipeline template (null = single-provider) |
| `default_provider_id` | integer, nullable | null | Default evaluator LLM provider |
| `dimensions` | JSON | (see below) | Active dimensions with weights and descriptions |

### Default dimensions

```json
[
  {"name": "relevance", "description": "Does the response address what the prompt asked for?", "weight": 1.0, "enabled": true, "builtin": true},
  {"name": "completeness", "description": "Are all parts of the prompt addressed? Nothing missing?", "weight": 1.0, "enabled": true, "builtin": true},
  {"name": "accuracy", "description": "Is the information correct and well-reasoned?", "weight": 1.0, "enabled": true, "builtin": true},
  {"name": "clarity", "description": "Is the response well-structured and easy to follow?", "weight": 1.0, "enabled": true, "builtin": true},
  {"name": "conciseness", "description": "Right amount of detail — not too verbose, not too sparse?", "weight": 1.0, "enabled": true, "builtin": true},
  {"name": "human", "description": "Human star-rating of the result.", "weight": 1.0, "enabled": true, "builtin": true}
]
```

Custom dimensions can be added (with `builtin: false`). Built-in dimensions can be disabled but not deleted.

### Settings UI

New "Evaluation" tab in Settings (visible to all users):

- **Toggle section:** Enable evaluation, Auto-evaluate after run
- **Source section:** Evaluation prompt dropdown (link to edit in workspace), Pipeline template dropdown (optional), Default evaluator provider dropdown
- **Dimensions section:** Table with checkbox, name, description, weight, delete (custom only). "+ Add custom dimension" button.

## 4. REST API Endpoints

Under `/api/v1/`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/results/{id}/evaluate` | Trigger evaluation (runs evaluation pipeline) |
| `GET` | `/results/{id}/evaluations` | List all evaluation versions |
| `GET` | `/results/{id}/evaluations/latest` | Latest version with scores + composite |
| `GET` | `/results/{id}/evaluations/{version}` | Specific evaluation version |

### POST `/results/{id}/evaluate`

Request body (all optional):
```json
{
  "provider": "Mistral"
}
```

Response (201):
```json
{
  "evaluation_version": 2,
  "composite_score": 3.8,
  "scores": [
    {"dimension": "relevance", "score": 4, "weight": 1.0, "reasoning": "..."},
    {"dimension": "completeness", "score": 3, "weight": 1.0, "reasoning": "..."},
    {"dimension": "accuracy", "score": 4, "weight": 1.0, "reasoning": "..."},
    {"dimension": "clarity", "score": 4, "weight": 1.0, "reasoning": "..."},
    {"dimension": "conciseness", "score": 4, "weight": 1.0, "reasoning": "..."}
  ],
  "evaluator_provider": "Mistral",
  "evaluator_model": "mistral-small-latest",
  "evaluation_prompt_version_id": 42
}
```

### GET `/results/{id}/evaluations`

Response:
```json
{
  "data": [
    {"evaluation_version": 1, "composite_score": 3.2, "scores_count": 5, "created_at": "..."},
    {"evaluation_version": 2, "composite_score": 3.8, "scores_count": 5, "created_at": "..."}
  ]
}
```

### GET `/results/{id}/evaluations/latest`

Response: same structure as POST evaluate response, including human dimension if rated.

## 5. MCP Tools

| Tool | Description | Scope |
|------|-------------|-------|
| `evaluate_result` | Trigger evaluation for a result. Returns scores + composite. | `mcp:write` |
| `get_evaluations` | Get evaluation scores for a result (latest or specific version). | `mcp:read` |

### `evaluate_result`

```json
{
  "result_id": 123,
  "provider": "Mistral"
}
```

Required: `result_id`. Optional: `provider` (overrides default evaluator).

### `get_evaluations`

```json
{
  "result_id": 123,
  "version": 2
}
```

Required: `result_id`. Optional: `version` (defaults to latest).

### End-to-end MCP flow

1. `create_prompt` → create prompt
2. `save_version` → add content
3. `list_providers` → see available LLMs
4. `run_prompt` → run through provider A → result stored (auto-evaluate if enabled)
5. `run_prompt` → same prompt through provider B → second result
6. `evaluate_result` → evaluate each result against the original prompt
7. `get_evaluations` → compare scores across providers
8. User star-rates preferred result → human dimension added to composite

### Auto-evaluation

When `auto_evaluate` is enabled, `run_prompt` automatically triggers evaluation after storing the result. The `run_prompt` MCP response includes evaluation scores alongside the LLM response:

```json
{
  "response_text": "...",
  "provider": "Mistral",
  "result_id": 123,
  "evaluation": {
    "composite_score": 3.8,
    "scores": [...]
  }
}
```

## 6. Canvas Integration

### Prompt node enhancement

The graph nodes API (`GET /api/v1/graph/nodes`) returns evaluation summary per prompt:

```json
{
  "id": 1,
  "type": "prompt",
  "name": "micro-casa",
  "avg_evaluation_score": 3.8,
  "evaluated_results_count": 3,
  "total_results_count": 5
}
```

### PromptNode component

Renders a score badge on the prompt node when evaluation data is present. Color-coded:
- Green (≥4.0) — strong results
- Amber (≥3.0) — acceptable
- Red (<3.0) — needs improvement

Clicking the badge navigates to the workspace results panel with evaluation details.

### Workspace ResultsPanel

Each result card shows:
- Existing star rating (human)
- LLM evaluation composite score (badge)
- Expandable dimension breakdown (click to see per-dimension scores + reasoning)

## 7. Files

### New files

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_create_result_evaluations_table.php` | `result_evaluations` + `evaluation_settings` tables |
| `app/Models/ResultEvaluation.php` | Evaluation model |
| `app/Models/EvaluationSetting.php` | Settings model |
| `app/Services/EvaluationService.php` | Orchestrates evaluation: render eval prompt, dispatch, parse scores, store |
| `app/Http/Controllers/Api/EvaluationController.php` | REST API endpoints |
| `app/Livewire/Settings/Evaluation.php` | Settings tab Livewire component |
| `resources/views/livewire/settings/evaluation.blade.php` | Settings tab view |
| `app/Console/Commands/SeedEvaluationCommand.php` | `urge:seed-evaluation` — creates default prompt + pipeline |
| `tests/Feature/EvaluationTest.php` | API + service tests |

### Modified files

| File | Change |
|------|--------|
| `app/Services/McpToolHandler.php` | Add `evaluate_result`, `get_evaluations` tools (definitions, scope, dispatch, implementation) |
| `app/Http/Controllers/Api/GraphController.php` | Include evaluation summary in graph nodes |
| `app/Livewire/Settings.php` | Add 'evaluation' tab |
| `resources/js/spa/components/canvas/PromptNode.jsx` | Score badge |
| `resources/js/spa/components/workspace/ResultsPanel.jsx` | Evaluation scores on result cards |
| `routes/api.php` | Evaluation endpoints |
| `config/urge.php` | Default evaluation config |

## 8. Verification

1. `php artisan urge:seed-evaluation` → creates default evaluation prompt + pipeline
2. Settings → Evaluation tab → enable, configure dimensions, select provider
3. `POST /results/{id}/evaluate` → returns scores
4. `GET /results/{id}/evaluations/latest` → returns same scores
5. Re-evaluate → new evaluation version created
6. Star-rate a result → human dimension appears in evaluations
7. MCP `evaluate_result` → returns scores
8. MCP `get_evaluations` → returns scores
9. MCP `run_prompt` with auto-evaluate → response includes evaluation
10. Canvas → prompt nodes show score badges
11. Workspace → result cards show evaluation breakdown
12. `php artisan test` → all tests pass
