# URGE v2 Architecture

## Vision

URGE is a **prompt registry, version control, and result archive** with two access patterns:
- **Human access** via a Livewire 3 web UI (workspace-centric, minimal navigation)
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

### Five Surfaces, One Backend

```
┌──────────────────────────────────────────────────────────┐
│                      Laravel App                          │
│                                                           │
│  ┌──────────┐  ┌──────────┐  ┌─────────┐  ┌──────────┐  │
│  │ Livewire │  │ REST API │  │ MCP SSE │  │MCP stdio │  │
│  │ Web UI   │  │ /api/v1/ │  │ /mcp    │  │ artisan  │  │
│  └────┬─────┘  └────┬─────┘  └────┬────┘  └────┬─────┘  │
│       │              │             │             │        │
│       └──────────────┼─────────────┴─────────────┘        │
│                      v                                    │
│            ┌──────────────────┐                           │
│            │  Service Layer   │                           │
│            │  TemplateEngine  │                           │
│            │  VersioningSvc   │                           │
│            │  McpToolHandler  │                           │
│            │  ApiKeySvc       │                           │
│            │  LlmDispatchSvc  │                           │
│            │  AiAssistantSvc  │                           │
│            │  ImportExportSvc │                           │
│            └────────┬─────────┘                           │
│                     v                                     │
│            ┌──────────────────┐                           │
│            │  Eloquent/SQLite │                           │
│            └──────────────────┘                           │
└──────────────────────────────────────────────────────────┘

External consumers:
  Browser (human)  ──> Livewire Web UI
  Any HTTP client  ──> REST API
  Claude Desktop   ──> MCP SSE (remote) or MCP stdio (local)
  CustomGPT        ──> REST API (via OpenAPI spec)
```

### REST API (`/api/v1/`)

Bearer token auth via `ApiKeyAuthentication` middleware. Rate limited per key.

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
  GET    /results/{id}                         — get single
  PATCH  /results/{id}                         — update rating/starred/notes
  DELETE /results/{id}                         — delete result

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

### MCP Server (dual transport)

Two transports, one shared handler layer:

**SSE transport (primary, for hosted/remote URGE):**
- HTTP endpoint at `/mcp`, authenticated via Bearer token (same API keys)
- Use case: Claude Desktop on your laptop connects to URGE on Hostinger
- Runs within the Laravel HTTP server — no extra process needed
- SSE (Server-Sent Events) for server→client streaming, POST for client→server

**stdio transport (secondary, for local dev):**
- Artisan command: `php artisan urge:mcp-server`
- Use case: Claude Code / Claude Desktop on the same machine as URGE
- Reads JSON-RPC from stdin, writes to stdout

Both transports dispatch to the same `McpToolHandler` service, which maps tool calls to TemplateEngine, VersioningService, and Eloquent queries.

**Tools:**
| Tool | Purpose |
|---|---|
| `get_prompt` | Fetch prompt by slug (+ optional owner for namespace), optionally specific version |
| `list_prompts` | Browse/search the registry (scope: mine/shared/team/all) |
| `render_prompt` | Resolve includes + fill variables → rendered text |
| `save_version` | Create new version of a prompt |
| `store_result` | Archive a result (response from any LLM) |
| `get_results` | Retrieve past results for a prompt |
| `update_result` | Update result metadata (rating, starred, notes) |
| `delete_result` | Delete a result |
| `delete_prompt` | Delete prompt (owner/admin only) |
| `share_prompt` | Share prompt with a team |
| `list_teams` | List user's teams |
| `list_branches` | List branches for a prompt |
| `create_branch` | Create a new branch |

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

Used by the Editor's inline autocomplete (Alpine.js) to suggest variable names and fragment includes.

### CustomGPT Actions

OpenAPI 3.0 spec generated from the REST API. Hosted at `/api/openapi.json`. GPT custom actions import this spec directly.

### Claude Skill

Markdown file with instructions + API examples. Tells Claude how to call the URGE API with curl/fetch for prompt retrieval and result storage.

## Component Architecture

### Web UI (Livewire)

```
app/Livewire/
├── Dashboard.php              # Recent prompts, starred results, inline create
├── Browse.php                 # Tabbed: prompts, fragments, starred, collections + namespace sidebar├── Settings.php               # Tabbed settings container
├── Teams.php                  # Teams list + create├── TeamDetail.php             # Team members + shared prompts├── Browse/
│   └── CollectionList.php     # Collection CRUD, expand/collapse, reorder items, nest collections, share links
├── Settings/
│   ├── ApiKeys.php            # API key CRUD, reveal once, scope to prompts
│   ├── LlmProviders.php      # LLM provider CRUD, test connection, toggle active
│   ├── Categories.php         # Category CRUD with color picker
│   └── UserManagement.php     # Admin-only user role management
└── Workspace/
    ├── WorkspacePage.php      # 3-panel orchestrator
    ├── Editor.php             # Content editing, live preview, visual composer, Ctrl+S
    ├── VersionSidebar.php     # Branch switcher, version list (filtered by branch), create/delete/set-default branch, diff
    ├── ResultsPanel.php       # Results list, star, rate, compare, AI summarize
    ├── ManualResultForm.php   # Paste result with provider/model/notes/rating
    ├── ImportResults.php      # Upload .md files, preview frontmatter, import
    ├── RunWithLlm.php         # LLM execution: provider selection, variable fill, run
    └── PromptMetadata.php     # Name, type, category, tags, description
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
| Post-7 | Version branching | Non-linear version history with PromptBranch, branch CRUD, MCP/API support |
| Post-7 | Nested collections | Collections inside collections (DAG), circular ref detection, configurable depth, public share rendering |
