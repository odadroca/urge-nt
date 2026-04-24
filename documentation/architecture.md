# URGE v2 Architecture

## Vision

URGE is a **prompt registry, version control, and result archive** with two access patterns:
- **Human access** via a React SPA (Browse, Canvas, Workspace, Settings, Teams)
- **Machine access** via REST API + MCP server (LLMs consume and contribute to the registry)

The key insight: instead of URGE calling LLMs, **LLMs call URGE**. URGE is the memory and management layer; any LLM can pull prompts, render templates, and store results back.

## Data Model

### Entity Relationships

```
User ──< Prompt ──< PromptVersion ──< Result
   │          │                          │
   │          └── Category               └── LlmProvider (nullable)
   │
   └──<> Team (pivot: team_user, role: owner|member)
              │
              └──<> Prompt (pivot: prompt_team — shared prompts)

Collection ──< CollectionItem ──> (PromptVersion | Result | Collection)
                                       ↑ nested collections form a DAG (multiple parents allowed)

ApiKey ──<> Prompt (pivot: api_key_prompt)
```

### Tables

| Table | Key Fields | Notes |
|---|---|---|
| users | role (admin/editor/viewer) | First user auto-admin |
| prompts | slug (unique per user), type (prompt/fragment), visibility (private/shared), pinned_version_id, tags (JSON), default_branch_id | Soft deletes, auto-slug, namespace-scoped |
| prompt_branches | prompt_id, name (slugified), head_version_id, forked_from_version_id, is_default, created_by | Non-linear version history |
| prompt_versions | version_number, branch_version_number, branch_id, content, variables (JSON), includes (JSON), variable_metadata (JSON), commit_message | Immutable |
| results | source, provider_name, model_name, response_text, starred, rating, rendered_content, variables_used (JSON), input_tokens, output_tokens, duration_ms, status, error_message, import_filename, created_by | Unified response archive |
| categories | name, slug, color | Auto-slug |
| llm_providers | driver, api_key (encrypted), model, endpoint, settings (JSON) | 6 drivers |
| collections | title, slug, description, created_by | Ordered groups, soft deletes |
| collection_items | item_type, item_id, sort_order, notes | Polymorphic (prompt_version, result, collection). collection type enables nesting (DAG) |
| collection_share_links | collection_id, token, label, expires_at, access_count, created_by | Public share links for collections |
| api_keys | key_hash (SHA-256), key_preview, is_active, expires_at | Bearer auth |
| api_key_prompt | api_key_id, prompt_id | Scope keys to prompts |
| pipelines | name, slug, description, created_by, is_active | Multi-channel prompt execution pipelines |
| pipeline_channels | pipeline_id, role_label, llm_provider_id, system_prompt, trigger, sort_order | Pipeline channels. system_prompt supports `{{>slug}}` includes |
| result_evaluations | result_id, version, scores (JSON), composite_score, evaluator_provider, evaluator_model, evaluation_prompt_version_id, evaluated_by | Versioned LLM-powered result scoring |
| evaluation_settings | user_id, enabled, auto_evaluate, provider_id, dimensions (JSON) | Per-user evaluation configuration |
| teams | name, slug (unique), created_by | Flat groups for sharing (Phase 7) |
| team_user | team_id, user_id, role (owner/member) | Team membership pivot (Phase 7) |
| prompt_team | prompt_id, team_id | Prompt-team sharing pivot (Phase 7) |

### Design Decisions

1. **Unified Result** — one table replaces v1's prompt_runs + llm_responses + library_entries. `source` column distinguishes origin. `starred` boolean replaces Library.
2. **Free-text provider/model** — manual pastes and MCP-sourced results don't need a configured provider. `llm_provider_id` only set for API-driven results.
3. **Pinned version** — NULL = latest is active. Explicit pin overrides. Cleaner than v1's active_version_id.
4. **Prompt type** — `prompt` vs `fragment`. Same model, same versioning, type flag controls include behavior.
5. **Nested collections** — collections inside collections via polymorphic `CollectionItem`. DAG structure (same collection in multiple parents). Circular reference detection + configurable depth limit.
6. **Version branching** — non-linear version history via `PromptBranch`. Each branch tracks its own HEAD and version numbers. Display format: `main#3 (v7)`.

## Integration Architecture

### Six Surfaces, One Backend

```
┌───────────────────────────────────────────────────────────────────┐
│                         Laravel App                                │
│                                                                    │
│  ┌──────────┐ ┌──────────┐ ┌───────────┐ ┌────────┐              │
│  │React SPA │ │ REST API │ │MCP Stream.│ │MCP     │              │
│  │ /app/*   │ │ /api/v1/ │ │HTTP /mcp  │ │stdio   │              │
│  └────┬─────┘ └────┬─────┘ └─────┬─────┘ └───┬────┘              │
│       │             │              │            │                   │
│       └─────────────┼──────────────┴────────────┘                   │
│                     v            v                                  │
│            ┌──────────────────┐                                    │
│            │  Service Layer   │                                    │
│            │  TemplateEngine  │                                    │
│            │  VersioningSvc   │                                    │
│            │  McpToolHandler  │                                    │
│            │  ApiKeySvc       │                                    │
│            │  LlmDispatchSvc  │                                    │
│            │  AiAssistantSvc  │                                    │
│            │  ImportExportSvc │                                    │
│            └────────┬─────────┘                                    │
│                     v                                              │
│            ┌──────────────────┐                                    │
│            │  Eloquent/SQLite │                                    │
│            └──────────────────┘                                    │
└───────────────────────────────────────────────────────────────────┘

External consumers:
  Browser (human)  ──> React SPA (/app/*)
  Any HTTP client  ──> REST API
  OAuth client     ──> OAuth 2.1 (PKCE) ──> REST API / MCP
  Claude Desktop   ──> MCP Streamable HTTP (remote) or MCP stdio (local)
  CustomGPT        ──> REST API (via OpenAPI spec)
```

### OAuth 2.1

Triple-auth cascade: Sanctum sessions (SPA) → OAuth 2.1 tokens → API keys (`urge_` prefix).

OAuth 2.1 with PKCE (S256 required for public clients, optional for confidential). Confidential client support with `client_secret` (for Mistral Le Chat). Dynamic Client Registration (RFC 7591) for Claude Desktop / Claude.ai. Scopes: `mcp:read`, `mcp:write`, `mcp:admin` (enforced on OAuth tokens; API keys have full access). GitHub as external identity provider.

**Public client flow (Claude.ai, Claude Desktop):**
```
Client ──401──> /.well-known/oauth-protected-resource
             ──> /.well-known/oauth-authorization-server
             ──> POST /oauth/register (Dynamic Client Registration)
             ──> GET/POST /oauth/authorize (PKCE S256 challenge)
             ──> POST /oauth/token (exchange code + code_verifier for token)
             ──> API/MCP with Bearer token
```

**Confidential client flow (Mistral Le Chat):**
```
Pre-register: php artisan oauth:create-client "Le Chat" --redirect="..." --confidential
Client ──> /.well-known/openid-configuration (OIDC Discovery)
       ──> GET/POST /oauth/authorize
       ──> POST /oauth/token (exchange code + client_secret for token)
       ──> API/MCP with Bearer token
```

**Discovery endpoints:**
- `GET /.well-known/oauth-protected-resource` — RFC 9728
- `GET /.well-known/oauth-authorization-server` — RFC 8414
- `GET /.well-known/openid-configuration` — OIDC Discovery (required by Mistral Le Chat)

### REST API (`/api/v1/`)

Bearer token or OAuth token auth via middleware cascade. Rate limited per key.

```
Prompts:
  GET    /prompts                              — list (filter: type, category, tag, search, scope)
  POST   /prompts                              — create (private by default)
  GET    /prompts/{username}/{slug}            — get with active version
  PATCH  /prompts/{username}/{slug}            — update metadata
  DELETE /prompts/{username}/{slug}            — delete (owner/admin only)
  GET    /prompts/{slug}                       — legacy redirect → namespaced URL

Versions:
  GET    /prompts/{username}/{slug}/versions   — list all
  POST   /prompts/{username}/{slug}/versions   — create new
  GET    /prompts/{username}/{slug}/versions/{n} — get specific

Rendering:
  POST   /prompts/{username}/{slug}/render     — resolve includes + fill variables → text

Branches:
  GET    /prompts/{username}/{slug}/branches   — list branches
  POST   /prompts/{username}/{slug}/branches   — create branch
  GET    /prompts/{username}/{slug}/branches/{branch} — get branch details
  DELETE /prompts/{username}/{slug}/branches/{branch} — delete branch
  PATCH  /prompts/{username}/{slug}/branches/{branch}/default — set as default

Results:
  GET    /prompts/{username}/{slug}/results    — list (filter: version, starred)
  POST   /prompts/{username}/{slug}/results    — store
  GET    /results/starred                      — list starred results across all prompts
  GET    /results/{id}                         — get single
  PATCH  /results/{id}                         — update rating/starred/notes
  DELETE /results/{id}                         — delete result

Evaluations:
  POST   /results/{id}/evaluate               — evaluate result with LLM scoring
  GET    /results/{id}/evaluations             — list evaluations for a result
  GET    /results/{id}/evaluations/latest      — get latest evaluation
  GET    /results/{id}/evaluations/{version}   — get specific evaluation version

Sharing:
  POST   /prompts/{username}/{slug}/share      — share with team
  DELETE /prompts/{username}/{slug}/share/{team} — unshare from team

Teams:
  GET    /teams                                — list user's teams
  POST   /teams                                — create team
  GET    /teams/{slug}                         — get team details
  PATCH  /teams/{slug}                         — update team
  DELETE /teams/{slug}                         — delete team
  POST   /teams/{slug}/members                 — add member
  DELETE /teams/{slug}/members/{user}          — remove member
  POST   /teams/{slug}/leave                   — leave team

Collections:
  GET    /collections                          — list collections
  POST   /collections                          — create collection
  GET    /collections/{slug}                   — get collection (recursive nested structure)
  PATCH  /collections/{slug}                   — update collection
  DELETE /collections/{slug}                   — delete collection
  POST   /collections/{slug}/items             — add item (prompt_version, result, or collection)
  DELETE /collections/{slug}/items/{item}      — remove item

Share Links:
  GET    /collections/{slug}/share-links       — list share links
  POST   /collections/{slug}/share-links       — create share link
  DELETE /collections/{slug}/share-links/{id}  — revoke share link

System:
  GET    /health                               — health check
```

### MCP Server (dual transport, protocol 2025-06-18)

Two transports, one shared handler layer:

**Streamable HTTP transport (primary, for hosted/remote URGE):**
- `POST /api/v1/mcp`, authenticated via Bearer token or OAuth token
- Session state via `Mcp-Session-Id` header (set by server on first response)
- Use case: Claude Desktop on your laptop connects to URGE on Hostinger
- Runs within the Laravel HTTP server — no extra process needed

**stdio transport (secondary, for local dev):**
- Artisan command: `php artisan urge:mcp-server`
- Use case: Claude Code / Claude Desktop on the same machine as URGE
- Reads JSON-RPC from stdin, writes to stdout

Both transports dispatch to the same `McpToolHandler` service, which maps tool calls to TemplateEngine, VersioningService, and Eloquent queries.

**Tools (29):**
| Tool | Purpose |
|---|---|
| `create_prompt` | Create a new prompt with initial version |
| `get_prompt` | Fetch prompt by slug (+ optional owner for namespace), optionally specific version |
| `list_prompts` | Browse/search the registry (scope: mine/shared/team/all) |
| `render_prompt` | Resolve includes + fill variables → rendered text |
| `save_version` | Create new version of a prompt |
| `delete_prompt` | Delete prompt (owner/admin only) |
| `store_result` | Archive a result (version defaults to active, accepts rendered_content/variables_used) |
| `get_results` | Retrieve past results for a prompt |
| `update_result` | Update result metadata (rating, starred, notes) |
| `delete_result` | Delete a result |
| `evaluate_result` | Server-side LLM-powered evaluation (uses configured provider) |
| `store_evaluation` | Client-side evaluation storage (free, no API cost) |
| `get_evaluation_prompt` | Get evaluation prompt template for client-side execution |
| `get_evaluations` | Get all evaluations for a result |
| `list_pipelines` | List available pipelines |
| `get_pipeline` | Get pipeline details with channels |
| `run_pipeline` | Run a pipeline (server-side execution) |
| `create_pipeline` | Create a new pipeline |
| `update_pipeline` | Update pipeline metadata |
| `delete_pipeline` | Delete a pipeline |
| `add_channel` | Add a channel to a pipeline |
| `update_channel` | Update a channel in a pipeline |
| `remove_channel` | Remove a channel from a pipeline |
| `list_providers` | List configured LLM providers |
| `run_prompt` | Run a prompt against a provider |
| `list_branches` | List branches for a prompt |
| `create_branch` | Create a new branch |
| `list_teams` | List user's teams |
| `share_prompt` | Share prompt with a team |

**Resources:**
| URI | Purpose |
|---|---|
| `urge://prompts` | List of visible prompts (namespace-scoped) |
| `urge://prompts/{username}/{slug}` | Prompt with active version content (namespaced) |
| `urge://prompts/{username}/{slug}/v/{n}` | Specific version content (namespaced) |
| `urge://prompts/{username}/{slug}/branches` | List branches for a prompt |
| `urge://prompts/{username}/{slug}/branches/{branch}` | Branch HEAD content |
| `urge://teams` | List user's teams |

### Internal Endpoints (no auth, same-origin only)

```
POST /internal/variables     — extract variables from content
GET  /internal/fragments     — list fragment slugs for autocomplete
```

Used by the Editor's inline autocomplete to suggest variable names and fragment includes.

### CustomGPT Actions

OpenAPI 3.0 spec generated from the REST API. Hosted at `/api/openapi.json`. GPT custom actions import this spec directly.

### Claude Skill

Markdown file with instructions + API examples. Tells Claude how to call the URGE API with curl/fetch for prompt retrieval and result storage.

## Component Architecture

### React SPA (mounted at `/app/*`)

The React SPA is the sole user-facing frontend. Blade views remain only for auth pages (login, register) and user profile.

```
resources/js/spa/
├── main.jsx                   # React root, BrowserRouter basename="/app"
├── App.jsx                    # Route definitions
├── api/
│   ├── client.js              # Axios client with /api/v1 base, CSRF, credentials
│   ├── prompts.js             # Prompt CRUD + render + run
│   ├── providers.js           # LLM provider CRUD + test connection
│   ├── categories.js          # Category CRUD
│   ├── teams.js               # Team CRUD + members + leave
│   ├── apiKeys.js             # API key CRUD
│   ├── users.js               # User management (admin)
│   ├── evaluationSettings.js  # Evaluation config get/save
│   └── pipelines.js           # Pipeline + channel CRUD
├── components/
│   ├── Sidebar.jsx            # Slim icon-rail navigation (desktop)
│   ├── BottomTabBar.jsx       # Mobile bottom tab bar
│   ├── Layout.jsx             # App shell with sidebar + content area
│   ├── ProtectedRoute.jsx     # Auth guard
│   ├── browse/                # Browse sub-components (PromptCard, CollectionList, etc.)
│   └── settings/              # Settings tab components (6 tabs)
├── pages/
│   ├── BrowsePage.jsx         # Tabs: prompts/fragments/collections/starred, filters, card grid
│   ├── CanvasPage.jsx         # Graph visualization with @xyflow/react
│   ├── WorkspacePage.jsx      # 3-panel editor (editor, version sidebar, results)
│   ├── SettingsPage.jsx       # Tabbed settings (API Keys, Providers, Categories, Pipelines, Evaluation, Users)
│   ├── TeamsPage.jsx          # Teams list + create
│   ├── TeamDetailPage.jsx     # Team members + shared prompts management
│   └── LoginPage.jsx          # Login form
└── hooks/
    ├── useAuth.js             # Auth state (user, login, logout)
    └── ...                    # React Query hooks for API data fetching
```

### Service Layer

```
app/Services/
├── TemplateEngine.php             # {{var}} + {{>slug}} rendering, circular detection
├── VersioningService.php          # Transactional version creation, auto-numbering, branch CRUD
├── ApiKeyService.php              # Key generation (prefix + random bytes), SHA-256 hashing
├── ImportExportService.php        # .md with YAML frontmatter import/export
├── McpToolHandler.php             # MCP tool dispatch (shared by SSE + stdio transports)
├── LlmDispatchService.php        # Resolve driver, dispatch prompt
├── AiAssistantService.php        # Meta-prompts: diff summarization, improvement suggestions
├── CollectionNestingService.php   # Circular ref detection, depth validation for nested collections
├── EvaluationService.php          # LLM-powered result scoring, 6 dimensions, versioned evaluations, composite scores
├── ShareLinkService.php           # Create/revoke/find share links for collections
└── LlmProviders/
    ├── Contracts/LlmDriverInterface.php   # complete(), completeWithSystem()
    ├── LlmResult.php                      # Readonly value object
    ├── OpenAiDriver.php
    ├── AnthropicDriver.php
    ├── MistralDriver.php
    ├── GeminiDriver.php
    ├── OllamaDriver.php
    └── OpenRouterDriver.php
```

### Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan urge:mcp-server` | Start stdio MCP server for local clients |
| `php artisan urge:import-v1 {path}` | Migrate data from URGE v1 SQLite database (idempotent, transaction-wrapped) |
| `php artisan oauth:create-client {name}` | Create pre-registered OAuth client (`--redirect=URL`, `--confidential`) |
| `php artisan urge:seed-evaluation` | Create default evaluation prompt, pipeline, and settings |

## Phase Roadmap

| Phase | Scope | Deliverables |
|---|---|---|
| 1 (done) | Core workspace | Models, services, Livewire workspace |
| 2 (done) | API + MCP | REST API, MCP server (SSE + stdio), OpenAPI spec, API key management |
| 3 (done) | Rich editing | Inline autocomplete, visual composer, version diff, result comparison |
| 4 (done) | Import/export + collections | .md import/export, collections CRUD, enhanced browse |
| 5 (done) | LLM drivers + AI + polish | 6 LLM drivers, AI assistant, v1 migration, settings UI, roles |
| 6 (done) | Live preview | Rendered preview with include resolution + variable fill from defaults |
| UX (done) | UX/UI polish | 8 sprints: browse merge, inline categories, toolbar, continue link, tag autocomplete, onboarding, settings access, visual polish |
| 7 (done) | Namespaces + teams | User-scoped prompts, team sharing, namespace-aware API/MCP/UI |
| Post-7 (done) | Version branching | Non-linear version history with PromptBranch, branch CRUD, MCP/API support |
| Post-7 (done) | Nested collections | Collections inside collections (DAG), circular ref detection, configurable depth, public share rendering |
| Post-7 (done) | OAuth 2.1 | PKCE (S256), scoped tokens, GitHub provider, discovery endpoints |
| Post-7 (done) | Streamable HTTP MCP | Protocol 2025-06-18, session via Mcp-Session-Id, 29 tools |
| Post-7 (done) | Result evaluation | LLM-powered scoring, versioned evaluations, composite scores, auto-evaluate |
| Post-7 (done) | Pipeline management | Pipeline CRUD via MCP, channel management |
| Post-7 (done) | Client-side execution | LLMs fetch prompts/pipelines, run natively, store results back |
| 8-9 (done) | React SPA migration | All pages ported from Livewire to React (Browse, Canvas, Workspace, Settings, Teams) |
| 10 (done) | Livewire cleanup | Removed all Livewire components, Blade views, Alpine modules. Converted tests to API tests |
