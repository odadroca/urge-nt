# Workspace Feature Sprints: Diff Viewer, Autocomplete, Visual Composer

> **Status: DELIVERED.** All three shipped — `DiffViewer.jsx`,
> `AutocompleteDropdown.jsx`, `VisualComposer.jsx` under
> `resources/js/spa/components/workspace/`. Kept for history.

## Context

Three workspace features from the Livewire era were not ported during the React migration. They enhance the editor experience but were not migration blockers. Now it's time to bring them back.

Current editor: plain `<textarea>` in `Editor.jsx` (147 lines) with regex-based variable/include detection, Ctrl+S save, and preview toggle. No syntax highlighting, no autocomplete, no diff, no drag-drop.

## Sprint 1: Version Diff Viewer

**Scope:** Compare any two versions of a prompt side-by-side with color-coded additions/deletions.

**Why first:** Most requested feature (user explicitly asked about it), self-contained, no changes to existing editor behavior.

### Files to create
- `resources/js/spa/components/workspace/DiffViewer.jsx` — modal with unified diff display, word/char mode toggle, stats

### Files to modify
- `resources/js/spa/components/workspace/VersionSidebar.jsx` — add "Compare" mode: user clicks a compare button, then selects two versions. Show "Diff" button when two are selected.
- `package.json` — `npm install diff`

### Implementation
1. Add `diff` npm package (was previously installed, removed during cleanup)
2. VersionSidebar gets a "Compare" toggle button in the header. When active, version items show checkboxes. Selecting exactly 2 enables a "Show Diff" button.
3. DiffViewer renders as a modal/overlay:
   - Header: `v3 (main#2) → v5 (main#4)` with version labels
   - Mode toggle: word-level (default) / char-level
   - Unified diff view with colored spans (green = added, red = removed+strikethrough)
   - Stats bar: `+X words / -Y words`
   - Close button
4. Fetch both version contents via existing `GET /api/v1/prompts/{u}/{s}/versions/{n}` endpoint
5. Use `diffWords()` / `diffChars()` from the `diff` package

### Verification
- Select two versions → diff modal shows correct additions/deletions
- Toggle word/char mode → diff updates
- Close modal → back to normal sidebar
- Works with versions on different branches

---

## Sprint 2: Autocomplete Dropdown

**Scope:** Inline autocomplete in the editor textarea for `{{variable}}` and `{{>fragment}}` patterns.

**Why second:** Directly improves the editing experience, builds on the existing textarea without replacing it.

### Files to create
- `resources/js/spa/components/workspace/AutocompleteDropdown.jsx` — positioned dropdown that appears below the cursor when typing `{{` or `{{>`
- `resources/js/spa/hooks/useAutocomplete.js` — hook managing trigger detection, filtering, keyboard navigation, and insertion

### Files to modify
- `resources/js/spa/components/workspace/Editor.jsx` — integrate the autocomplete hook with the textarea ref, pass onInput/onKeyDown handlers

### Implementation
1. **Trigger detection:** Monitor textarea input. When cursor is inside `{{` → variable mode. Inside `{{>` → fragment mode.
2. **Data fetching:** Fetch variables from `GET /internal/variables` and fragments from `GET /internal/fragments`. Cache with React Query (stale time ~60s).
3. **Filtering:** Match typed query against available items (case-insensitive substring).
4. **Dropdown positioning:** Calculate position from textarea's `selectionStart`, line height, scroll offset. Render absolutely positioned below cursor.
5. **Keyboard navigation:** ArrowUp/Down to navigate, Enter/Tab to insert, Escape to dismiss.
6. **Insertion:** Replace text from trigger start to cursor with completed `{{variable}}` or `{{>fragment_slug}}`. Update textarea value and cursor position.
7. **Dismiss:** Close on blur, escape, or when trigger pattern is broken.

### Verification
- Type `{{` → dropdown shows known variables
- Type `{{>` → dropdown shows fragment slugs
- Arrow keys navigate, Enter/Tab inserts
- Dropdown positioned correctly near cursor
- Works with scrolled textarea
- Escape dismisses

---

## Sprint 3: Visual Composer

**Scope:** Block-based editor mode where the prompt is displayed as draggable blocks (text, variable chips, include chips) that can be reordered.

**Why third:** Most complex feature, requires a mode toggle in the editor, and benefits from having autocomplete already in place (for adding new variable/include blocks).

### Files to create
- `resources/js/spa/components/workspace/VisualComposer.jsx` — block list with drag-drop reordering, block type badges, inline editing for text blocks

### Files to modify
- `resources/js/spa/components/workspace/Editor.jsx` — add "Visual" mode toggle button in toolbar. When active, swap textarea for VisualComposer. Content syncs bidirectionally (parse on toggle-in, serialize on toggle-out/save).

### Implementation
1. **Mode toggle:** Editor toolbar gets a "Text | Visual" toggle. State: `editorMode: 'text' | 'visual'`.
2. **Content parsing:** `parseContent(text)` splits on `{{variable}}` and `{{>slug}}` regex, producing typed blocks:
   - `{ id, type: 'text', value: 'free text...' }`
   - `{ id, type: 'variable', value: 'variable_name' }`
   - `{ id, type: 'include', value: 'fragment_slug' }`
3. **Block rendering:** Each block type has distinct UI:
   - Text: editable textarea (auto-resize), neutral background
   - Variable: indigo chip/badge with `{{name}}`, delete button
   - Include: amber chip/badge with `{{>slug}}`, delete button
   - All blocks: drag handle on the left
4. **Drag-drop:** Use `sortablejs` (already in dependencies) for reordering blocks.
5. **Block actions:** Add buttons for new text/variable/include blocks. Variable/include add buttons could use the autocomplete data for selection.
6. **Serialization:** `serialize(blocks)` reconstructs plain text from blocks. Called on save and when toggling back to text mode.
7. **Dirty state:** Block edits set the editor's dirty flag.

### Verification
- Toggle to Visual mode → content parsed into blocks correctly
- Drag blocks to reorder → content updates
- Add/remove blocks → content updates
- Toggle back to Text mode → serialized correctly
- Save from Visual mode → new version created with correct content
- Handles edge cases: empty content, adjacent variables, nested includes

---

## Execution Order & Dependencies

```
Sprint 1 (Diff Viewer)     — independent, no editor changes
Sprint 2 (Autocomplete)    — independent, adds to editor without replacing
Sprint 3 (Visual Composer)  — benefits from Sprint 2 data (variable/fragment lists)
```

All three can be implemented sequentially in one session or across multiple. Each sprint is self-contained and shippable independently.

## Key Files Reference

| File | Current State |
|------|--------------|
| `resources/js/spa/pages/WorkspacePage.jsx` | Orchestrator — routes state between sidebar, editor, results |
| `resources/js/spa/components/workspace/Editor.jsx` | Plain textarea, 147 lines, Ctrl+S save, preview toggle |
| `resources/js/spa/components/workspace/VersionSidebar.jsx` | Branch selector, version list, single selection, 130 lines |
| `resources/js/spa/components/workspace/PreviewPanel.jsx` | Live render via API, variable fill form |
| `resources/js/spa/components/workspace/ResultsPanel.jsx` | Results list with sort/filter/star/rate |
| `app/Http/Controllers/InternalApiController.php` | GET /internal/variables, GET /internal/fragments |
| `package.json` | Has `sortablejs` in dependencies, needs `diff` added |

## Test Strategy

Each sprint: `npm run build` + manual verification in browser. No new PHP tests needed (pure frontend features using existing API endpoints).
