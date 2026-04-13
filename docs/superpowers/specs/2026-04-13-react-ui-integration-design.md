# React UI Integration Design

## Context

URGE v2 has two disconnected UIs: Livewire (browse, workspace, settings, teams) and React SPA (canvas, workspace). They share auth and API endpoints but have separate navigation with no cross-links. This design integrates them into a unified React-primary experience.

## Decision

React becomes the primary UI. Laravel remains the backend (API, MCP, auth). Livewire pages for Settings and Teams stay temporarily until migrated. Browse is migrated to React in this phase.

## Architecture

### Routing

The SPA catch-all at `/app/{any?}` becomes the primary UI entry point.

**React routes:**

| Route | Component | Notes |
|-------|-----------|-------|
| `/app/browse` | BrowsePage (new) | Default landing after login |
| `/app/canvas` | CanvasPage (existing) | Graph visualization |
| `/app/workspace/:username/:slug` | WorkspacePage (existing) | 3-panel editor |
| `/app/login` | LoginPage (existing) | Sanctum auth |
| `*` | Redirect to `/browse` | Catch-all |

**Settings and Teams:** Sidebar links navigate to Livewire `/settings` and `/teams` via full page load. These will be migrated to React in a future phase.

**Livewire routes remain:** `/browse`, `/prompts/{username}/{slug}`, `/settings`, `/teams` stay functional as fallback. No deletions.

### Navigation: Slim Sidebar (Icon Rail)

Always-visible sidebar, ~56px wide, left edge. Present on all React pages including workspace.

**Contents (top to bottom):**

- **U** — brand mark, links to `/app/browse`
- **Browse** — grid/list icon
- **Canvas** — nodes/graph icon
- *(spacer)*
- **Settings** — gear icon, navigates to Livewire `/settings` (full page load)
- **Teams** — people icon, navigates to Livewire `/teams` (full page load)
- **User** — avatar/initial + logout (bottom)

**Active state:** Active page icon gets indigo background + white icon. Others are gray.

**Internal links:** All use React Router `<Link>` for SPA transitions. Settings and Teams are the only exceptions (full page loads).

**Mobile:** Sidebar collapses to a bottom tab bar on small screens. Same icons, horizontal layout.

### Browse Page

Full port of Livewire Browse into React at `/app/browse`.

**Layout:** Inline tabs + filter bar + content grid (no secondary sidebar).

**Tabs:** Prompts | Fragments | Collections | Starred — horizontal row at top.

**Filter bar:** Search input, category dropdown, tag pills (removable). Filters update via React Query against existing API.

**Prompt cards:** Name, type badge, category color dot, tag chips, version count, result count. Click → `/app/workspace/:username/:slug`.

**Collections tab:** Collection list with expand/collapse for nested items. Click collection item → workspace.

**Starred tab:** Starred results across all prompts. Shows prompt name, provider, model, rating.

**"+ New" button:** Create prompt inline — name input + type selector (prompt/fragment). Creates via API, navigates to workspace.

**Data source:** Existing API endpoints only:
- `GET /api/v1/prompts` — with `type`, `category`, `tag`, `search`, `scope` params
- `GET /api/v1/collections` — for collections tab

No new backend endpoints needed.

### Migration Cleanup

**Login redirect:** Laravel auth redirects to `/app/browse` after login (was `/browse`).

**Root redirects:** `/` and `/dashboard` redirect to `/app/browse` (were `/browse`).

**React defaults:** SPA catch-all redirects to `/browse` (was `/canvas`). Login success redirects to `/app/browse` (was `/app/canvas`).

## Files to Create

| File | Purpose |
|------|---------|
| `resources/js/spa/components/Sidebar.jsx` | Icon rail navigation component |
| `resources/js/spa/pages/BrowsePage.jsx` | Full rewrite — tabbed browse with filters |
| `resources/js/spa/components/browse/PromptCard.jsx` | Prompt card component |
| `resources/js/spa/components/browse/CollectionList.jsx` | Collection expand/collapse |
| `resources/js/spa/components/browse/CreatePromptForm.jsx` | Inline new prompt form |
| `resources/js/spa/api/collections.js` | Collection API wrapper |
| `resources/js/spa/api/categories.js` | Category API wrapper (for filter dropdown) |

## Files to Modify

| File | Change |
|------|--------|
| `resources/js/spa/components/Layout.jsx` | Replace top nav bar with Sidebar |
| `resources/js/spa/App.jsx` | Update default redirect from `/canvas` to `/browse` |
| `resources/js/spa/pages/LoginPage.jsx` | Redirect to `/app/browse` on success |
| `routes/web.php` | Change `/` and `/dashboard` redirects to `/app/browse` |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Change post-login redirect |

## Verification

1. After login → lands on `/app/browse`
2. Sidebar visible on all pages, active state correct
3. Browse tabs switch between prompts/fragments/collections/starred
4. Filters (search, category, tags) work and update grid
5. Click prompt card → workspace opens with sidebar still visible
6. Canvas accessible from sidebar
7. Settings/Teams links go to Livewire pages
8. Back to browse from workspace via sidebar
9. Mobile: sidebar becomes bottom tab bar
10. `php artisan test` — 351+ tests pass
11. `npm run build` — clean build
