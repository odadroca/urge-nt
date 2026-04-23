# URGE v2 — Claude Code Prompts for React Flow Migration

> **Usage:** Run these prompts sequentially in Claude Code from the `urge-nt` project root.
> Each prompt is self-contained with explicit scope boundaries.
> Mode labels follow the project convention: BUILD, PATCH, REVIEW.

---

## Prompt 0: Project Context Bootstrap

```
Mode: CONTEXT-ONLY

You are working on URGE v2, a Laravel 11 prompt registry with:
- Backend: Laravel + SQLite, session auth (web), Bearer token auth (API/MCP)
- Current frontend: Livewire 3 + Alpine.js + Tailwind CSS + Vite
- Migration target: React SPA + React Flow (node-graph canvas)

Key backend files:
- app/Models/Prompt.php — core entity, type: prompt|fragment, {{>slug}} includes fragments
- app/Services/TemplateEngine.php — resolves {{var}} and {{>slug}} includes recursively
- app/Services/McpToolHandler.php — 15 MCP tools, untouchable
- app/Http/Controllers/Api/ — 47 REST endpoints, Bearer auth via ApiKeyAuthentication middleware
- routes/api.php — all API routes under /api/v1/

The migration is additive. Existing Blade/Livewire UI stays working until explicit removal.
The React SPA will live alongside Blade during migration, served from a catch-all route.

Read and acknowledge the following files before proceeding:
- documentation/architecture.md
- routes/api.php
- routes/web.php
- app/Models/Prompt.php
- app/Services/TemplateEngine.php
- package.json
- vite.config.js
```

---

## Prompt 1: Install Sanctum + SPA Auth

```
Mode: BUILD
Scope: Backend auth only. Do not touch any Livewire components, Blade views, or existing API routes.

1. Install Laravel Sanctum: composer require laravel/sanctum
2. Publish Sanctum config: php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
3. Add Sanctum's middleware to the api middleware group in bootstrap/app.php:
   - Add \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class to the 'api' group
   - Do NOT remove the existing 'api.auth' alias
4. Configure config/sanctum.php:
   - Set stateful domains to include localhost:5173 (Vite dev) and the production domain
5. Configure config/cors.php:
   - supports_credentials: true
   - allowed_origins: localhost:5173 and production domain
6. Create API auth endpoints (new file app/Http/Controllers/Api/AuthController.php):
   - POST /api/v1/auth/login — validate credentials, return user JSON (session cookie set by Sanctum)
   - POST /api/v1/auth/logout — logout
   - GET /api/v1/auth/user — return authenticated user (for SPA session check)
7. Add these routes to routes/api.php inside the v1 prefix, OUTSIDE the api.auth middleware group
8. Add a new middleware alias 'spa.auth' pointing to 'auth:sanctum'
9. Add a SECOND middleware group in routes/api.php using 'spa.auth' for SPA-specific routes (empty for now)

Constraints:
- The existing api.auth (ApiKeyAuthentication) middleware must continue working unchanged for all current routes
- Session-based web auth must continue working for Livewire
- Do not modify any existing controller
- Do not modify any existing route — only add new ones

Test: After changes, verify `php artisan route:list` shows new auth routes and all existing routes unchanged.
```

---

## Prompt 2: Graph Positions Migration + Model

```
Mode: BUILD
Scope: One migration file + one model. No controllers, no routes.

Create migration: database/migrations/YYYY_MM_DD_000001_create_graph_positions_table.php

Schema:
- id (primary)
- user_id (foreign → users.id, on delete cascade)
- node_type (string: 'prompt', 'fragment', 'collection')
- node_id (unsigned bigint)
- x (float)
- y (float)
- created_at, updated_at
- Unique index on (user_id, node_type, node_id)

Create model: app/Models/GraphPosition.php
- fillable: user_id, node_type, node_id, x, y
- Relationship: belongsTo User
- Static method: bulkUpsert(int $userId, array $positions) — accepts [{node_type, node_id, x, y}], uses upsert on the unique index

Do not run the migration. Do not create controllers or routes.
```

---

## Prompt 3: Graph API Controller

```
Mode: BUILD
Scope: One controller + route additions. No frontend changes.

Create: app/Http/Controllers/Api/GraphController.php

Endpoints (all require spa.auth OR api.auth — support both):

1. GET /api/v1/graph/nodes
   - Returns all prompts + fragments visible to user (via Prompt::visibleTo) with:
     - id, slug, name, type, description, tags, category (name + color), owner (username)
     - active_version content (for include extraction)
     - position: {x, y} from GraphPosition for current user (null if no position saved)
   - Also returns collections visible to user with positions
   - Paginate: no (graph needs all nodes at once). Limit: 500 nodes max.

2. POST /api/v1/graph/positions
   - Body: { positions: [{node_type, node_id, x, y}, ...] }
   - Calls GraphPosition::bulkUpsert for current user
   - Returns: { saved: count }

3. GET /api/v1/graph/edges
   - For each visible prompt, extract includes via TemplateEngine::extractIncludes()
   - Return: [{source_type, source_slug, target_slug, type: 'composition'}]
   - Also return collection→item edges from CollectionItem table

4. POST /api/v1/prompts/{username}/{slug}/append-include
   - Body: { fragment_slug: string }
   - Appends "\n{{>fragment_slug}}" to the prompt's active version content
   - Creates a new version via VersioningService with commit_message: "Added include: fragment_slug"
   - Returns the new version data

5. DELETE /api/v1/prompts/{username}/{slug}/remove-include
   - Body: { fragment_slug: string }
   - Removes all occurrences of "{{>fragment_slug}}" from active version content
   - Creates new version via VersioningService
   - Returns new version data

Add routes to routes/api.php. Support BOTH middlewares:
- Create a new middleware group that accepts either api.auth OR spa.auth (use middleware array)

Inject TemplateEngine and VersioningService via constructor.
Follow the existing controller patterns in Api/PromptController.php.
```

---

## Prompt 4: React SPA Scaffold

```
Mode: BUILD
Scope: Frontend scaffold only. Do not modify any PHP files except vite.config.js and one new catch-all route.

1. Install React + dependencies:
   npm install react react-dom @types/react @types/react-dom
   npm install @xyflow/react elkjs
   npm install @tanstack/react-query axios
   npm install -D @vitejs/plugin-react

2. Update vite.config.js:
   - Add React plugin
   - Keep existing laravel plugin config (Blade still needs it)
   - Add new entry point: resources/js/spa/main.jsx

3. Create the SPA entry point structure:
   resources/js/spa/
   ├── main.jsx              — ReactDOM.createRoot, QueryClientProvider, BrowserRouter
   ├── App.jsx               — Route definitions (react-router-dom)
   ├── api/
   │   ├── client.js          — Axios instance with baseURL '/api/v1', withCredentials: true
   │   ├── auth.js            — login(), logout(), getUser()
   │   ├── prompts.js         — CRUD wrappers
   │   ├── graph.js           — getNodes(), getEdges(), savePositions(), appendInclude(), removeInclude()
   │   └── collections.js     — CRUD wrappers
   ├── hooks/
   │   ├── useAuth.js         — React Query hook for auth state
   │   ├── useGraphData.js    — React Query hook combining nodes + edges
   │   └── useDebounce.js     — Debounce utility for position saves
   ├── components/
   │   ├── Layout.jsx          — Shell: top nav, sidebar, canvas area
   │   ├── ProtectedRoute.jsx  — Redirect to login if not authenticated
   │   └── canvas/
   │       ├── FlowCanvas.jsx  — React Flow wrapper (main component)
   │       ├── nodes/
   │       │   ├── PromptNode.jsx    — Custom node: title, tags, Run/Edit buttons
   │       │   ├── FragmentNode.jsx  — Custom node: title, reuse count, content preview
   │       │   └── CollectionNode.jsx — Group node container
   │       └── edges/
   │           └── CompositionEdge.jsx — Labeled edge with arrow
   ├── pages/
   │   ├── LoginPage.jsx
   │   ├── CanvasPage.jsx      — Main graph view
   │   ├── WorkspacePage.jsx   — Placeholder (prompt editor — Phase 4)
   │   ├── BrowsePage.jsx      — Placeholder (list view — Phase 5)
   │   └── SettingsPage.jsx    — Placeholder (Phase 5)
   └── styles/
       └── flow.css            — React Flow custom node styles

4. Create a Blade wrapper view: resources/views/spa.blade.php
   - Minimal HTML: loads Vite React entry point
   - No Livewire, no Alpine

5. Add catch-all route in routes/web.php (AFTER all existing routes):
   Route::get('/app/{any?}', fn () => view('spa'))->where('any', '.*')->middleware('auth');

   This serves the React SPA at /app/* while keeping all Blade routes working.

6. The FlowCanvas.jsx should:
   - Fetch nodes and edges via useGraphData hook
   - Map nodes to custom node types (PromptNode, FragmentNode, CollectionNode)
   - Handle onNodesChange for drag → debounced position save
   - Handle onConnect for new edge → calls appendInclude API
   - Support Elk.js auto-layout toggle (free-form vs tree vs layered)

Do NOT implement the full editor, results panel, or settings yet — just placeholders.
The canvas page is the deliverable for this phase.
```

---

## Prompt 5: Canvas Node Components

```
Mode: BUILD
Scope: React components in resources/js/spa/components/canvas/ only.

Implement the three custom node types for React Flow:

### PromptNode.jsx
- Displays: name (title), type badge ("prompt"), tag chips (max 3), category color dot
- Action buttons: "Open" (navigates to /app/workspace/{username}/{slug}), "Run" (placeholder)
- Source handle (bottom) — can connect TO fragments
- Target handle (top) — can receive connections FROM other prompts
- Visual: card with shadow, white bg, left border colored by category
- Shows include count badge if prompt has {{>...}} includes

### FragmentNode.jsx
- Displays: name (title), type badge ("fragment"), first 80 chars of content preview
- Badge: "Used in N prompts" (from edge count)
- Source handle (bottom) — can connect to other fragments (nested includes)
- Target handle (top) — receives connections from prompts
- Visual: card with dashed border, slightly muted colors

### CollectionNode.jsx
- Uses React Flow's GroupNode pattern
- Displays: collection title, item count
- Child nodes (prompts/fragments in the collection) render inside
- Collapsible: toggle to hide/show children

All nodes must:
- Accept `data` prop with the full node payload from the API
- Use Tailwind classes (the SPA shares the same Tailwind config)
- Handle selection state (highlight border on click)
- Show a mini context menu on right-click: "Edit", "Delete", "Remove from collection" (where applicable)

### CompositionEdge.jsx
- Animated dashed line
- Label showing "includes" or the fragment slug
- Delete button on hover (calls removeInclude API)
```

---

## Prompt 6: Sidebar + Properties Panel

```
Mode: BUILD
Scope: React components in resources/js/spa/components/ only.

### Sidebar.jsx (left panel, toggleable)
- Search input (filters nodes on canvas by name)
- Node palette: draggable list of all prompts and fragments
  - Grouped by: "My Prompts", "My Fragments", "Shared", per-team sections
  - Each item is draggable (React Flow's drag-to-canvas pattern)
  - Shows name + type icon
- Layout controls:
  - Toggle: Free-form / Mindmap (tree) / Workflow (layered)
  - Calls Elk.js layout engine on toggle
- Mermaid export button (serializes current graph to mermaid flowchart syntax, copies to clipboard)

### PropertiesPanel.jsx (right panel, shows on node selection)
- Displays full metadata of selected node:
  - Name (editable inline)
  - Type (read-only)
  - Description (editable)
  - Tags (editable, comma-separated)
  - Category (dropdown)
  - Visibility (private/shared)
  - Version info: current version number, branch, last updated
  - Content preview (read-only, first 500 chars)
  - Detected variables list
  - Detected includes list (clickable → highlights target node on canvas)
- "Open in Editor" button → navigates to /app/workspace/{username}/{slug}
- "Run with LLM" button → placeholder for Phase 4
- Save button → PATCH /api/v1/prompts/{username}/{slug}

### useMermaidExport.js hook
- Takes current React Flow nodes + edges
- Outputs mermaid flowchart LR syntax:
  - Prompt nodes as rectangles: A["Prompt Name"]
  - Fragment nodes as rounded: B("Fragment Name")
  - Collection nodes as subgraphs
  - Composition edges as arrows with labels
- Returns { mermaidString, copyToClipboard() }
```

---

## Prompt 7: SPA Auth Flow

```
Mode: BUILD
Scope: resources/js/spa/pages/LoginPage.jsx + resources/js/spa/hooks/useAuth.js + ProtectedRoute.jsx

### LoginPage.jsx
- Simple login form: email + password
- Calls GET /sanctum/csrf-cookie first, then POST /api/v1/auth/login
- On success: redirect to /app/canvas
- On error: show error message
- Styled with Tailwind, matches existing URGE dark theme

### useAuth.js
- React Query hook that calls GET /api/v1/auth/user
- Returns: { user, isLoading, isAuthenticated, login, logout }
- login: calls csrf-cookie + login endpoint, then invalidates user query
- logout: calls logout endpoint, invalidates user query, redirects to /app/login

### ProtectedRoute.jsx
- Wraps routes that require auth
- If useAuth returns not authenticated → redirect to /app/login
- If loading → show spinner
- Otherwise render children

### App.jsx (update)
- /app/login → LoginPage
- /app/canvas → ProtectedRoute → CanvasPage
- /app/workspace/:username/:slug → ProtectedRoute → WorkspacePage (placeholder)
- /app/browse → ProtectedRoute → BrowsePage (placeholder)
- /app/settings → ProtectedRoute → SettingsPage (placeholder)
- /app/* catch-all → redirect to /app/canvas
```

---

## Prompt 8: Workspace Page (Editor Migration)

```
Mode: BUILD
Scope: resources/js/spa/pages/WorkspacePage.jsx and sub-components.

Replicate the Livewire workspace functionality in React:

### WorkspacePage.jsx
- 3-panel layout matching current workspace-page.blade.php
- URL: /app/workspace/:username/:slug
- Fetches prompt data via React Query

### Editor.jsx (center panel)
- Textarea with syntax highlighting for {{var}} and {{>slug}} patterns
- Autocomplete dropdown for variables and fragments (port autocomplete.js logic)
- Visual/text mode toggle (port composer.js block editor)
- Ctrl+S to save new version (POST /api/v1/prompts/{u}/{s}/versions)
- Commit message input
- Preview toggle (renders via POST /api/v1/prompts/{u}/{s}/render)
- Variable metadata editor (type, default, description per variable)
- Dirty state indicator

### VersionSidebar.jsx (left panel)
- Branch selector dropdown
- Version list filtered by current branch
- Click to load version content into editor
- Diff button between two selected versions (port diff.js logic)
- Create branch button
- Set default branch button

### ResultsPanel.jsx (right panel)
- Results list for current prompt (GET /api/v1/prompts/{u}/{s}/results)
- Star/unstar toggle
- Rating (1-5)
- Manual result form (paste with provider/model/notes)
- "Run with LLM" button (POST to run endpoint)
- "Run with Template" (pipeline template selection + run)

Follow the exact same feature set as the current Livewire components.
Port all Alpine.js logic to React hooks/state.
```

---

## Prompt 9: Browse + Settings Pages

```
Mode: BUILD
Scope: resources/js/spa/pages/BrowsePage.jsx, SettingsPage.jsx and sub-components.

### BrowsePage.jsx
- Tabbed view: Prompts | Fragments | Starred | Collections
- Search input with URL query sync
- Scope sidebar: Mine | All | per-team filters
- Category filter chips
- Tag filter chips
- Prompt cards with: name, description preview, version count, result count, category badge, tags
- Click → navigate to /app/workspace/{username}/{slug}
- Create new prompt/fragment button + modal
- Collection management (CRUD, add items, nested display, share links)
- Matches current browse.blade.php feature set exactly

### SettingsPage.jsx (tabbed)
- API Keys: list, create (show once), revoke, scope to prompts
- LLM Providers: CRUD, test connection, driver selection, toggle active
- Categories: CRUD with color picker
- Pipeline Templates: CRUD, channel management
- User Management (admin only): role assignment

These are standard CRUD pages. Use React Query for data fetching.
Use the same API endpoints already available.
Add any missing API endpoints as needed (create them in Laravel).
```

---

## Prompt 10: Cleanup — Remove Livewire/Blade

```
Mode: BUILD
Scope: Removal of old frontend. Only execute after all React pages are verified working.

1. Remove all Livewire components: app/Livewire/* (20 files)
2. Remove all Blade views: resources/views/livewire/*, resources/views/layouts/*, resources/views/components/*, resources/views/auth/*, resources/views/profile/*, resources/views/dashboard.blade.php
3. Remove Alpine.js modules: resources/js/autocomplete.js, composer.js, diff.js, app.js, bootstrap.js
4. Remove InternalApiController.php (replaced by API equivalents)
5. Remove ProfileController.php (replaced by SPA)
6. Update routes/web.php:
   - Remove all Livewire route references
   - Keep auth routes (for Sanctum CSRF)
   - Keep share route (public, no auth)
   - Change root '/' to redirect to '/app/canvas'
   - Keep the SPA catch-all
7. Remove Livewire from composer.json: composer remove livewire/livewire
8. Remove Alpine.js from package.json: npm uninstall alpinejs
9. Remove SortableJS: npm uninstall sortablejs (React Flow handles drag)
10. Update vite.config.js to remove old entry points
11. Run: composer dump-autoload && npm run build
12. Verify: php artisan route:list — only API routes + SPA catch-all + auth + share

Do NOT remove:
- Any model, service, or API controller
- MCP controller or artisan commands
- Tests (update test routes if needed)
- config files
- migrations
```

---

## Execution Notes

- **Run prompts 0-3 first** (backend foundation). Verify with tests before touching frontend.
- **Prompts 4-7** can be worked in parallel (React scaffold + canvas + auth are independent).
- **Prompt 8** depends on 4+7 (SPA scaffold + auth must exist).
- **Prompt 9** depends on 8 (workspace must work first).
- **Prompt 10** is the final step — only after full regression test.

Each prompt creates or modifies files only within its stated scope.
If Claude Code needs to touch something outside scope, it should stop and explain.
