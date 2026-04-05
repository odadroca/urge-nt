# URGE v2 — Continuation Prompts

Copy the relevant phase section into a new Claude Code session to continue development.

---

## Phase 2: API Layer + MCP Server

```
You are continuing development of URGE v2, a Laravel 12 + Livewire 3 prompt management system. Phase 1 is complete (core workspace UI). Read CLAUDE.md for full context.

Implement Phase 2 — REST API + MCP Server. URGE becomes a prompt registry that LLMs can consume.

### 2A. API Key Infrastructure

Create migration for api_keys:
- api_keys: id, name, user_id (FK), key_hash (string, indexed), key_preview (string 8 chars), last_used_at (nullable), expires_at (nullable), is_active (bool), timestamps
- api_key_prompt: api_key_id (FK), prompt_id (FK) — scope keys to specific prompts

Create ApiKey model with relationships.

Create app/Services/ApiKeyService.php:
- generateKey(): returns ['key' => 'urge_...', 'model' => ApiKey]. Generate 31 random bytes, prepend 'urge_', store SHA-256 hash, store first 8 chars as preview.
- findByToken(string $token): ?ApiKey — hash token, lookup by key_hash, check is_active and expires_at.

### 2B. API Authentication Middleware

Create app/Http/Middleware/ApiKeyAuthentication.php:
- Extract Bearer token from Authorization header
- Look up via ApiKeyService::findByToken()
- Update last_used_at
- Check rate limit: config('urge.api_rate_limit') requests per config('urge.api_rate_window') seconds, tracked per key in cache
- Abort 401 if invalid, 429 if rate limited
- Attach api_key and user to request

Register in bootstrap/app.php as alias 'api.auth'.

Update config/urge.php: add key_prefix ('urge_'), key_bytes (31), key_preview_length (8), api_rate_limit (60), api_rate_window (60).

### 2C. API Controllers

Create app/Http/Controllers/Api/ApiController.php:
- Base controller with helpers: success($data, $status), error($message, $status), paginated($query, $request)

Create app/Http/Controllers/Api/PromptController.php:
- index(Request): list prompts. Filter by: type, category_id, tag, search (name/description). Paginate.
- store(Request): create prompt. Validate name (required), type (optional, default 'prompt'), description, category_id, tags.
- show(Prompt $prompt): return prompt with active version, version count, result count.
- update(Request, Prompt $prompt): update metadata (name, description, type, category_id, tags).

Create app/Http/Controllers/Api/VersionController.php:
- index(Prompt $prompt): list all versions for a prompt.
- store(Request, Prompt $prompt): create version. Validate content (required), commit_message (optional), variable_metadata (optional JSON). Use VersioningService.
- show(Prompt $prompt, int $versionNumber): get specific version by number.

Create app/Http/Controllers/Api/RenderController.php:
- render(Request, Prompt $prompt): POST. Accept: version (optional, default active), variables (key-value object). Call TemplateEngine::render(). Return rendered text + metadata (variables_used, variables_missing, includes_resolved).

Create app/Http/Controllers/Api/ResultController.php:
- index(Request, Prompt $prompt): list results. Filter by: version (number), starred (bool). Paginate.
- store(Request, Prompt $prompt): create result. Validate: version (required, version number), response_text (required), source (default 'api'), provider_name, model_name, notes, rating (1-5), starred (bool), rendered_content, variables_used.
- show(Result $result): get single result.
- update(Request, Result $result): update rating, starred, notes only.

Create app/Http/Controllers/Api/HealthController.php:
- __invoke(): return {status: 'ok', version: '2.0', timestamp: now()}.

### 2D. API Routes

In routes/api.php:
```php
Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    Route::middleware('api.auth')->group(function () {
        Route::apiResource('prompts', PromptController::class)->except('destroy');
        Route::get('prompts/{prompt:slug}/versions', [VersionController::class, 'index']);
        Route::post('prompts/{prompt:slug}/versions', [VersionController::class, 'store']);
        Route::get('prompts/{prompt:slug}/versions/{version}', [VersionController::class, 'show']);
        Route::post('prompts/{prompt:slug}/render', [RenderController::class, 'render']);
        Route::get('prompts/{prompt:slug}/results', [ResultController::class, 'index']);
        Route::post('prompts/{prompt:slug}/results', [ResultController::class, 'store']);
        Route::get('results/{result}', [ResultController::class, 'show']);
        Route::patch('results/{result}', [ResultController::class, 'update']);
    });
});
```

### 2E. OpenAPI Spec

Create public/openapi.json — OpenAPI 3.0 spec documenting all endpoints above. Include:
- Server URL (configurable via APP_URL)
- Bearer auth security scheme
- All request/response schemas
- This spec must be importable as a CustomGPT Action

Add a route to serve it: GET /api/openapi.json.

### 2F. MCP Tool Handler (shared logic)

Create app/Services/McpToolHandler.php:
- Central dispatcher that maps MCP tool names to service layer calls
- No transport awareness — takes a tool name + arguments, returns a result array
- This is shared by both SSE and stdio transports

Tool implementations (each a method on McpToolHandler):
1. get_prompt(slug, version?): calls Prompt::where('slug', $slug), returns content + metadata
2. list_prompts(type?, category?, tag?, search?): queries prompts, returns slug + name + type + version count
3. render_prompt(slug, version?, variables{}): calls TemplateEngine::render(), returns rendered text
4. save_version(slug, content, commit_message?): calls VersioningService::createVersion()
5. store_result(slug, version, response_text, provider?, model?, notes?): creates Result with source='mcp'
6. get_results(slug, version?, starred?, limit?): queries results, returns list

Resource implementations:
- urge://prompts — list of prompts as JSON
- urge://prompts/{slug} — prompt with active version content as text
- urge://prompts/{slug}/v/{n} — specific version content as text

Also implement: getToolDefinitions(), getResourceDefinitions(), getServerInfo() — return MCP protocol metadata.

### 2G. MCP SSE Transport (primary — for hosted/remote URGE)

Create app/Http/Controllers/McpController.php:
- POST /mcp — receives JSON-RPC requests, dispatches to McpToolHandler, returns JSON-RPC responses
- GET /mcp — SSE endpoint for server-to-client streaming (MCP protocol requires this for SSE transport)
- Authenticated via the same ApiKeyAuthentication middleware (Bearer token)
- Register routes in routes/api.php (inside the api.auth middleware group)

This is the primary transport because URGE runs on Hostinger (remote). Users connect Claude Desktop from their local machine to the hosted URGE instance over HTTP.

### 2H. MCP stdio Transport (secondary — for local dev)

Create app/Console/Commands/McpServerCommand.php:
- Artisan command: urge:mcp-server
- Reads JSON-RPC from STDIN line by line, dispatches to McpToolHandler, writes JSON-RPC to STDOUT
- Same handler, different I/O — while(true) loop with fgets(STDIN)
- Use case: Claude Code or Claude Desktop running on the same machine as URGE

No external MCP SDK dependency — the protocol is simple JSON-RPC 2.0 with well-defined method names (initialize, tools/list, tools/call, resources/list, resources/read).

### 2I. Claude Skill Document

Create documentation/claude-skill.md:
- Markdown instructions a user can paste into Claude Projects or custom instructions
- Explains how Claude can use the URGE API (with curl examples)
- Lists available endpoints with example requests/responses
- Includes a "quick start" section: how to get an API key, how to fetch and render a prompt

### 2J. API Key Settings UI

Create app/Livewire/Settings/ApiKeys.php and view:
- List all API keys (name, preview, last_used, active status)
- Create form: name input, optional prompt scoping (multi-select), generate button
- Show generated key ONCE in a modal (never retrievable again)
- Toggle active, delete with confirmation

Update Settings.php to render ApiKeys as a tab.

### 2K. Tests

Create tests for:
- ApiKeyService: generation, hashing, lookup, expiry
- ApiKeyAuthentication middleware: valid token, invalid token, rate limiting, expired key
- All API endpoints: CRUD prompts, versions, results, render
- McpToolHandler: all 6 tools with mock data (unit test the handler directly)
- MCP SSE controller: POST /mcp with valid tool calls
- MCP stdio command: mock stdin/stdout
- Prompt scoping: key with scoped prompts can only access those prompts

Run php artisan test — all tests must pass.
```

---

## Phase 3: Rich Editing + Comparison

```
You are continuing development of URGE v2, a Laravel 12 + Livewire 3 prompt management system. Phases 1-2 are complete. Read CLAUDE.md for full context.

Implement Phase 3 — Rich Editing + Comparison.

### 3A. Inline Autocomplete

Create resources/js/autocomplete.js — Alpine.js component for the Editor textarea:
- Detect `{{` → show dropdown of known variable names (fetch from API: GET /api/v1/internal/variables)
- Detect `{{>` → show dropdown of fragment slugs (fetch from API: GET /api/v1/internal/fragments)
- Arrow keys to navigate, Enter/Tab to insert, Escape to dismiss
- Position dropdown below cursor

Add two internal-only API routes (no auth, web middleware only):
- GET /api/internal/variables — return all unique variable names from prompt_versions
- GET /api/internal/fragments — return all prompts where type='fragment' (slug + name)

Wire into Editor.blade.php with x-data="autocomplete()".

### 3B. Variable Metadata Editor

Create app/Livewire/Workspace/VariableMetadata.php and view:
- Shows detected variables from Editor (listen to events)
- Per variable: type dropdown (string/text/enum/number/boolean), default value, description
- Enum type: comma-separated options
- Data flows to Editor's save flow as variable_metadata

Add below Editor in workspace-page.blade.php.

### 3C. Visual Composer

Create app/Livewire/Workspace/VisualComposer.php and view + resources/js/composer.js:
- Toggle "Text"/"Visual" in Editor toolbar
- Visual mode: content as draggable blocks (text, variable chips, include chips)
- SortableJS for drag-and-drop (npm install sortablejs)
- Add variable/include via picker buttons
- Serialize back to template string when switching to Text mode
- Sync with Editor via Livewire events

### 3D. Version Diff

Create resources/js/diff.js:
- npm install diff
- Alpine component rendering side-by-side diff (green additions, red deletions)

Add to VersionSidebar: Shift+click second version → open diff modal.

### 3E. Compare Modal

Create app/Livewire/Workspace/CompareModal.php and view:
- Checkboxes on result cards in ResultsPanel
- "Compare Selected" button (2-4 results)
- Full-width modal with side-by-side columns
- Header per column: provider, model, rating

### Testing

Test autocomplete endpoints, variable metadata save/load, compare modal rendering. All tests pass.
```

---

## Phase 4: Import/Export + Collections

```
You are continuing development of URGE v2, a Laravel 12 + Livewire 3 prompt management system. Phases 1-3 are complete. Read CLAUDE.md for full context.

Implement Phase 4 — Import/Export + Collections.

### 4A. ImportExportService

Create app/Services/ImportExportService.php:
- exportPromptVersion(PromptVersion): markdown with YAML frontmatter (prompt slug, version, variables, includes)
- exportResult(Result): markdown with frontmatter (prompt, version, provider, model, rating, starred)
- exportCollection(Collection): narrative markdown with all items
- parseMarkdownWithFrontmatter(string): returns ['meta' => [...], 'body' => '...'] using Symfony YAML
- importResult(string $content, PromptVersion $version, User $user): parses frontmatter, creates Result with source='import'

### 4B. Import/Export UI

Create app/Livewire/Workspace/ImportResults.php — file upload with WithFileUploads, parse .md files, attach to current version.

Add to ResultsPanel: per-result "Export .md" button, "Export All" button (zip).
Add to Editor toolbar: "Export Prompt" button, "Copy Rendered" button (TemplateEngine::render with empty vars, copy to clipboard).

Use Livewire's streamDownload() for file downloads.

### 4C. Collections

Create migrations: collections + collection_items tables.
Create models: Collection, CollectionItem (polymorphic relationships).

Create app/Livewire/Browse/CollectionList.php — CRUD collections, add items from workspace (button on version sidebar + results panel), reorder via SortableJS.

Add "Collections" tab to Browse. Add "Starred Results" tab to Browse.

### 4D. Enhanced Browse

Update Browse: category filter dropdown, tag filter chips, result count per prompt (withCount).

### Testing

Test ImportExportService round-trip, Collection CRUD, file upload. All tests pass.
```

---

## Phase 5: LLM Drivers + AI Features + v1 Migration + Polish

```
You are continuing development of URGE v2, a Laravel 12 + Livewire 3 prompt management system. Phases 1-4 are complete. Read CLAUDE.md for full context.

Implement Phase 5 — LLM drivers, AI features, v1 data migration, and production polish.

### 5A. Port LLM Drivers

Copy from v1 (at /home/user/urge or ask user for path), adapt namespaces:
- LlmDriverInterface (add completeWithSystem method)
- LlmResult
- OpenAiDriver, AnthropicDriver, MistralDriver, GeminiDriver, OllamaDriver, OpenRouterDriver
- LlmDispatchService

### 5B. LLM Provider Settings

Create app/Livewire/Settings/LlmProviders.php — CRUD providers, test connection button. Add as Settings tab.

### 5C. RunWithLlm Component

Create app/Livewire/Workspace/RunWithLlm.php — provider selection checkboxes, variable fill form, "Run" button dispatches to providers, creates Results with source='api'. Add to workspace via Editor toolbar button.

### 5D. AiAssistantService

Create app/Services/AiAssistantService.php:
- summarizeDifferences(textA, textB, provider): meta-prompt for diff analysis
- suggestImprovements(promptContent, provider): meta-prompt for prompt refinement
Add buttons to CompareModal and Editor toolbar.

### 5E. v1 Data Migration

Create app/Console/Commands/ImportV1Command.php — artisan urge:import-v1 {path}:
- Read v1 SQLite (read-only), map all tables to v2 schema in a transaction
- Mapping: users, categories, prompts, versions (direct), prompt_runs+llm_responses→results (source='api'), library_entries→results (source='manual', starred=true, deduplicate), stories→collections, story_steps→collection_items, api_keys+pivot
- Idempotent (firstOrCreate), logs actions, never modifies v1 database
- Requires same APP_KEY for encrypted LLM provider keys

### 5F. Settings Tabs

Create app/Livewire/Settings/UserManagement.php — list users, change roles, delete (admin only).
Create app/Livewire/Settings/Categories.php — CRUD categories with color picker.

### 5G. Polish

- Responsive layout (panels stack on mobile)
- Keyboard shortcuts (Ctrl+S save, Ctrl+Enter run) via Alpine
- Loading states (wire:loading) on all server-action buttons
- Flash messages for success/error
- Cache optimization (eager loading, query review)
- composer dev script

### Testing

Test LLM dispatch with mock driver, API auth, ImportV1Command with fixture DB, role-based access. All tests pass.
```

---

---

## Phase 7: User Namespaces & Teams (COMPLETE)

Phase 7 is fully implemented (sprints N1-N8). 307 tests passing.

Key features added:
- User namespaces: prompts scoped to `{username}/{slug}` URLs
- Private by default with team-based sharing
- Teams management (create, invite, share prompts)
- All surfaces updated: Web UI, REST API, MCP, openapi.json
- Legacy URLs redirect to namespaced versions

See `docs/namespaces-sprints.md` for the full sprint plan and `docs/superpowers/specs/2026-03-25-user-namespaces-design.md` for the design spec.

---

## Post-Phase 7: Version Branching (COMPLETE)

Non-linear version history with `PromptBranch` model. 307 tests passing.

Key features added:
- Named branches per prompt (auto-created `main` on first version)
- Independent branch version numbers (display: `main#3 (v7)`)
- Fork from any version, set default branch, delete branches
- MCP tools: `list_branches`, `create_branch`
- API: full branch CRUD under `/prompts/{username}/{slug}/branches`
- UI: branch switcher in VersionSidebar

---

## Post-Phase 7: Nested Collections (COMPLETE)

Collections inside collections for compositional storytelling. 307 tests passing.

Key features added:
- `collection` as third polymorphic type in `CollectionItem` (no new tables)
- DAG structure: same collection can appear in multiple parents
- Circular reference detection (BFS ancestor walk) via `CollectionNestingService`
- Configurable depth: `max_collection_depth` (default 5) with `unlimited_collection_depth` toggle
- API: `addItem` accepts `item_type=collection`, `show` returns recursive structure
- Livewire: "Nest" action on collection cards, nested collections in expanded view
- Public share view: nested collections render as chapters with recursive `story-item.blade.php` partial

See `docs/superpowers/specs/2026-03-27-nested-collections-design.md` for the design spec.

---

## Usage

1. Copy the phase section you need
2. Start a new Claude Code session in the urge-v2 project directory
3. Paste the prompt
4. Claude reads CLAUDE.md automatically for project context
5. For Phase 7: individual sprint prompts are in `docs/namespaces-sprints.md`
