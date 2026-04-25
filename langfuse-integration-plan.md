# Langfuse Integration for URGE

> Companion to `claude-plugin-import-notes.md`, `contracts-layer-notes.md`,
> `orchestrator-comparison-notes.md`, and `narrated-observability-notes.md`.
> The five together sketch URGE's transition from prompt registry to
> governance plane for prompt-centric LLM systems (registry + runtime +
> observability + content imports + policy), its positioning relative to
> established agentic orchestrators, and the narrated-vs-measured
> observability framing for mode-2-pure-form runs.

## Context

URGE is a prompt registry + runtime; Langfuse is an observability platform that
executes nothing but ingests traces of everything. The two compose naturally:
URGE stores/composes/runs prompts, Langfuse captures the resulting traces and
scores. Today there is **no integration surface** in URGE — no event system, no
`/app/Events/`, no `/app/Jobs/`, no webhook code, and no mention of
observability in `CLAUDE.md`. This plan adds a first-class, bi-directional
Langfuse bridge:

1. **Outbound traces** — every `Result` created in URGE is emitted to Langfuse as a trace + generation, non-blocking, via a queued listener.
2. **Prompt sync** — every new `PromptVersion` is mirrored into Langfuse's prompt registry with correct labels so traces auto-link in Langfuse's UI.
3. **Score import** — Langfuse scores (LLM-judge + human annotations) are pulled back into URGE as `ResultEvaluation` rows so both products see the same signal.

The guiding principle: **URGE is the source of truth for prompts and runs; Langfuse is the source of truth for observability and scores. Neither system owns data the other should.**

---

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                          URGE                                │
│                                                              │
│  5 Result-creation chokepoints ─┐                            │
│                                 v                            │
│              Model observer fires ResultCreated event        │
│                                 │                            │
│                                 v                            │
│          EmitLangfuseTrace (ShouldQueue listener)            │
│                                 │                            │
│   VersioningService::createVersion ─┐                        │
│                                     v                        │
│          PromptVersionCreated event                          │
│                                     │                        │
│                                     v                        │
│        SyncPromptVersionToLangfuse (ShouldQueue)             │
│                                                              │
│   urge:sync-langfuse-scores (scheduled command, hourly)      │
└─────────────────┬─────────────────────────────┬──────────────┘
                  │ POST /api/public/ingestion  │ GET /api/public/scores
                  │ POST /api/public/prompts    │
                  v                             ^
                        ┌─────────────┐
                        │  Langfuse   │
                        └─────────────┘
```

All three flows share one `LangfuseClient` service that wraps `Http::` with Basic Auth (Langfuse uses Public+Secret key as HTTP Basic).

---

## Files to create

### Data model
- `database/migrations/YYYY_MM_DD_create_integrations_table.php` — columns: `id`, `kind` (unique, e.g. `langfuse`), `enabled` (bool), `config` (encrypted JSON: `base_url`, `public_key`, `secret_key`, `project_id`, `sync_prompts`, `sync_scores`), `last_trace_at`, `last_prompt_sync_at`, `last_score_sync_at`, `last_score_cursor` (timestamp, for incremental pulls), `last_error` (nullable text), timestamps.
- `database/migrations/YYYY_MM_DD_add_langfuse_trace_id_to_results.php` — nullable `langfuse_trace_id` (string). Optional but useful for debugging and idempotency.
- `database/migrations/YYYY_MM_DD_extend_result_source_enum.php` — add `synthesis` to the allowed values for `Result.source`. Currently `api|manual|import|mcp` (see `2026_01_01_000006_create_results_table.php:15` and `ResultController` validation `'in:api,manual,import,mcp'`). Update both the migration comment/column and the validation rule. Rationale: today `PipelineService::run()` tags both parallel and synthesis channel Results as `source='api'` (lines 56, 99), making them indistinguishable. A dedicated `synthesis` value lets `LangfuseMapper` model synthesis generations as parent observations of their parallel siblings (per `role_label` + `pipeline_run_id` grouping) and surfaces the distinction in URGE's own Results UI.
- `app/Models/Integration.php` — model with `encrypted:array` cast on `config` (Laravel native field-level encryption).

### Events + listeners (new infrastructure)
- `app/Events/ResultCreated.php` — payload: `Result $result`.
- `app/Events/PromptVersionCreated.php` — payload: `PromptVersion $version`.
- `app/Events/PromptPinnedVersionChanged.php` — payload: `Prompt $prompt`, `?PromptVersion $pinned`.
- `app/Listeners/EmitLangfuseTrace.php` — `implements ShouldQueue`. Reads `Integration::where('kind','langfuse')->where('enabled',true)->first()`; short-circuits if absent. Builds Langfuse ingestion envelope, calls `LangfuseClient::ingestBatch()`.
- `app/Listeners/SyncPromptVersionToLangfuse.php` — `implements ShouldQueue`. Converts `{{>slug}}` → `@@@langfusePrompt:name={owner}/{slug}|label=production@@@` so composability survives the bridge. Calls `LangfuseClient::createPrompt()`.
- `app/Listeners/UpdateLangfuseProductionLabel.php` — on `PromptPinnedVersionChanged`, moves Langfuse `production` label to the new pinned version.
- `app/Observers/ResultObserver.php` — `created()` hook dispatches `ResultCreated`. Registered in `app/Providers/AppServiceProvider.php`.
- `app/Providers/EventServiceProvider.php` — wire the three events to their listeners. (File exists in Laravel, currently minimal — extend it.)

### Services
- `app/Services/Integrations/LangfuseClient.php` — public methods:
  - `ingestBatch(array $events): void` — POST `/api/public/ingestion` with `{batch: [...]}`, events have stable IDs so Langfuse dedupes on retry.
  - `createOrUpdatePrompt(string $name, array $payload): void` — POST `/api/public/prompts`.
  - `updatePromptLabels(string $name, int $version, array $labels): void`.
  - `fetchScoresSince(\DateTimeInterface $since, ?string $cursor = null): iterable` — paginated GET `/api/public/scores`.
  - `testConnection(): array` — GET `/api/public/projects`, returns `{ok, project_name, error}`.
  - All calls use `Http::withBasicAuth($publicKey, $secretKey)->withOptions(['verify' => config('urge.curl_ssl_verify', true)])->timeout(10)`.
- `app/Services/Integrations/LangfuseMapper.php` — pure mapper:
  - `Result → [trace-create, generation-create]`. For pipeline results, trace_id = `urge-pipeline-{pipeline_run_id}` (shared across all results from the run; first emission creates the trace, subsequent ones reuse); for standalone, trace_id = `urge-result-{result.id}`. Generation id = `urge-generation-{result.id}`.
  - **Synthesis-of-parallels hierarchy**: when `Result.source='synthesis'`, set the generation's `parent_observation_id` to the synthesis trace's root and mark the parallel generations (from the same `pipeline_run_id`) as its inputs via Langfuse's `input` field (structured: `{type: 'parallel_outputs', sources: [{role_label, generation_id}, ...]}`). This preserves the "these N produced this 1" relationship in Langfuse's trace view rather than rendering N+1 sibling generations. Lookup of sibling generation IDs is deterministic because they share `pipeline_run_id` and URGE assigns their Langfuse IDs as `urge-generation-{result.id}`.
  - Maps `rendered_content`→input, `response_text`→output, `input_tokens`/`output_tokens`→usage, `duration_ms`→latency (start/end timestamps), `provider_name`/`model_name`→model, `status='error'`→level=ERROR, `error_message`→statusMessage.
  - Metadata: `variables_used`, URGE prompt slug, branch name, version number, URL back to URGE workspace (`{base_url}/app/workspace/{owner}/{slug}`).
  - Tags: `urge`, `urge:source:{source}` (so `urge:source:synthesis` visibly distinguishes synthesis generations in Langfuse filters), `urge:branch:{branch_name}`, `urge:pipeline:{slug}` if applicable, `urge:role:{role_label}` for pipeline-origin generations.
  - `promptName` = `{owner}/{slug}`, `promptVersion` = `version_number` (lets Langfuse auto-link to its mirrored prompt).

### Scheduled command
- `app/Console/Commands/SyncLangfuseScoresCommand.php` — `urge:sync-langfuse-scores`. Pulls scores since `Integration.last_score_cursor`, for each score:
  - Look up URGE `Result` by Langfuse `traceId`/`observationId` (either `urge-result-{id}` or `urge-generation-{id}` — decode).
  - Upsert `ResultEvaluation` with `evaluator_provider='langfuse'`, `evaluator_model=score.source`, `dimension=score.name`, `score`=numeric value (categorical → skip or map), `reasoning=score.comment`, `evaluation_version`=next available. Unique on `(result_id, evaluation_version, dimension)`.
  - Advance cursor.
- Register in `app/Console/Kernel.php` (or `routes/console.php` in Laravel 12): `$schedule->command('urge:sync-langfuse-scores')->hourly()->withoutOverlapping();`

### API + controller
- `app/Http/Controllers/Api/IntegrationController.php` — `show('langfuse')`, `update('langfuse', Request)`, `test('langfuse')`. Admin-gated via existing `role:admin` middleware.
- `routes/api.php` — add under `/api/v1/integrations/...`.
- `resources/js/spa/api/integrations.js` — client wrappers mirroring `resources/js/spa/api/providers.js`.

### UI
- `resources/js/spa/components/settings/IntegrationsTab.jsx` — mirrors `LlmProvidersTab.jsx` shape. Single card for Langfuse with: enabled toggle, base URL (default `https://cloud.langfuse.com`), public key, secret key (masked), project ID (optional), "sync prompts" toggle, "sync scores" toggle, Test Connection button, status strip showing last-trace / last-prompt-sync / last-score-sync / last-error.
- `resources/js/spa/pages/SettingsPage.jsx` — register the new tab in `TAB_COMPONENTS` and `TABS` (admin-only, key `integrations`, label `Integrations`).

### Config
- `config/urge.php` — add `'integrations' => ['langfuse' => ['default_base_url' => 'https://cloud.langfuse.com', 'batch_size' => 50, 'timeout_seconds' => 10]]`.

### Docs
- `documentation/integrations/langfuse.md` — set-up, what gets emitted, how pipelines map to parent trace + child generations, limitations (categorical scores skipped, `{{>slug}}` converted to Langfuse composability syntax on sync), how to link prompts in Langfuse UI.
- `README.md` — new "## Integrations" section linking the doc.

### Tests
- `tests/Feature/Integrations/LangfuseTraceEmissionTest.php` — `Http::fake()`; create a Result; assert listener fires; assert outbound request payload shape matches Langfuse's ingestion schema. Test pipeline case (multiple Results → one trace, multiple generations). Test synthesis-specific mapping: when a pipeline produces 3 parallel Results + 1 synthesis Result, assert the synthesis generation's `input` field structurally references the 3 parallel generation IDs, and assert `urge:source:synthesis` tag is present only on the synthesis generation.
- `tests/Feature/Integrations/LangfusePromptSyncTest.php` — create a PromptVersion with `{{>fragment}}`; assert outbound payload contains converted `@@@langfusePrompt:...@@@` syntax.
- `tests/Feature/Integrations/LangfuseScoreImportTest.php` — fake scores response; run command; assert `ResultEvaluation` rows created with correct `evaluator_provider='langfuse'`.
- `tests/Feature/Integrations/IntegrationSettingsTest.php` — admin CRUD; non-admin forbidden; test-connection endpoint.
- Update existing tests: make sure `PipelineService` and `ResultController` tests still pass with the observer firing (use `Event::fake()` where appropriate).

---

## Files to modify

| File | Change |
|---|---|
| `app/Services/VersioningService.php` (line ~62, before `return $version`) | Dispatch `PromptVersionCreated::dispatch($version)`. |
| `app/Models/Prompt.php` (accessor for `pinned_version_id` or dedicated setter) | When pinned version changes, fire `PromptPinnedVersionChanged`. Alternative: do it in `PromptController::update` where the field is written — cleaner because it happens after a single intentional write. |
| `app/Services/PipelineService.php` (line ~99, synthesis Result creation) | Set `source = 'synthesis'` instead of `'api'` for the synthesis channel's Result. Parallel channel Results (line ~56) stay as `source = 'api'`. One-line change; unlocks correct Langfuse hierarchy mapping and clearer UX in URGE's own Results view. |
| `app/Http/Controllers/Api/ResultController.php` | Extend `source` validation rule from `'in:api,manual,import,mcp'` to `'in:api,manual,import,mcp,synthesis'`. |
| `app/Providers/AppServiceProvider.php` | Register `ResultObserver` on `Result::observe(...)`. |
| `app/Providers/EventServiceProvider.php` | Map events → listeners. |
| `app/Console/Kernel.php` or `routes/console.php` | Schedule `urge:sync-langfuse-scores`. |
| `config/urge.php` | Add `integrations.langfuse` block. |
| `routes/api.php` | Add integration endpoints. |

Event emission requires no changes at Result-creation call sites — **the `ResultObserver` centralizes emission to a single chokepoint**, so the five call sites (PipelineService.php:53, PipelineService.php:96, McpToolHandler runPrompt, McpToolHandler storeResult, ResultController::store, PromptController::run) don't need observability instrumentation. The one-line `PipelineService.php` edit above is unrelated to event emission — it's the synthesis-polish scope addition.

---

## Reused existing utilities

- **`Http::` facade pattern** — same as every `app/Services/LlmProviders/*Driver.php`. `LangfuseClient` follows `OpenAiDriver.php` shape.
- **`Integration` model encryption** — use Laravel's native `protected $casts = ['config' => 'encrypted:array']`, same approach `LlmProvider.php` uses for `api_key`.
- **Queue + listener pattern** — reuses Laravel's `ShouldQueue`. Default `QUEUE_CONNECTION=database` is already wired in `config/queue.php`; the `jobs` table exists via framework default.
- **Settings tab shape** — `resources/js/spa/components/settings/LlmProvidersTab.jsx` is the template (form + react-query mutation + test button).
- **Admin gating** — `role:admin` middleware, already used by `UserManagementTab`.
- **SSL verify toggle** — `config('urge.curl_ssl_verify')`, already honored by `OllamaDriver.php` and `GeminiDriver.php`.
- **Composable fragments** — URGE's `{{>slug}}` has a direct Langfuse equivalent (`@@@langfusePrompt:name=X|label=production@@@`); `LangfuseMapper` converts at sync time so composability survives.
- **Namespaced prompt naming** — Langfuse prompts named `{username}/{slug}` matches URGE's URL scheme (`/api/v1/prompts/{username}/{slug}`) exactly.
- **pipeline_run_id as trace_id** — URGE already groups Results from one pipeline run under a UUID; that UUID becomes the Langfuse trace_id with no new state needed.
- **ResultEvaluation's `evaluator_provider` field** — already designed for multi-source evaluations; Langfuse scores slot in cleanly as another provider.

---

## Idempotency & failure modes

- **Ingestion**: Langfuse dedupes on event `id`. We use stable IDs (`urge-generation-{result.id}`, `urge-trace-{pipeline_run_id}`). Re-delivery after queue retry is safe.
- **Prompt sync**: Langfuse auto-increments version on `createPrompt`. To avoid duplicate versions, check if `{name}` already has a version with matching content hash before POSTing — add this to `LangfuseClient::createOrUpdatePrompt`.
- **Score import**: Each `ResultEvaluation` upsert is keyed on `(result_id, evaluation_version, dimension)`. Cursor advances only on successful batch.
- **Integration disabled mid-queue**: Listener re-checks `Integration.enabled` before HTTP call; queued jobs for a disabled integration become no-ops.
- **Langfuse down**: Listener throws → queue retries with backoff (Laravel default 3 attempts). Failed jobs land in `failed_jobs` table for inspection.
- **PII / size limits**: Langfuse drops metadata values >200 chars. `LangfuseMapper` truncates `variables_used` values and logs a warning metadata field.

---

## Verification

1. **Unit/feature tests**: `php artisan test --filter=Langfuse` — all 4 new feature test files green.
2. **Full suite**: `php artisan test` — confirm no regression in existing 385 tests.
3. **End-to-end (outbound trace)**:
   ```
   php artisan migrate
   php artisan queue:work --once   # in one terminal
   ```
   In Settings → Integrations, fill Langfuse keys (or use Langfuse's cloud free tier). Test Connection → success. Then in Workspace, run a prompt via "Run with LLM". Open Langfuse → Traces. Within 5s a trace `urge-result-{id}` appears with one generation, correct input/output, tokens, latency, model, `promptName={owner}/{slug}`, `promptVersion=N`. Tags include `urge`.
4. **End-to-end (pipeline trace with synthesis hierarchy)**: Run a pipeline with 3 parallel channels + 1 synthesis channel. Langfuse shows one trace `urge-pipeline-{uuid}` containing 4 generations. The 3 parallel generations are siblings at the top level, tagged `urge:source:api` and `urge:role:{role_label}`. The synthesis generation is tagged `urge:source:synthesis` and has its `input` field structured to reference the 3 parallel generations by `role_label` + `generation_id`. In Langfuse's trace tree, the synthesis generation is visibly distinct from the parallel siblings (different source tag, different `role_label`). Confirm the parallel→synthesis relationship is navigable in Langfuse's UI.
5. **End-to-end (prompt sync)**: Create a new PromptVersion whose content contains `{{>fragment-slug}}`. Langfuse → Prompts. A new prompt `{owner}/{slug}` appears; its content shows the converted `@@@langfusePrompt:name={owner}/fragment-slug|label=production@@@` reference. Pin a different version → Langfuse `production` label moves.
6. **End-to-end (score import)**: In Langfuse, add a manual human annotation score to a trace URGE emitted. Run `php artisan urge:sync-langfuse-scores`. In URGE Workspace → Results → Evaluations panel, a new evaluation appears with `evaluator_provider=langfuse` and the score.
7. **Failure path**: Disable the integration mid-run. Existing queued jobs should no-op cleanly; no `failed_jobs` rows.
8. **Queue worker must be running** — call this out in `documentation/integrations/langfuse.md` as a prerequisite (not optional; the `sync` queue driver would block LLM calls).

---

## What's deliberately out of scope (v1)

- **OTel ingestion into URGE** (Langfuse → URGE trace display). URGE has no trace UI and adding one would duplicate Langfuse. Keep the separation: Langfuse is the observability surface; URGE links out to it.
- **Cost tracking, latency breakdown, sessions, user analytics, dashboards.** All native to Langfuse. URGE should not build these — they'd be pure duplication. URGE emits the raw numbers (token counts, `duration_ms`), Langfuse does the aggregation and visualization.
- **Real-time score streaming** — scheduled pull is enough; webhooks are not worth the infra for v1.
- **Categorical score import** — Langfuse scores can be categorical; URGE's `ResultEvaluation.score` is numeric (1-5). Skip with a log warning; revisit if users ask.
- **Multi-integration (Helicone, Phoenix)** — the `Integration` model's `kind` column leaves room, but no new integrations in v1. Helicone and Phoenix are both Langfuse-adjacent; revisit after Langfuse is in production use.
- **OpenTelemetry emitter as a shared substrate for multiple observability integrations.** Tempting (Langfuse/Phoenix/Helicone all accept OTel) but premature. Commit to direct Langfuse API calls now; extract an `IntegrationEmitter` contract when integration #2 lands.
- **Standalone result synthesis primitive** (synthesize N pre-existing Results into 1 outside of a Pipeline execution). Today all synthesis happens at generation time via Pipeline synthesis channels. A standalone "distill these 5 archived Results" primitive would need a new MCP tool + API endpoint + either a `synthesis_source_ids` column or a `result_syntheses` pivot table. Deferred as a separate feature decision — independent of Langfuse integration. If wanted later, the `source='synthesis'` enum value added here is reusable.
- **Langfuse Datasets ↔ URGE Collections.** URGE's `CollectionItem` polymorphism already supports `item_type=result`, so a Collection of Results is structurally a dataset. Export to Langfuse would be a clean one-way handoff (curate in URGE, experiment in Langfuse). Deferred because value depends on whether users actually use Langfuse's experiment flow — revisit after real Langfuse usage reveals the need.
- **Distillation meta-prompts as user-tunable versioned prompts** (à la the existing evaluation prompt pattern). Interesting design space — strategy-as-prompt-slug, recursive distillation, best-of-N with judge — but all achievable today in user-land via custom Pipelines with custom synthesis channel system prompts. No engine change needed unless users start asking for first-class strategy selection in the UI.
