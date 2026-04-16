# CLAUDE.md

## Project Overview

URGE v2 is a self-hosted **prompt registry and version control system** that serves two audiences:
1. **Humans** via a React SPA (primary UI: Browse, Canvas, Workspace) and Livewire 3 pages (settings, teams)
2. **LLMs** via a REST API, MCP server, CustomGPT actions, and Claude Skills

URGE is the prompt memory layer that sits behind any LLM. LLMs pull prompts, fill variables, resolve includes, and store results back — all via API. The UI is for curation and management.

**Stack:** Laravel 12 / PHP 8.3+, React 19, React Query, @xyflow/react, Livewire 3, Alpine.js, Tailwind CSS, SQLite, Vite 7

## Build & Dev Commands

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan test         # 376 tests
php artisan serve        # http://127.0.0.1:8000
npm run dev              # Vite HMR
npm run build            # Production
```

## Architecture

### Core Concept

URGE is a **prompt registry with version control and result archiving**, accessible by both humans (UI) and machines (API/MCP).

```
                    ┌─────────────┐
  Claude (MCP) ────>│             │<──── Human (Browser)
  GPT (Actions) ──>│  URGE API   │<──── Claude Skill
  Any LLM ────────>│  + MCP      │
                    └──────┬──────┘
                           │
              ┌────────────┼────────────┐
              v            v            v
          Prompts    PromptVersions   Results
          (registry)  (immutable)    (archive)
```

### Data Flow

```
Prompt (type: prompt|fragment) → PromptVersion[] (immutable) → Result[] (source: api|manual|import|mcp)
Collection → CollectionItem[] (polymorphic: prompt_version|result|collection) — nested collections form a DAG
```

### Core Models (14 domain tables)

- **Prompt** — name, slug (auto-generated, unique per user), type (prompt|fragment), category_id, tags (JSON), visibility (private|shared, default private), pinned_version_id (nullable; NULL = latest is active). Soft deletes.
- **PromptBranch** — prompt_id, name (slugified, unique per prompt), head_version_id, forked_from_version_id, is_default (exactly one per prompt), created_by. Enables non-linear version history.
- **PromptVersion** — immutable (LogicException on update). Auto-numbered per prompt (global version_number) and per branch (branch_version_number). Extracts variables/includes on create. Has commit_message, variable_metadata (JSON), branch_id.
- **Result** — unified response archive. source (api|manual|import|mcp), provider_name (free text), model_name (free text), llm_provider_id (FK, nullable), response_text, rating (1-5), starred (boolean), notes, token counts, duration_ms, import_filename.
- **ResultEvaluation** — result_id, version (auto-incremented per result), scores (JSON with per-dimension scores), composite_score, evaluator_provider, evaluator_model, evaluation_prompt_version_id, evaluated_by.
- **EvaluationSetting** — user_id, enabled, auto_evaluate, provider_id, dimensions (JSON with dimension names, enabled flags, weights).
- **Category** — name, slug (auto-generated), color
- **LlmProvider** — name, driver, api_key (encrypted), model, endpoint, settings (JSON)
- **Pipeline** — name, slug, description, owner, channels (JSON). Multi-channel prompt execution pipelines.
- **Collection** — title, slug (auto-generated, unique), description, created_by. Soft deletes.
- **CollectionItem** — collection_id, item_type+item_id (polymorphic via enforceMorphMap: prompt_version|result|collection), sort_order, notes. Unique constraint on [collection_id, item_type, item_id]. When item_type=collection, enables nested collections (DAG — same child in multiple parents).
- **Team** — name, slug (auto-generated, unique), created_by. Flat groups for sharing prompts.
- **team_user** (pivot) — team_id, user_id, role (owner|member). Unique on (team_id, user_id).
- **prompt_team** (pivot) — prompt_id, team_id. Links shared prompts to teams. Unique on (prompt_id, team_id).

### Integration Surfaces

| Surface | Protocol | Consumer |
|---|---|---|
| React SPA | React 19 at `/app/*`, BrowserRouter, React Query | Humans in browsers (Browse, Canvas, Workspace) |
| REST API | JSON over HTTP, Bearer/OAuth token auth | Any HTTP client, CustomGPT Actions |
| MCP Server (Streamable HTTP) | POST `/api/v1/mcp`, Mcp-Session-Id header, protocol 2025-06-18 | Remote MCP clients (Claude Desktop pointing at hosted URGE) |
| MCP Server (stdio) | stdio transport, Model Context Protocol | Local MCP clients (Claude Code, Claude Desktop on same machine) |
| Claude Skill | Markdown instructions + API calls | Claude Projects |
| Livewire Pages | Livewire 3 (HTML over AJAX) | Humans: /settings, /teams, /teams/{slug}, /prompts/{u}/{s} (classic workspace) |

### MCP Server (dual transport, protocol 2025-06-18)

**Streamable HTTP (primary, for remote/hosted URGE):** `POST /api/v1/mcp`, authenticated via Bearer token or OAuth token. Session state via `Mcp-Session-Id` header. Claude Desktop on your local machine connects to your hosted URGE instance over the network.

**stdio (secondary, for local dev):** Artisan command `php artisan urge:mcp-server`. Same handler logic, different transport wrapper.

Both transports share the same tool dispatch layer — the handler resolves tool calls to service layer methods identically.

**Tools (29):**

Prompt:
- `create_prompt(name, content, type?, category?, tags?, description?)` — create a new prompt with initial version
- `get_prompt(slug, owner?, version?, variables?)` — fetch, optionally render with variables
- `list_prompts(type?, category?, tag?, search?, scope?)` — browse registry (scope: mine|shared|team:{slug}|all)
- `render_prompt(slug, owner?, version?, variables{}, branch?)` — resolve includes + fill variables, return rendered text
- `save_version(slug, owner?, content, commit_message?, branch?)` — create new version (on branch)
- `delete_prompt(slug, owner?)` — delete prompt (owner/admin only)

Results:
- `store_result(slug, owner?, version?, response_text, provider?, model?, branch?, rendered_content?, variables_used?)` — archive a result (version defaults to active)
- `get_results(slug, owner?, version?, starred?, branch?)` — retrieve past results
- `update_result(result_id, rating?, starred?, notes?)` — update result metadata
- `delete_result(result_id)` — delete a result

Evaluation:
- `evaluate_result(result_id)` — server-side LLM-powered evaluation (uses configured provider)
- `store_evaluation(result_id, scores, composite_score?, evaluator_provider?, evaluator_model?)` — client-side evaluation storage (free, no API cost)
- `get_evaluation_prompt(result_id?)` — get the evaluation prompt template (for client-side execution)
- `get_evaluations(result_id)` — get all evaluations for a result

Pipeline:
- `list_pipelines()` — list available pipelines
- `get_pipeline(slug, owner?)` — get pipeline details with channels
- `run_pipeline(slug, owner?, variables?)` — run a pipeline (server-side execution)
- `create_pipeline(name, description?, channels?)` — create a new pipeline
- `update_pipeline(slug, owner?, name?, description?)` — update pipeline metadata
- `delete_pipeline(slug, owner?)` — delete a pipeline

Channels:
- `add_channel(pipeline_slug, owner?, name, provider_slug, system_prompt?, settings?)` — add channel to pipeline
- `update_channel(pipeline_slug, owner?, channel_id, name?, provider_slug?, system_prompt?, settings?)` — update a channel
- `remove_channel(pipeline_slug, owner?, channel_id)` — remove channel from pipeline

Providers:
- `list_providers()` — list configured LLM providers
- `run_prompt(prompt, provider_slug?, system_prompt?)` — run a prompt against a provider

Branches:
- `list_branches(slug, owner?)` — list branches for a prompt
- `create_branch(slug, owner?, name, from_version?)` — create a branch

Teams:
- `list_teams()` — list user's teams
- `share_prompt(slug, team_slug)` — share prompt with team

**Resources:**
- `urge://prompts` — list of visible prompts (respects namespace scoping)
- `urge://prompts/{username}/{slug}` — prompt with active version content (namespaced)
- `urge://prompts/{username}/{slug}/v/{n}` — specific version content (namespaced)
- `urge://prompts/{username}/{slug}/branches` — list branches for a prompt
- `urge://prompts/{username}/{slug}/branches/{branch}` — branch HEAD content
- `urge://teams` — list user's teams

### API Endpoints (prefix `/api/v1/`)

```
GET    /prompts                              — list prompts (filter: type, category, tag, search, scope)
POST   /prompts                              — create prompt (private by default)
GET    /prompts/{username}/{slug}            — get prompt with active version
PATCH  /prompts/{username}/{slug}            — update prompt metadata
DELETE /prompts/{username}/{slug}            — delete prompt (owner/admin only)
GET    /prompts/{username}/{slug}/versions   — list versions
POST   /prompts/{username}/{slug}/versions   — create version
GET    /prompts/{username}/{slug}/versions/{n} — get specific version
POST   /prompts/{username}/{slug}/render     — render with variables, return text
GET    /prompts/{username}/{slug}/branches   — list branches
POST   /prompts/{username}/{slug}/branches   — create branch
GET    /prompts/{username}/{slug}/branches/{branch} — get branch details
DELETE /prompts/{username}/{slug}/branches/{branch} — delete branch
PATCH  /prompts/{username}/{slug}/branches/{branch}/default — set as default
GET    /prompts/{username}/{slug}/results    — list results
POST   /prompts/{username}/{slug}/results    — store result
POST   /prompts/{username}/{slug}/share      — share with team
DELETE /prompts/{username}/{slug}/share/{team} — unshare from team
GET    /prompts/{slug}                       — legacy redirect to namespaced URL
GET    /results/starred                      — list starred results across all prompts
GET    /results/{id}                         — get single result
PATCH  /results/{id}                         — update rating/starred/notes
DELETE /results/{id}                         — delete result
POST   /results/{id}/evaluate               — evaluate result with LLM scoring
GET    /results/{id}/evaluations             — list evaluations for a result
GET    /results/{id}/evaluations/latest      — get latest evaluation
GET    /results/{id}/evaluations/{version}   — get specific evaluation version
GET    /teams                                — list user's teams
POST   /teams                                — create team
GET    /teams/{slug}                         — get team details
PATCH  /teams/{slug}                         — update team
DELETE /teams/{slug}                         — delete team
POST   /teams/{slug}/members                 — add member
DELETE /teams/{slug}/members/{user}          — remove member
GET    /health                               — health check
```

Auth: Bearer token → SHA-256 hash lookup. Keys scoped to specific prompts via pivot table.

### Services

- **TemplateEngine** — `{{variable}}` substitution, `{{>slug}}` recursive include resolution, circular reference detection, max depth config
- **VersioningService** — transactional version creation, auto-numbering (global + per-branch), variable/include extraction, metadata filtering, branch CRUD (create, delete, set-default)
- **ApiKeyService** — key generation (prefix + random bytes), SHA-256 hash storage, preview
- **ImportExportService** — .md with YAML frontmatter import/export (regex-based parsing, no Symfony YAML dependency)
- **LlmDispatchService** — resolves LlmProvider driver to concrete driver class, dispatches prompt. Supports `dispatch()` and `dispatchWithSystem()`.
- **AiAssistantService** — meta-prompts via `dispatchWithSystem()` for diff summarization (`summarizeDifferences`) and prompt improvement suggestions (`suggestImprovements`)
- **CollectionNestingService** — circular reference detection (BFS ancestor walk), depth validation (configurable max depth with unlimited toggle), validates nesting before creating collection-type CollectionItems
- **EvaluationService** — LLM-powered result scoring with 6 configurable dimensions, versioned evaluations, composite score calculation, auto-evaluate support

### Livewire Components

```
app/Livewire/
├── Dashboard.php              # Recent prompts, starred results, inline create
├── Browse.php                 # Tabbed (prompts/fragments/collections/starred), category+tag filters, namespace sidebar, add-to-collection (single + bulk)
├── Settings.php               # Tabbed settings container (API Keys, LLM Providers, Categories, Pipelines, Evaluation, Users)
├── Teams.php                  # Teams list + create├── TeamDetail.php             # Team members + shared prompts management├── Browse/
│   └── CollectionList.php     # Collection CRUD, expand/collapse, reorder items
├── Settings/
│   ├── ApiKeys.php            # API key CRUD, reveal, scope to prompts
│   ├── LlmProviders.php       # LLM provider CRUD, test connection, toggle active
│   ├── Categories.php         # Category CRUD with color picker
│   ├── Evaluation.php         # Enable/auto-evaluate toggles, provider selection, dimension config with weights
│   └── UserManagement.php     # Admin-only user role management
└── Workspace/
    ├── WorkspacePage.php      # 3-panel orchestrator
    ├── Editor.php             # Textarea, variable detection, save, export, AI suggest, Ctrl+S
    ├── VersionSidebar.php     # Branch switcher, version list (filtered by branch), create/delete/set-default branch
    ├── ResultsPanel.php       # Results list, star, rate, compare, AI summarize, export
    ├── ManualResultForm.php   # Paste result with provider/model/notes/rating
    ├── ImportResults.php      # Upload .md files, preview frontmatter, import as results
    ├── RunWithLlm.php         # LLM execution: provider selection, variable fill, run, Ctrl+Enter
    └── PromptMetadata.php     # Name, type, category, tags, description
```

### React SPA (mounted at `/app/*`)

```
resources/js/
├── app.jsx                    # React root, BrowserRouter basename="/app"
├── components/
│   ├── Sidebar.jsx            # Slim icon-rail navigation (desktop)
│   ├── BottomTabBar.jsx       # Mobile bottom tab bar
│   ├── PromptCard.jsx         # Card component for browse grid
│   └── ...
├── pages/
│   ├── BrowsePage.jsx         # Tabs: prompts/fragments/collections/starred, filters, card grid
│   ├── CanvasPage.jsx         # Graph visualization with @xyflow/react
│   └── WorkspacePage.jsx      # 3-panel editor (React version)
└── hooks/
    └── ...                    # React Query hooks for API data fetching
```

Post-login redirect: `/app/browse`

### Livewire Event Flow

```
VersionSidebar --[version-selected]--> WorkspacePage --> Editor, ResultsPanel, ManualResultForm, ImportResults, RunWithLlm
VersionSidebar --[branch-switched]--> WorkspacePage --> Editor
Editor --[version-created]--> WorkspacePage --> VersionSidebar, ResultsPanel, ImportResults, RunWithLlm
ManualResultForm --[result-saved]--> ResultsPanel
ImportResults --[result-saved]--> ResultsPanel
RunWithLlm --[result-saved]--> ResultsPanel
Editor toolbar --[toggle-run-panel]--> RunWithLlm
notify (any component) --[notify]--> app.blade.php toast system
```

### Routes

```
React SPA (at /app/*, BrowserRouter):
/app/browse, /app/canvas, /app/workspace/:username/:slug

Livewire (wire:navigate):
/settings, /teams, /teams/{slug}, /prompts/{username}/{slug} (classic workspace)

OAuth:
GET/POST /oauth/authorize, POST /oauth/token
POST /oauth/register (Dynamic Client Registration, RFC 7591)
GET /oauth/github, GET /oauth/github/callback
GET /.well-known/oauth-protected-resource (RFC 9728)
GET /.well-known/oauth-authorization-server (RFC 8414)
GET /.well-known/openid-configuration (OIDC Discovery)

Internal API (no auth, same-origin only):
POST /internal/variables    — extract variables from content
GET  /internal/fragments    — list fragment slugs for autocomplete

API (prefix /api/v1/, Bearer/OAuth auth):
See API Endpoints above
```

### Auth & Roles

Web: Breeze (Blade stack). Roles: admin, editor, viewer. First user auto-admin. `RequireRole` middleware as `role`.
API: Triple-auth cascade — Sanctum sessions (SPA) → OAuth 2.1 tokens → API keys (`urge_` prefix). Rate limited per key.
OAuth 2.1: PKCE with S256 (required for public clients, optional for confidential). Confidential client support with `client_secret` (for Mistral Le Chat). Dynamic Client Registration (RFC 7591) for Claude Desktop / Claude.ai. Scopes `mcp:read`, `mcp:write`, `mcp:admin` (enforced on OAuth tokens only; API keys have full access). GitHub as external identity provider. Discovery via `/.well-known/oauth-protected-resource`, `/.well-known/oauth-authorization-server`, and `/.well-known/openid-configuration`.
Namespaces: Prompts are private by default. Shared via teams. Visibility scope: `Prompt::visibleTo($user)`. Owner can delete/rename/share. Team members can edit content. Admins override all.

### Template Syntax

- `{{variable_name}}` — variable placeholder
- `{{>slug}}` — include another prompt's active version
- Max depth: `URGE_MAX_INCLUDE_DEPTH` env (default 10)

### Key Patterns

- **Blade/Alpine `{{` conflict:** Use `'{' + '{'` string splitting in JS contexts
- **Auto-slug:** Prompt, Category, and Collection generate from name/title with collision counter
- **Immutable versions:** `PromptVersion::booted()` throws LogicException on update
- **Active version:** Prompt accessor resolves: pinned version → default branch HEAD → latest (legacy fallback)
- **Branching:** Non-linear version history with independent branch version numbers. Display format: `main#3 (v7)`. No merge (phase 1).

### Config

`config/urge.php` — `max_include_depth`, `curl_ssl_verify`, `api_rate_limit`, `api_rate_window`, `key_prefix`, `key_bytes`, `max_collection_depth` (default 5), `unlimited_collection_depth` (default false)

OAuth config (env): `OAUTH_TOKEN_TTL` (token lifetime), `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` (for GitHub OAuth provider)
OAuth clients: Created via `php artisan oauth:create-client {name}` with `--redirect` and `--confidential` flags. Pre-registered clients use `client_id` + `client_secret` (confidential) or Dynamic Client Registration (public).

### LLM Driver Architecture

```
app/Services/LlmProviders/
├── Contracts/LlmDriverInterface.php   # complete(prompt), completeWithSystem(system, user)
├── LlmResult.php                      # Readonly value object: success/failure factories
├── OpenAiDriver.php                   # Http::withToken(), optional baseUrl override
├── AnthropicDriver.php                # x-api-key header, top-level system param
├── MistralDriver.php                  # OpenAI-compatible endpoint
├── GeminiDriver.php                   # ?key= in URL, systemInstruction key
├── OllamaDriver.php                   # No API key, 300s timeout, configurable baseUrl
└── OpenRouterDriver.php               # Raw curl, HTTP-Referer + X-Title headers
```

Drivers: openai, anthropic, mistral, gemini, ollama, openrouter. `LlmDispatchService::resolveDriver()` maps `LlmProvider.driver` to concrete class.

### Keyboard Shortcuts (workspace)

- **Ctrl+S** / **Cmd+S** — Save version
- **Ctrl+Enter** / **Cmd+Enter** — Toggle Run LLM panel

### Toast Notifications

Global Alpine `toasts()` component in `app.blade.php`. Any Livewire component dispatches:
```php
$this->dispatch('notify', message: 'Done', type: 'success'); // or 'error'
```

### Artisan Commands

- `php artisan urge:mcp-server` — stdio MCP server
- `php artisan urge:import-v1 {path}` — import v1 SQLite database (idempotent, transaction-wrapped)
- `php artisan oauth:create-client {name}` — create pre-registered OAuth client (`--redirect=URL`, `--confidential`)
- `php artisan urge:seed-evaluation` — create default evaluation prompt, pipeline, and settings

### Live Preview (Phase 6)

The Editor has a **Preview** toggle button in the toolbar. When active, the editor area splits vertically:
- **Top:** textarea/visual editor
- **Bottom:** live rendered preview with variable fill form

Preview features:
- Resolves `{{>slug}}` includes recursively (shows green badges for resolved includes)
- Fills `{{variable}}` from metadata defaults or user-overridden values in the preview form
- Shows amber badges for missing/unfilled variables
- Handles circular includes and max depth errors gracefully (red error display)
- Updates automatically as content, variables, or metadata defaults change

State: `showPreview`, `previewVariables`, `previewResult`, `previewError` on Editor component.

## Current Status

**Phases 1-7 complete + UX sprints done + post-phase improvements + version branching + nested collections + React SPA UI + OAuth 2.1 (PKCE + confidential clients) + Streamable HTTP MCP + result evaluation + pipeline management + client-side execution.** 376 tests passing. 29 MCP tools. Verified MCP connectivity: Claude.ai, Claude Desktop, Mistral Le Chat, stdio (Claude Code).

### Phase Roadmap

| Phase | Focus |
|---|---|
| 1 (done) | Core workspace UI |
| 2 (done) | API layer + MCP server + OpenAPI spec |
| 3 (done) | Rich editing (autocomplete, visual composer, diff, compare) |
| 4 (done) | Import/export + collections |
| 5 (done) | LLM drivers + AI features + v1 migration + polish |
| 6 (done) | Prompt preview (live rendered preview with includes resolved + variables filled from defaults) |
| UX (done) | 8 UX/UI improvement sprints (see docs/ux-roadmap.md) |
| 7 (done) | Namespaces + teams | User-scoped prompts, team sharing, namespace-aware API/MCP/UI |

### Post-Phase 7 Improvements
- **Collection visibility** — always-visible archive icon on version sidebar, styled "Collect" pill on results panel, "Collect" button + bulk selection mode on Browse page (spec: `docs/superpowers/specs/2026-03-26-collection-visibility-design.md`)
- **Prompt metadata** — moved from sidebar inline editing to a dedicated modal (`PromptMetadata.php`)
- **Public layout** — added anonymous Blade component at `resources/views/components/layouts/public.blade.php` for `<x-layouts.public>` resolution (fixes `view:cache`)
- **Version branching** — non-linear version history with `PromptBranch` model, branch CRUD (create/delete/set-default), dual version numbers (global + per-branch), MCP tools (`list_branches`, `create_branch`), API endpoints, branch switcher UI
- **Nested collections** — collections inside collections (DAG), `CollectionNestingService` for circular ref detection + depth validation, configurable depth (`max_collection_depth`, `unlimited_collection_depth`), recursive API/share rendering, "Nest" action in UI (spec: `docs/superpowers/specs/2026-03-27-nested-collections-design.md`)
- **React SPA** — React 19 mounted at `/app/*` with Browse (card grid, tabs, filters), Canvas (graph visualization via @xyflow/react), and Workspace (3-panel editor) pages. Slim icon-rail sidebar, mobile bottom tab bar. Post-login redirect to `/app/browse`.
- **OAuth 2.1** — PKCE (S256), confidential client support (`client_secret`), Dynamic Client Registration (RFC 7591), scoped tokens (mcp:read, mcp:write, mcp:admin), GitHub external identity provider, OIDC discovery (`/.well-known/openid-configuration`). Verified with Claude.ai/Desktop (public PKCE) and Mistral Le Chat (confidential client).
- **Streamable HTTP MCP** — Protocol version 2025-06-18, POST `/api/v1/mcp`, session via `Mcp-Session-Id` header. 29 tools. Verified with Claude.ai, Claude Desktop, Mistral Le Chat, and stdio (Claude Code).
- **Result evaluation** — LLM-powered scoring with 6 configurable dimensions (relevance, completeness, accuracy, clarity, conciseness, human), versioned evaluations, composite scores, auto-evaluate option. Evaluation settings UI tab. Canvas nodes show color-coded score badges. Workspace result cards show composite scores.
- **Pipeline management** — PipelineTemplate renamed to Pipeline everywhere (tables, models, routes, MCP tools, UI). Full CRUD via MCP: create/update/delete pipelines, add/update/remove channels.
- **Client-side execution** — LLMs can fetch prompts/pipelines from URGE, run them natively (free, no API cost), store results back. Flow: get_prompt → execute → store_result. For pipelines: get_pipeline → run each channel → store_result per channel. store_result version now optional (defaults to active), accepts rendered_content and variables_used.
