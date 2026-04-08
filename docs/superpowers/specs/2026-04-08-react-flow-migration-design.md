# URGE v2 — React Flow Frontend Migration Design

## Context

URGE v2's current frontend (Livewire 3 + Alpine.js) works well for CRUD-style interactions but doesn't expose the **compositional graph** that is URGE's core data model. Prompts include fragments via `{{>slug}}`, collections group items in a DAG, and the relationships between these entities are invisible in the current UI.

This migration replaces the Livewire frontend with a React SPA centered on a **React Flow canvas** — a node-graph visualization where prompts, fragments, and collections are draggable nodes, and composition relationships (`{{>slug}}` includes) are visible edges. The canvas becomes the primary navigation surface, with floating panels for search, properties, and actions.

The backend (Laravel 12, all models, services, API controllers, MCP server) is untouched except for additive changes (new endpoints, new migration, Sanctum auth).

---

## Migration Order (Canvas-First)

### Phase 1 — Graph Backend
- `graph_positions` migration + `GraphPosition` model
- `GraphController` with 5 new API endpoints
- Temporary `spa.auth` middleware (reuses `auth:web` session)
- SPA catch-all route at `/app/*`

### Phase 2 — React Scaffold + Canvas
- Install React, React Flow, Elk.js, React Query, Axios, react-router-dom
- Build full-canvas layout with floating panels
- PromptNode, FragmentNode, CollectionNode, CompositionEdge
- Node palette sidebar, properties panel, bottom toolbar
- Mermaid export, Elk.js auto-layout (free/tree/layered)

### Phase 3 — Sanctum + SPA Auth
- Install Laravel Sanctum (SPA cookie-based mode)
- Auth API endpoints (login, logout, user)
- LoginPage, useAuth hook, ProtectedRoute
- Replace temporary `spa.auth` with `auth:sanctum`

### Phase 4 — Workspace Page
- Port Editor, VersionSidebar, ResultsPanel to React
- Autocomplete, visual composer, diff viewer as React hooks
- Ctrl+S save, Ctrl+Enter run

### Phase 5 — Browse + Settings Pages
- Browse: tabbed list, search, scope/category/tag filters, collection management
- Settings: API keys, LLM providers, categories, pipeline templates, user management

### Phase 6 — Cleanup
- Remove Livewire components, Blade views, Alpine.js modules
- Remove InternalApiController, ProfileController
- Root `/` redirects to `/app/canvas`
- Remove livewire/livewire, alpinejs, sortablejs packages

**Transition strategy:** Parallel routes. Blade UI stays at existing URLs, React SPA at `/app/*`. Both work simultaneously until Phase 6.

---

## Backend Changes

### New Migration: `graph_positions`

```
graph_positions
├── id (primary)
├── user_id (FK → users.id, cascade delete)
├── node_type (string: 'prompt', 'fragment', 'collection')
├── node_id (unsigned bigint)
├── x (float)
├── y (float)
├── created_at, updated_at
└── UNIQUE(user_id, node_type, node_id)
```

### New Model: `GraphPosition`

- `fillable`: user_id, node_type, node_id, x, y
- `belongsTo(User)`
- Static `bulkUpsert(int $userId, array $positions)` — upsert on unique index

### New Controller: `GraphController`

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/v1/graph/nodes` | All visible prompts + fragments + collections with positions for current user. No pagination. Max 500 nodes (return most recently updated; include `total_count` and `truncated` flag in response). |
| POST | `/api/v1/graph/positions` | Bulk upsert `[{node_type, node_id, x, y}]` for current user |
| GET | `/api/v1/graph/edges` | Derived composition edges from `TemplateEngine::extractIncludes()` + collection→item edges from `CollectionItem` |
| POST | `/api/v1/prompts/{username}/{slug}/append-include` | Append `{{>slug}}` to active version content, create new version via `VersioningService` |
| DELETE | `/api/v1/prompts/{username}/{slug}/remove-include` | Remove `{{>slug}}` from content, create new version |

### Authentication Strategy

**Phase 1-2 (temporary):** Add `spa.auth` middleware alias → `auth:web`. The SPA Blade wrapper (`spa.blade.php`) initializes a Laravel session. Axios sends session cookie with `withCredentials: true`. Graph endpoints accept both `api.auth` (Bearer) and `spa.auth` (session).

**Phase 3 (permanent):** Install Sanctum. Add auth endpoints:
- `POST /api/v1/auth/login` — validate credentials, session cookie set by Sanctum
- `POST /api/v1/auth/logout` — destroy session
- `GET /api/v1/auth/user` — return authenticated user

Replace `spa.auth` with `auth:sanctum`. All SPA routes use Sanctum cookie auth. API/MCP routes continue using `api.auth` (Bearer token) unchanged.

**Sanctum config:**
- `stateful` domains: `localhost:5173` (Vite dev), production domain
- `cors.supports_credentials`: true
- `cors.allowed_origins`: localhost:5173, production domain

### Existing Services Reused

| Service | Usage in migration |
|---------|-------------------|
| `TemplateEngine::extractIncludes()` | Derive composition edges for graph |
| `VersioningService::createVersion()` | Create versions on append/remove include |
| `Prompt::visibleTo($user)` | Scope graph nodes to user's visibility |
| `ApiController` (base class) | `success()`, `error()`, `paginated()` response helpers |

### Files Modified (Backend)

| File | Change |
|------|--------|
| `routes/api.php` | Add graph endpoints, auth endpoints (Phase 3) |
| `routes/web.php` | Add `/app/{any?}` catch-all route |
| `bootstrap/app.php` | Add `spa.auth` middleware alias; add Sanctum middleware (Phase 3) |
| `config/sanctum.php` | New file (Phase 3) |
| `config/cors.php` | Update `supports_credentials`, `allowed_origins` (Phase 3) |
| `vite.config.js` | Add React plugin, new SPA entry point |
| `package.json` | Add React, React Flow, Elk.js, React Query, Axios, react-router-dom |

### Files Added (Backend)

| File | Purpose |
|------|---------|
| `database/migrations/YYYY_create_graph_positions_table.php` | Graph position storage |
| `app/Models/GraphPosition.php` | Graph position model |
| `app/Http/Controllers/Api/GraphController.php` | Graph API endpoints |
| `app/Http/Controllers/Api/AuthController.php` | SPA auth endpoints (Phase 3) |
| `resources/views/spa.blade.php` | Minimal Blade wrapper for React SPA |

---

## Frontend Architecture

### Canvas Layout: Full Canvas + Floating Panels

Canvas fills the entire viewport. Three floating overlay elements:

1. **Node Palette (left)** — toggleable (Ctrl+B), contains:
   - Search input (filters nodes on canvas)
   - Scope tabs: Mine / Shared / All
   - Draggable prompt list (category color bars, version badge)
   - Draggable fragment list (usage count badge)
   - Layout mode switcher: Free / Tree / Layer

2. **Properties Panel (right)** — appears on node selection, contains:
   - Editable: name, description, tags (add/remove), category (dropdown), visibility toggle
   - Read-only: type badge, version info, detected variables, detected includes
   - Include links: click highlights target node on canvas
   - Actions: "Open Editor" → `/app/workspace/{u}/{s}`, "Run LLM" (placeholder until Phase 4)
   - Save button appears on dirty state → PATCH API

3. **Bottom Toolbar (center)** — always visible, contains:
   - Layout mode toggle (Free / Tree / Layer)
   - Mermaid export (copies flowchart syntax to clipboard)
   - Fit View
   - New Prompt
   - Sidebar toggle

### Node Types

**PromptNode**
- Solid card, category-colored left border
- Title, type badge ("prompt"), namespace (owner/slug)
- Tag chips (max 3, "+N" overflow)
- Stats row: includes count, results count, variables count
- Source handle (bottom), target handle (top)
- Right-click: Edit, Delete, Remove from collection

**FragmentNode**
- Dashed blue border, darker background
- Title, type badge ("fragment"), namespace
- Content preview (first ~80 chars, monospace)
- "Used in N prompts" badge (derived from edge count)
- Source + target handles
- Right-click: Edit, Delete

**CollectionNode**
- Purple solid border, semi-transparent purple background
- Title, item count badge
- React Flow group node pattern — children render inside
- Expand/collapse toggle
- Supports nested collections (DAG)

**CompositionEdge**
- Animated dashed indigo line
- Label: "includes" or fragment slug
- Delete button (×) on hover → calls `remove-include` API

### React Directory Structure

```
resources/js/spa/
├── main.jsx              — ReactDOM.createRoot, QueryClientProvider, BrowserRouter
├── App.jsx               — Route definitions
├── api/
│   ├── client.js          — Axios instance (baseURL: /api/v1, withCredentials: true)
│   ├── auth.js            — login(), logout(), getUser()
│   ├── prompts.js         — CRUD wrappers
│   ├── graph.js           — getNodes(), getEdges(), savePositions(), appendInclude(), removeInclude()
│   └── collections.js     — CRUD wrappers
├── hooks/
│   ├── useAuth.js         — React Query hook for auth state
│   ├── useGraphData.js    — React Query hook combining nodes + edges
│   ├── useDebounce.js     — Debounce utility for position saves (300ms)
│   └── useMermaidExport.js — Serialize graph to mermaid flowchart syntax
├── components/
│   ├── Layout.jsx          — Top nav bar, floating panel wrappers
│   ├── ProtectedRoute.jsx  — Redirect to login if not authenticated
│   └── canvas/
│       ├── FlowCanvas.jsx  — React Flow wrapper, onNodesChange → debounced save
│       ├── nodes/
│       │   ├── PromptNode.jsx
│       │   ├── FragmentNode.jsx
│       │   └── CollectionNode.jsx
│       ├── edges/
│       │   └── CompositionEdge.jsx
│       ├── Sidebar.jsx      — Node palette float
│       ├── PropertiesPanel.jsx — Properties float
│       └── Toolbar.jsx       — Bottom toolbar float
├── pages/
│   ├── LoginPage.jsx
│   ├── CanvasPage.jsx       — Main graph view (Phase 2 deliverable)
│   ├── WorkspacePage.jsx    — Prompt editor (Phase 4)
│   ├── BrowsePage.jsx       — List view (Phase 5)
│   └── SettingsPage.jsx     — Settings (Phase 5)
└── styles/
    └── flow.css             — React Flow custom node + edge styles
```

### SPA Routes

| Path | Component | Auth | Phase |
|------|-----------|------|-------|
| `/app/login` | LoginPage | None | 3 |
| `/app/canvas` | CanvasPage | Required | 2 |
| `/app/workspace/:username/:slug` | WorkspacePage | Required | 4 |
| `/app/browse` | BrowsePage | Required | 5 |
| `/app/settings` | SettingsPage | Required | 5 |
| `/app/*` | Redirect → `/app/canvas` | — | 2 |

### Key Interactions

**Drag from palette to canvas:** Creates a `GraphPosition` for the node (it was already in the DB, just not on the canvas). Fires `savePositions()`.

**Drag node on canvas:** `onNodesChange` → debounced `savePositions()` (300ms). Positions persist per-user.

**Connect nodes (draw edge):** `onConnect` → if source is prompt and target is fragment, call `appendInclude(sourceSlug, fragmentSlug)`. Creates new version. React Query invalidates graph edges.

**Delete edge:** Click × on edge → `removeInclude(slug, fragmentSlug)`. Creates new version. React Query invalidates.

**Layout toggle:** Elk.js computes positions for all nodes using selected algorithm (mrtree for Tree, layered for Layer). Positions saved to backend.

**Mermaid export:** `useMermaidExport` hook serializes current nodes + edges:
- Prompts as rectangles: `A["Prompt Name"]`
- Fragments as rounded: `B("Fragment Name")`
- Collections as subgraphs
- Edges as labeled arrows

---

## Workspace Migration (Phase 4)

Port three Livewire components to React:

| Livewire Component | React Component | Key Logic |
|-------------------|-----------------|-----------|
| `Editor.php` + `editor.blade.php` | `Editor.jsx` | Textarea, autocomplete (port `autocomplete.js`), visual composer (port `composer.js`), Ctrl+S save, preview |
| `VersionSidebar.php` + `version-sidebar.blade.php` | `VersionSidebar.jsx` | Branch selector, version list, diff viewer (port `diff.js`), branch CRUD |
| `ResultsPanel.php` + `results-panel.blade.php` | `ResultsPanel.jsx` | Results list, star/rate, manual result form, run with LLM, compare |

Additional workspace sub-components:
- `RunWithLlm.jsx` — provider selection, variable fill, run
- `RunWithTemplate.jsx` — pipeline template selection, multi-channel run
- `ManualResultForm.jsx` — paste result with metadata
- `ImportResults.jsx` — file upload, parse .md, import
- `PromptMetadata.jsx` — modal for name, type, category, tags, description

All data via existing API endpoints. No new backend changes.

---

## Browse + Settings Migration (Phase 5)

### BrowsePage

- Tabbed: Prompts / Fragments / Starred / Collections
- Search, scope sidebar (Mine / Shared / Team), category + tag filters
- Prompt cards → click navigates to `/app/workspace/{u}/{s}`
- Collection CRUD with nested display
- Existing `GET /api/v1/prompts`, `GET /api/v1/collections` endpoints

### SettingsPage (tabbed)

- API Keys: list, create, revoke, scope
- LLM Providers: CRUD, test connection, driver selection
- Categories: CRUD with color picker
- Pipeline Templates: CRUD, channel management
- User Management: admin-only role assignment

---

## Cleanup (Phase 6)

### Remove

- `app/Livewire/*` (20 components)
- `resources/views/livewire/*`, `resources/views/layouts/*`, `resources/views/components/*`, `resources/views/auth/*`, `resources/views/profile/*`, `resources/views/dashboard.blade.php`
- `resources/js/autocomplete.js`, `composer.js`, `diff.js`, `app.js`, `bootstrap.js`
- `app/Http/Controllers/InternalApiController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/View/Components/*`

### Update

- `routes/web.php`: remove Livewire routes, root `/` → redirect `/app/canvas`
- `composer.json`: `composer remove livewire/livewire`
- `package.json`: `npm uninstall alpinejs sortablejs`
- `vite.config.js`: remove old Blade entry points

### Keep (untouched)

- All models (14), services (10+), API controllers (12+)
- MCP server (controller + artisan command)
- All migrations, config files
- All tests (update route references if needed)
- `routes/api.php`, `routes/auth.php`

---

## Verification Plan

| Phase | Gate |
|-------|------|
| 1 | `php artisan test` passes. `php artisan route:list` shows graph endpoints. GraphPosition migration runs clean. |
| 2 | Canvas renders real prompts/fragments as nodes. Edges drawn from includes. Drag nodes → positions persist on refresh. Elk.js layout works. Mermaid export copies valid syntax. Floating panels toggle. |
| 3 | `/app/login` works. Protected routes redirect unauthenticated users. Session cookie persists across pages. Sanctum CSRF flow works. |
| 4 | Workspace feature parity: save version (Ctrl+S), autocomplete variables/fragments, visual composer, diff two versions, run with LLM, manual result, star/rate, branch CRUD. |
| 5 | Browse: search, filter, create prompt, manage collections. Settings: all 5 tabs functional, test LLM connection works. |
| 6 | All Livewire removed. `php artisan test` passes. No 404s on any `/app/*` route. Root `/` redirects to `/app/canvas`. `composer.json` clean of livewire. |

---

## Risk Register

| Risk | Severity | Mitigation |
|------|----------|------------|
| React Flow performance with 500+ nodes | Low | React Flow has built-in virtualization; paginate by collection if needed |
| Edge derivation is O(n) per visible prompt | Low | Cache edges on version save; `extractIncludes()` is fast (regex) |
| Temporary `auth:web` → Sanctum migration | Low | Clean swap — same cookie mechanism, just formalized |
| Parallel Blade + React route confusion | Medium | Clear separation: existing URLs = Blade, `/app/*` = React |
| Workspace feature parity gap | Medium | Port Livewire 1:1; keep Blade as fallback until Phase 6 |
| Autocomplete/composer JS logic port | Low | Logic is pure JS (autocomplete.js, composer.js, diff.js) — straightforward React hook conversion |
