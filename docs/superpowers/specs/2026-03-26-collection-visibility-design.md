# Collection Visibility Improvements

## Problem

The "add to collection" actions are nearly invisible:
- **Version sidebar**: tiny `+` button only appears on hover (`opacity-0 group-hover:opacity-100`)
- **Results panel**: plain text link "+ Collection" buried in a row of small action links
- **Browse page**: no way to add prompts to collections at all — must open workspace first
- **No bulk actions**: adding multiple items requires clicking one at a time

## Solution

Three changes across three surfaces, plus a new bulk selection mode on Browse.

### Icon

Use the Heroicons "archive" SVG icon consistently across all surfaces:
- **Outline variant** (`stroke`, no fill): default/light theme contexts
- **Solid variant** (`fill`): dark theme or mini sizes where outline doesn't read well

```html
<!-- Outline (24x24) -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
  <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
</svg>

<!-- Solid (20x20) -->
<svg viewBox="0 0 20 20" fill="currentColor">
  <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/>
</svg>
```

### Change 1: Version Sidebar (always-visible icon)

**File**: `resources/views/livewire/workspace/version-sidebar.blade.php`

Replace the hidden hover `+` button with an always-visible icon button on each version row:
- Remove `opacity-0 group-hover:opacity-100` classes
- Replace the `+` text with the archive SVG icon (w-3.5 h-3.5)
- Style: subtle background (`bg-gray-50 dark:bg-gray-800`), indigo icon color (`text-indigo-500 dark:text-indigo-400`), rounded (`rounded`)
- Clicking opens the same collection picker dropdown (existing behavior preserved)
- Only render when `$collections->count() > 0` (existing guard preserved)

### Change 2: Results Panel (styled button)

**File**: `resources/views/livewire/workspace/results-panel.blade.php`

Replace the plain text "+ Collection" link with a styled pill button:
- Replace: `<button ... class="text-xs text-gray-400 ...">+ Collection</button>`
- With: a button styled as `inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded hover:bg-indigo-100 dark:hover:bg-indigo-800/30`
- Include archive SVG icon (w-3 h-3) + "Collect" text
- Clicking opens the same collection picker dropdown (existing behavior preserved)
- When no collections exist, show "No collections yet" in the dropdown (existing behavior)

### Change 3: Browse Page (new collection button + bulk selection)

**Files**: `app/Livewire/Browse.php`, `resources/views/livewire/browse.blade.php`

#### 3a. Single-item "Collect" button on prompt cards

Add a "Collect" button to each prompt card in the Browse grid:
- Position: bottom-right of the card, inside the existing `<a>` tag but with `@click.prevent.stop` to prevent navigation
- Style: same as results panel pill button (indigo, with archive icon + "Collect" text)
- Click opens a collection picker dropdown (same pattern as version sidebar/results panel)
- **Semantics**: adds the prompt's **active version** (pinned or latest) to the selected collection
- Backend: new `addPromptToCollection(int $promptId, int $collectionId)` method on `Browse.php` that resolves the active version and calls `CollectionItem::firstOrCreate()`
- **Edge case**: if a prompt has no versions (just created, no content saved yet), the "Collect" button is hidden (guard: `$prompt->latestVersion` exists). In bulk mode, prompts without versions are silently skipped and excluded from the count.

#### 3b. Bulk selection mode

Add a "Select" toggle button to the Browse tab toolbar (next to the search/filter area):
- Default state: hidden/off — prompt cards behave as normal navigable links
- Toggled on: enters selection mode

**Selection mode UI:**
- Each prompt card shows a checkbox (top-right corner)
- Clicking a card toggles its selection (does NOT navigate)
- Selected cards get `ring-2 ring-indigo-300 dark:ring-indigo-600` border highlight
- An indigo floating action bar appears above the grid:
  - Left: "N selected" count + "Add to Collection" button (opens collection picker)
  - Right: "Select All" button + "Cancel" button (exits selection mode)

**Selection mode state** (Alpine.js, client-side):
- `selectMode: false` — toggle for selection mode
- `selectedPromptIds: []` — array of selected prompt IDs

**Backend**: new `addPromptsToCollection(array $promptIds, int $collectionId)` method on `Browse.php`:
- Loads each prompt, resolves active version, calls `CollectionItem::firstOrCreate()` in a loop
- Dispatches a success notification with count
- Resets selection state via `$this->dispatch('selection-cleared')`

**Data passed to view**: `collections` — `Collection::orderBy('title')->get(['id', 'title'])` (same query used in workspace components)

### Collection Picker Dropdown

All three surfaces use the same dropdown pattern:
- Lists all collections by title
- Clicking a collection adds the item and closes the dropdown
- Shows "No collections yet" empty state
- `@click.outside="open = false"` to dismiss
- Positioned contextually (below button on sidebar/cards, above button on results)

No new Livewire component needed — each surface handles its own dropdown inline (matching existing pattern in version-sidebar and results-panel).

### What's NOT changing

- Collection model/schema — no migrations needed
- `CollectionItem` model — existing polymorphic structure supports this
- `CollectionList` component — untouched
- API/MCP endpoints — no changes
- Version sidebar core logic (`addVersionToCollection`) — method stays the same
- Results panel core logic (`addResultToCollection`) — method stays the same

## Verification

1. **Manual testing:**
   - Open workspace → verify archive icon visible on every version row
   - Click icon → verify collection picker dropdown opens and works
   - Verify results panel "Collect" button is styled and visible
   - Navigate to Browse → verify "Collect" button on each prompt card
   - Click "Collect" on Browse → verify active version is added to collection
   - Toggle "Select" mode → verify checkboxes appear
   - Select multiple prompts → verify floating bar with count
   - Click "Add to Collection" → verify all selected prompts' active versions are added
   - Verify collection tab shows the newly added items

2. **Existing tests:**
   - `php artisan test` — all 307 tests must still pass (no breaking changes)

3. **New tests:**
   - Test `Browse::addPromptToCollection()` — adds active version to collection
   - Test `Browse::addPromptsToCollection()` — bulk adds active versions
   - Test that prompt without versions is handled gracefully (no-op or notification)
