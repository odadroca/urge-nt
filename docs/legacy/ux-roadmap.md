# URGE v2 — UX/UI Improvement Roadmap

> Generated from a full UI/UX audit on 2026-03-25.
> Each sprint is self-contained with a ready-to-use prompt for Claude Code.
> Status: [ ] = todo, [~] = in progress, [x] = done

---

## Sprint 1 — Merge Dashboard into Browse (home page consolidation)

**Status:** [x]

**Problem:** Dashboard and Browse are two separate pages doing similar things. Dashboard shows recent prompts + stats + starred results but isn't filterable. Browse has tabs, filters, tags, and search but no stats or quick-create. Users always navigate away from Dashboard to do real work.

**Goal:** Make Browse the home page (`/`). Add a compact "quick start" header to Browse with the create-prompt form and condensed stats. Remove the standalone Dashboard page.

**Files to modify:**
- `routes/web.php` — change `/` redirect from `dashboard` to `browse`
- `app/Livewire/Browse.php` — add stats computation + create-prompt logic (merge from Dashboard.php)
- `resources/views/livewire/browse.blade.php` — add compact header with inline create + mini stats
- `resources/views/layouts/app.blade.php` — update nav links (remove Dashboard, make Browse the home link)
- `app/Livewire/Dashboard.php` — keep file but deprecate (or delete if clean)
- `resources/views/livewire/dashboard.blade.php` — remove or archive

**Prompt:**
```
I'm working on URGE v2 (Laravel 12 / Livewire 3 / Alpine.js / Tailwind v3).

Read docs/ux-roadmap.md for full context, then implement Sprint 1: Merge Dashboard into Browse.

The goal is to make Browse the home page. Add a compact quick-start header to Browse with:
- Inline create-prompt form (name + type + create button, same as current dashboard)
- Condensed stats row (just prompts/fragments/results counts as small badges, not 6 big cards)
- "Continue editing: [last prompt name]" quick link if the user has recent prompts

Then update routes to redirect `/` to Browse, update the nav to remove "Dashboard" and make Browse the home link. Keep the existing Browse tabs (Prompts, Fragments, Collections, Starred) and all filtering intact.

Key files: routes/web.php, app/Livewire/Browse.php, app/Livewire/Dashboard.php, resources/views/livewire/browse.blade.php, resources/views/livewire/dashboard.blade.php, resources/views/layouts/app.blade.php

After implementing, commit with a descriptive message and push.
```

---

## Sprint 2 — Inline category creation from metadata dropdown

**Status:** [x]

**Problem:** Categories are locked behind admin-only Settings page. Editors assigning categories in the workspace metadata panel can only pick existing ones — they can't create new categories without asking an admin.

**Goal:** Add a "create category" option at the bottom of the category dropdown in the workspace metadata panel. Allow editors (not just admins) to create categories inline.

**Files to modify:**
- `app/Livewire/Workspace/PromptMetadata.php` — add `createCategory(name, color)` method with editor role check
- `resources/views/livewire/workspace/prompt-metadata.blade.php` — replace `<select>` with a custom Alpine dropdown that has a "+" create option at the bottom, including a mini color picker
- `app/Models/Category.php` — reference for available colors and `getColorHexAttribute()`

**Prompt:**
```
I'm working on URGE v2 (Laravel 12 / Livewire 3 / Alpine.js / Tailwind v3).

Read docs/ux-roadmap.md for full context, then implement Sprint 2: Inline category creation.

In the workspace metadata panel (PromptMetadata component), replace the plain category <select> with a custom Alpine dropdown that:
1. Lists existing categories with their color dots (same as current)
2. Has a "No category" option at the top
3. Has a "+ New category" option at the bottom that expands inline to show:
   - Name input
   - Color picker (same circular color buttons as Settings > Categories)
   - Create button
4. After creation, auto-selects the new category

Add a `createCategory(string $name, string $color)` method to PromptMetadata.php.
Allow users with editor role or above (use `auth()->user()->isEditor()` check).
Dispatch a notify toast on success.

Key files: app/Livewire/Workspace/PromptMetadata.php, resources/views/livewire/workspace/prompt-metadata.blade.php, app/Models/Category.php

After implementing, commit with a descriptive message and push.
```

---

## Sprint 3 — Workspace toolbar cleanup for mobile

**Status:** [x]

**Problem:** The editor toolbar packs 10+ elements (prompt name, version badge, dirty indicator, Text/Visual toggle, Preview, Export, Copy Rendered, Run LLM, AI Suggest, commit input, Save). On mobile this wraps unpredictably or overflows.

**Goal:** Reorganize the toolbar into primary actions (always visible) and secondary actions (in an overflow "..." menu on mobile). Keep all actions accessible but reduce visual clutter.

**Files to modify:**
- `resources/views/livewire/workspace/editor.blade.php` — restructure toolbar into rows/groups with responsive visibility

**Prompt:**
```
I'm working on URGE v2 (Laravel 12 / Livewire 3 / Alpine.js / Tailwind v3).

Read docs/ux-roadmap.md for full context, then implement Sprint 3: Workspace toolbar cleanup.

Reorganize the editor toolbar in editor.blade.php:

**Always visible (all screens):**
- Prompt name (truncated on mobile)
- Unsaved indicator (amber dot)
- Save Version button
- Commit message input (compact)

**Visible on desktop, hidden on mobile behind overflow menu:**
- Text/Visual mode toggle
- Preview toggle
- Export prompt
- Copy Rendered
- Run LLM
- AI Suggest

Add an Alpine-powered overflow menu button (three dots "..." icon) that is `lg:hidden` and contains the secondary actions as a dropdown. On desktop (`hidden lg:flex`), show all actions inline as they are now.

Keep keyboard shortcuts (Ctrl+S, Ctrl+Enter) working regardless of toolbar visibility.
Do NOT change any Livewire methods or PHP — this is a pure template restructure.

Key file: resources/views/livewire/workspace/editor.blade.php

After implementing, commit with a descriptive message and push.
```

---

## Sprint 4 — "Continue editing" quick link + last-prompt memory

**Status:** [x]

**Problem:** The most common workflow is "return to app → continue working on last prompt." Currently requires scanning the recent prompts grid. Should be instant.

**Goal:** Add a "Continue editing: [prompt name]" link in the nav bar that takes the user directly to their last-edited prompt.

**Files to modify:**
- `resources/views/layouts/app.blade.php` — add the continue-editing link in nav
- `app/Models/User.php` or middleware — track last-edited prompt (could use session or a `last_prompt_id` column)
- `app/Livewire/Workspace/Editor.php` — update session/user when a version is saved

**Prompt:**
```
I'm working on URGE v2 (Laravel 12 / Livewire 3 / Alpine.js / Tailwind v3).

Read docs/ux-roadmap.md for full context, then implement Sprint 4: Continue editing quick link.

Track the user's last-edited prompt using a session value (avoid schema changes).
When a user saves a version in Editor.php, store `session(['last_prompt_id' => $this->prompt->id])`.

In the app layout nav bar (layouts/app.blade.php), after the existing nav links, add a subtle "Continue: [prompt name]" link that:
- Only shows if session has `last_prompt_id` and the prompt still exists
- Links to route('workspace', $prompt)
- Styled as muted/secondary text (not a primary nav item)
- Truncates long names to ~20 chars
- Shows on both desktop and mobile nav

Key files: resources/views/layouts/app.blade.php, app/Livewire/Workspace/Editor.php

After implementing, commit with a descriptive message and push.
```

---

## Sprint 5 — Tag autocomplete from existing tags

**Status:** [x]

**Problem:** Tags are typed as comma-separated freeform strings. No autocomplete, no suggestions from existing tags. Easy to create duplicates ("ai" vs "AI" vs "A.I.").

**Goal:** Add tag autocomplete to the tags input in the workspace metadata panel. Show suggestions from all existing tags across all prompts as the user types.

**Files to modify:**
- `app/Livewire/Workspace/PromptMetadata.php` — add computed property or method to fetch all distinct tags
- `resources/views/livewire/workspace/prompt-metadata.blade.php` — replace plain text input with Alpine autocomplete dropdown
- Possibly `app/Models/Prompt.php` — add a static method to get all unique tags

**Prompt:**
```
I'm working on URGE v2 (Laravel 12 / Livewire 3 / Alpine.js / Tailwind v3).

Read docs/ux-roadmap.md for full context, then implement Sprint 5: Tag autocomplete.

Add tag autocomplete to the metadata panel's tags input:

1. In PromptMetadata.php, pass all existing unique tags to the view:
   - Query all prompts, pluck 'tags' (JSON array column), flatten, unique, sort
   - Pass as `$allTags` to the blade view

2. In the blade template, replace the plain tags input with an Alpine component that:
   - Shows a text input for typing
   - Displays existing tags as removable pills/chips above the input
   - As the user types after the last comma (or in an empty field), shows a filtered dropdown of matching existing tags
   - Clicking a suggestion adds it as a tag
   - Case-insensitive matching
   - Normalizes tags to lowercase on save

3. In PromptMetadata::save(), normalize tags to lowercase and deduplicate.

Keep the comma-separated wire:model approach for Livewire state, but enhance the UX layer with Alpine.

Key files: app/Livewire/Workspace/PromptMetadata.php, resources/views/livewire/workspace/prompt-metadata.blade.php

After implementing, commit with a descriptive message and push.
```

---

## Sprint 6 — First-time onboarding empty state

**Status:** [x]

**Problem:** A fresh install shows empty grids with minimal guidance. New users don't understand the tool's workflow (create prompt → add variables → run with LLM → compare results).

**Goal:** Add a helpful getting-started section that appears when the user has zero prompts. Show a step-by-step workflow guide that disappears once the first prompt is created.

**Files to modify:**
- `resources/views/livewire/browse.blade.php` (or dashboard, depending on Sprint 1 completion) — add onboarding section
- Possibly a new partial: `resources/views/components/onboarding.blade.php`

**Prompt:**
```
I'm working on URGE v2 (Laravel 12 / Livewire 3 / Alpine.js / Tailwind v3).

Read docs/ux-roadmap.md for full context, then implement Sprint 6: First-time onboarding.

Note: If Sprint 1 was completed, the home page is Browse. If not, add this to Dashboard.

When the user has zero prompts, instead of showing empty grids, show a focused onboarding section:

1. A clean welcome card with:
   - App name and one-line description ("Your prompt engineering workspace")
   - 4-step visual workflow: Create Prompt → Add Variables → Run with LLM → Compare Results
   - Each step as a small card with an icon/emoji, title, and one-line description
   - "Create your first prompt" CTA button that opens the create form

2. Style: use a subtle gradient or border treatment to make it feel welcoming but not childish.
   Keep it consistent with the existing Tailwind design system (indigo accents, gray backgrounds).

3. The onboarding section should ONLY show when the user has 0 prompts. Once they create one, normal content takes over.

Do NOT add a "dismiss" button — it auto-dismisses when content exists.

Key files: resources/views/livewire/browse.blade.php (or dashboard.blade.php)

After implementing, commit with a descriptive message and push.
```

---

## Sprint 7 — Settings page access for non-admins (read-only providers)

**Status:** [x]

**Problem:** Non-admin users can't see the Settings page at all, but they need awareness of available LLM providers (shown when running prompts). Categories are also hidden (partially addressed by Sprint 2).

**Goal:** Allow editors/viewers to see a read-only view of LLM providers. Keep API keys and user management admin-only. Make the Settings nav link visible to all authenticated users but limit tabs by role.

**Files to modify:**
- `app/Livewire/Settings.php` — remove admin gate for the page, add per-tab role checks
- `resources/views/livewire/settings.blade.php` — conditionally show tabs based on role
- `resources/views/layouts/app.blade.php` — show Settings link for all users, not just admins
- `routes/web.php` — adjust middleware if needed

**Prompt:**
```
I'm working on URGE v2 (Laravel 12 / Livewire 3 / Alpine.js / Tailwind v3).

Read docs/ux-roadmap.md for full context, then implement Sprint 7: Settings access for non-admins.

Make the Settings page accessible to all authenticated users, but with role-based tab visibility:

**All users see:**
- LLM Providers tab (read-only for non-admins — show the list but hide create/edit/delete/test buttons)
- Categories tab (read-only for viewers, editable for editors+)

**Editors also see:**
- Categories tab with full create/edit/delete capability

**Admins see everything:**
- All tabs including API Keys and Users

Changes:
1. In Settings.php, remove the admin-only gate. Add a `$visibleTabs` computed property based on role.
2. In settings.blade.php, conditionally render tab buttons and wrap destructive actions in role checks.
3. In llm-providers.blade.php, wrap create/edit/delete/test in `@if(auth()->user()->isAdmin())`.
4. In categories.blade.php, wrap create/edit/delete in `@if(auth()->user()->isEditor())`.
5. In app.blade.php, show the Settings nav link for all authenticated users (remove admin check).

Key files: app/Livewire/Settings.php, resources/views/livewire/settings.blade.php, resources/views/livewire/settings/llm-providers.blade.php, resources/views/livewire/settings/categories.blade.php, resources/views/layouts/app.blade.php

After implementing, commit with a descriptive message and push.
```

---

## Sprint 8 — Visual polish pass

**Status:** [x]

**Problem:** The app uses Figtree (a clean but generic font) and a standard indigo/gray Tailwind palette throughout. It's functional but doesn't have a distinctive visual identity for a developer tool.

**Goal:** Refine typography, spacing, and micro-interactions for a more polished, professional feel without a full redesign. Add subtle personality touches.

**Prompt:**
```
I'm working on URGE v2 (Laravel 12 / Livewire 3 / Alpine.js / Tailwind v3).

Read docs/ux-roadmap.md for full context, then implement Sprint 8: Visual polish pass.

Apply targeted visual refinements across the app:

1. **Typography**: Replace Figtree with JetBrains Mono for code/prompt content areas (textarea, pre, monospace displays) and keep a clean sans-serif (e.g., "Plus Jakarta Sans" from Google Fonts) for UI chrome. Update tailwind.config.js font families and the Google Fonts link in app.blade.php layout.

2. **Transitions**: Add `transition-all duration-150` to all interactive cards (prompt cards in browse, result cards). Add subtle `hover:translate-y-[-1px]` lift effect on cards.

3. **Active states**: In the workspace, make the current mode (Text/Visual) toggle use a pill slider style instead of two separate buttons. Use Alpine for the sliding indicator.

4. **Spacing consistency**: Audit and normalize padding in sidebar panels — ensure version-sidebar and results-panel use consistent p-3 internally.

5. **Toast notifications**: Add a slide-in animation (transform from right) instead of instant appear.

Keep changes surgical — don't break any existing functionality. This is cosmetic only.

Key files: tailwind.config.js, resources/views/layouts/app.blade.php, resources/views/livewire/browse.blade.php, resources/views/livewire/workspace/editor.blade.php, resources/css/app.css

After implementing, commit with a descriptive message and push.
```

---

## Dependency Map

```
Sprint 1 (Dashboard → Browse merge)
  └─ Sprint 6 depends on this (onboarding location)

Sprint 2 (Inline categories) — independent
Sprint 3 (Toolbar cleanup) — independent
Sprint 4 (Continue editing) — independent
Sprint 5 (Tag autocomplete) — independent
Sprint 7 (Settings access) — independent
Sprint 8 (Visual polish) — do last, after layout changes settle
```

**Recommended order:** 1 → 2 → 3 → 4 → 5 → 7 → 6 → 8

Sprints 2-5 and 7 are independent and can be done in any order or in parallel.
Sprint 6 should come after Sprint 1 (needs to know the home page).
Sprint 8 should come last (polish after structural changes).

---

## Phase 7 — User Namespaces & Teams (COMPLETE)

All UX sprints and Phase 7 namespace sprints (N1-N8) are complete. 307 tests passing.

See:
- [`superpowers/specs/2026-03-25-user-namespaces-design.md`](superpowers/specs/2026-03-25-user-namespaces-design.md) — Design spec
- [`namespaces-sprints.md`](namespaces-sprints.md) — Sprint plan (all 8 sprints done)

---

## Post-Phase 7 — Version Branching & Nested Collections (COMPLETE)

Two major features added after Phase 7:

**Version branching:** Non-linear version history with `PromptBranch`. Named branches per prompt, independent version numbering, fork from any version, branch switcher UI, MCP tools, API endpoints.

**Nested collections:** Collections inside collections (DAG structure). Circular reference detection, configurable depth limit, recursive API/share view rendering. Foundation for future mind-map/graph visualization.

See:
- [`superpowers/specs/2026-03-27-nested-collections-design.md`](superpowers/specs/2026-03-27-nested-collections-design.md) — Nested collections design spec
