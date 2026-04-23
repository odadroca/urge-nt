# URGE v2 — Phase 1 Audit for React Flow Frontend Migration

## Scope

Audit of `odadroca/urge-nt` to map all existing functionality, identify what stays, what changes, and what's missing for a React + React Flow SPA frontend.

---

## 1. Observed Facts

### 1.1 Backend Stack

- **Framework:** Laravel 11 (PHP), SQLite database
- **Frontend:** Livewire 3 + Alpine.js + Tailwind CSS 3 + Vite 7
- **JS dependencies:** SortableJS (drag-drop), diff (word/char diff), alpinejs, axios
- **Auth (web):** Laravel session-based (Breeze scaffold)
- **Auth (API/MCP):** Custom `ApiKeyAuthentication` middleware — Bearer token, SHA-256 hashed keys, rate-limited
- **No Sanctum installed.** SPA auth is not currently supported.

### 1.2 Data Model (14 models)

| Model | Role | Graph relevance |
|---|---|---|
| **Prompt** | Core entity. type: `prompt` or `fragment`. Has slug, tags, visibility, category, soft deletes | **Node** |
| **PromptVersion** | Immutable content snapshots. variables, includes (JSON), variable_metadata | Content behind a node |
| **PromptBranch** | Non-linear versioning (name, head_version_id, forked_from) | Not directly graphed |
| **Result** | LLM responses. Linked to prompt+version. Starred, rated | Side panel data |
| **Collection** | Ordered groups. Nested via CollectionItem (DAG) | **Grouping container** |
| **CollectionItem** | Polymorphic: prompt_version, result, or collection. sort_order | **Edges** (collection→items) |
| **Team** | Sharing groups with roles | Scope filter |
| **Category** | Prompt classification with color | Tag/filter chip |
| **LlmProvider** | 6 drivers (OpenAI, Anthropic, Mistral, Gemini, Ollama, OpenRouter) | Run action config |
| **PipelineTemplate** | Multi-channel LLM execution templates | Run action config |
| **PipelineTemplateChannel** | Individual LLM channels within a pipeline | — |
| **ApiKey** | Bearer auth for API/MCP | — |
| **CollectionShareLink** | Public share tokens for collections | — |
| **User** | roles: admin/editor/viewer, slug for namespacing | — |

### 1.3 Template Engine

- **Variables:** `{{var_name}}` — substituted at render time
- **Fragment includes:** `{{>slug}}` — recursive resolution with circular detection (max depth 10)
- **Variable metadata:** type, default, description, options (enum) — per-version JSON
- This is the **implicit graph**: fragment includes define composition edges

### 1.4 API Surface (already JSON, already complete)

**47 REST endpoints** under `/api/v1/`, all Bearer-token authenticated:

- Prompts: CRUD, namespaced (`/{username}/{slug}`), legacy redirect
- Versions: list, create, get specific
- Branches: CRUD, set default
- Render: POST with variable substitution + include resolution
- Results: CRUD, filter by version/starred
- Collections: CRUD, add/remove items (polymorphic)
- Teams: CRUD, member management
- Pipeline Templates: CRUD, channels, run
- Categories: list, create
- Share Links: CRUD for collections
- Health check
- MCP: POST (JSON-RPC) + GET (SSE stream)

### 1.5 MCP Server

- **15 tools** + **6 resources** via `McpToolHandler`
- Dual transport: SSE (remote) + stdio (local artisan command)
- Full JSON-RPC 2.0 with rate limiting, session management
- All tool calls route through the service layer (TemplateEngine, VersioningService, etc.)

### 1.6 Web UI (Livewire — to be replaced)

**Pages:** Browse, Workspace, Teams, TeamDetail, Settings

**Livewire components (20 total):**
- Browse: tabbed list (prompts/fragments/starred), search, scope sidebar (mine/team/all), create form, collection management
- Workspace: 3-panel layout (version sidebar | editor | results panel)
  - Editor: text/visual mode, autocomplete (variables + fragments), Ctrl+S, AI suggestions, preview with include resolution
  - VersionSidebar: branch switcher, version list, diff
  - ResultsPanel: results list, star, rate, manual result, import, run with LLM, run with template
  - PromptMetadata: name, type, category, tags, description modal
- Settings: API keys, LLM providers, categories, pipeline templates, user management
- Teams: CRUD + member management

**Alpine.js modules (3):**
- `autocomplete.js` — `{{variable}}` and `{{>fragment}}` inline suggestions
- `composer.js` — Block-based visual editor (text/variable/include blocks, SortableJS drag)
- `diff.js` — Word/char-level diff viewer

### 1.7 Internal API (web-only, no auth)

- `GET /internal/variables` — variable names for autocomplete
- `GET /internal/fragments` — fragment slugs for autocomplete

---

## 2. Gap Analysis for React + React Flow SPA

### 2.1 Missing: SPA Authentication

**Current state:** Web uses session auth. API uses Bearer token.
**Problem:** A React SPA needs either Sanctum SPA auth (cookie-based, same-domain) or token-based auth from the frontend.

**Options:**
- **A) Laravel Sanctum SPA mode** — cookie-based CSRF auth for same-domain SPA. Least change to backend. Recommended.
- **B) Token-based** — SPA calls login endpoint, gets token, stores in memory. More decoupled but more frontend work.

**Recommendation:** Sanctum SPA mode. Single `composer require laravel/sanctum` + config.

### 2.2 Missing: Node Position Persistence

**Current state:** No spatial data anywhere. Prompts and collections have no x/y coordinates.
**Needed:** A table or JSON column to persist node positions on the canvas.

**Options:**
- **A) New `graph_positions` table** — `(user_id, node_type, node_id, x, y, canvas_id)`. Allows per-user layouts.
- **B) JSON column on Prompt/Collection** — `canvas_position JSON`. Simpler but shared across users.

**Recommendation:** Option A. Per-user positions. Debounced bulk-save endpoint.

### 2.3 Missing: Explicit Composition Edges

**Current state:** Fragment includes (`{{>slug}}`) are embedded in content text. They're parsed at render time by TemplateEngine.
**For graph:** Edges need to be derived from content (already extractable via `TemplateEngine::extractIncludes()`). No new storage needed — edges are computed from version content.

**Implication:** The graph is a **read-derived view**, not a separate data store. When a user drags a fragment onto a prompt in the graph, the backend action is "append `{{>slug}}` to the prompt content" → save new version → re-derive edges.

### 2.4 Missing: API Endpoints for Graph

New endpoints needed:

| Endpoint | Purpose |
|---|---|
| `GET /api/v1/graph/nodes` | Prompts + fragments + collections with positions for current user |
| `POST /api/v1/graph/positions` | Bulk-save `[{node_type, node_id, x, y}]` |
| `GET /api/v1/graph/edges` | Derived composition edges from all visible prompts' includes |
| `POST /api/v1/prompts/{u}/{s}/append-include` | Append `{{>slug}}` to prompt content (graph drag action) |
| `DELETE /api/v1/prompts/{u}/{s}/remove-include` | Remove `{{>slug}}` from content |

### 2.5 Existing API Gaps (minor)

- `GET /api/v1/prompts` does not return `tags` or `category` in the list response — needed for filter chips
- No `GET /api/v1/settings/llm-providers` endpoint — Settings page needs this
- No `GET /api/v1/settings/pipeline-templates` with channels — exists but may need expansion
- Internal autocomplete endpoints (`/internal/variables`, `/internal/fragments`) need API equivalents with Bearer auth

### 2.6 What Does NOT Change

- All models (zero schema changes except adding `graph_positions` table)
- All services (TemplateEngine, VersioningService, McpToolHandler, LlmDispatchService, etc.)
- All API controllers (keep as-is, extend where needed)
- MCP server (completely untouched)
- Auth middleware for API (ApiKeyAuthentication)
- Database schema (additive only)

---

## 3. Migration Architecture

```
Phase 1: Sanctum + new API endpoints (backend only, Blade still works)
Phase 2: React SPA scaffold (Vite, React Flow, Tailwind)
Phase 3: Canvas page (React Flow with PromptNode, FragmentNode, CollectionNode)
Phase 4: Workspace page (editor, version sidebar, results panel — React)
Phase 5: Browse + Settings + Teams (standard CRUD pages — React)
Phase 6: Remove Livewire/Blade, cleanup
```

Phases 1-3 can run in parallel with existing Blade UI. No breaking changes until Phase 6.

---

## 4. File Inventory

### Files to KEEP (untouched)
- `app/Models/*` (all 14)
- `app/Services/*` (all 10)
- `app/Http/Controllers/Api/*` (all 13)
- `app/Http/Controllers/McpController.php`
- `app/Http/Controllers/ShareController.php`
- `app/Http/Middleware/*`
- `app/Console/*`
- `routes/api.php` (extend, not replace)
- `routes/auth.php`
- `database/migrations/*`
- `config/*`
- `public/openapi.json`
- `tests/*`

### Files to REMOVE (Phase 6 only)
- `app/Livewire/*` (20 components)
- `app/Http/Controllers/InternalApiController.php` (replaced by API equivalents)
- `app/Http/Controllers/ProfileController.php` (replaced by SPA)
- `resources/views/livewire/*` (all Blade views)
- `resources/views/layouts/*`
- `resources/views/components/*`
- `resources/views/auth/*`
- `resources/views/profile/*`
- `resources/views/dashboard.blade.php`
- `resources/js/autocomplete.js`, `composer.js`, `diff.js` (logic moves to React)

### Files to ADD
- `resources/js/` → full React SPA (new directory structure)
- `database/migrations/xxxx_create_graph_positions_table.php`
- `app/Http/Controllers/Api/GraphController.php`
- Sanctum config files

---

## 5. Risk Register

| Risk | Severity | Mitigation |
|---|---|---|
| Parallel Blade + React during migration increases complexity | Medium | Clear route separation: `/app/*` for React, existing routes for Blade |
| React Flow performance with large prompt libraries (500+ nodes) | Low | Virtualization built into React Flow; paginate by collection |
| Edge derivation from content is O(n) per prompt | Low | Cache edges on version save; invalidate on new version |
| Sanctum CSRF + Bearer token coexistence | Low | Sanctum handles both natively; SPA uses cookies, API uses Bearer |
| Loss of Livewire reactivity (auto-refresh on save) | Medium | React Query or SWR for cache invalidation; WebSocket optional |
