# Phase 4: Workspace Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the 3-panel Livewire workspace (editor, version sidebar, results panel) to React, achieving feature parity for core prompt editing, versioning, and result management.

**Architecture:** React components mirroring the Livewire workspace. State managed via React Query for server data and useState for local UI state. WorkspacePage orchestrates child components via props/callbacks (no global state needed). All API calls go through the existing `/api/v1/` endpoints with session cookie auth via `dual.auth` middleware.

**Tech Stack:** React 19, React Query v5, Axios, Tailwind CSS
**PHP Path:** `C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`
**Spec:** `docs/superpowers/specs/2026-04-08-react-flow-migration-design.md`

---

## Critical Pre-requisite: Auth Middleware

The prompt/version/result/branch API endpoints are under `api.auth` (Bearer token only). The React SPA uses session cookies. **Task 1 moves all protected endpoints from `api.auth` to `dual.auth`** so they accept both session cookies and Bearer tokens.

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `resources/js/spa/api/versions.js` | Version CRUD API wrappers |
| `resources/js/spa/api/results.js` | Result CRUD API wrappers |
| `resources/js/spa/api/branches.js` | Branch CRUD API wrappers |
| `resources/js/spa/api/render.js` | Render endpoint wrapper |
| `resources/js/spa/api/providers.js` | LLM providers list |
| `resources/js/spa/components/workspace/Editor.jsx` | Textarea, save, variable detection |
| `resources/js/spa/components/workspace/VersionSidebar.jsx` | Version list, branch switcher |
| `resources/js/spa/components/workspace/ResultsPanel.jsx` | Results list, star, rate, sort |
| `resources/js/spa/components/workspace/RunWithLlm.jsx` | Provider select, variable fill, run |
| `resources/js/spa/components/workspace/ManualResultForm.jsx` | Paste result form |
| `resources/js/spa/components/workspace/PreviewPanel.jsx` | Live rendered preview |

### Modified Files

| File | Change |
|------|--------|
| `routes/api.php` | Move all protected routes from `api.auth` to `dual.auth` |
| `resources/js/spa/pages/WorkspacePage.jsx` | Full workspace implementation |

---

## Task 1: Auth Fix + API Layer

**Files:**
- Modify: `routes/api.php` — change `api.auth` to `dual.auth` for all protected routes
- Create: `resources/js/spa/api/versions.js`
- Create: `resources/js/spa/api/results.js`
- Create: `resources/js/spa/api/branches.js`
- Create: `resources/js/spa/api/render.js`
- Create: `resources/js/spa/api/providers.js`

- [ ] **Step 1: Move all protected routes to dual.auth**

In `routes/api.php`, change the main protected route group from:
```php
Route::middleware('api.auth')->group(function () {
```
to:
```php
Route::middleware('dual.auth')->group(function () {
```

This makes ALL protected endpoints (prompts, versions, branches, results, teams, collections, etc.) accessible via both session cookies (SPA) and Bearer tokens (API consumers).

Also move the `EncryptCookies` + `StartSession` that was on the graph routes' `dual.auth` group — since `EnsureFrontendRequestsAreStateful` is now prepended to all API routes (added in Phase 3), we don't need the separate graph middleware group anymore. Merge the graph routes INTO the main `dual.auth` group.

- [ ] **Step 2: Create API modules**

`resources/js/spa/api/versions.js`:
```javascript
import client from './client.js';

export async function listVersions(username, slug, params = {}) {
    const { data } = await client.get(`/prompts/${username}/${slug}/versions`, { params });
    return data;
}

export async function createVersion(username, slug, { content, commit_message, variable_metadata }) {
    const { data } = await client.post(`/prompts/${username}/${slug}/versions`, {
        content, commit_message, variable_metadata,
    });
    return data;
}

export async function getVersion(username, slug, versionNumber) {
    const { data } = await client.get(`/prompts/${username}/${slug}/versions/${versionNumber}`);
    return data;
}
```

`resources/js/spa/api/results.js`:
```javascript
import client from './client.js';

export async function listResults(username, slug, params = {}) {
    const { data } = await client.get(`/prompts/${username}/${slug}/results`, { params });
    return data;
}

export async function createResult(username, slug, resultData) {
    const { data } = await client.post(`/prompts/${username}/${slug}/results`, resultData);
    return data;
}

export async function updateResult(resultId, { rating, starred, notes }) {
    const { data } = await client.patch(`/results/${resultId}`, { rating, starred, notes });
    return data;
}

export async function deleteResult(resultId) {
    const { data } = await client.delete(`/results/${resultId}`);
    return data;
}
```

`resources/js/spa/api/branches.js`:
```javascript
import client from './client.js';

export async function listBranches(username, slug) {
    const { data } = await client.get(`/prompts/${username}/${slug}/branches`);
    return data;
}

export async function createBranch(username, slug, { name, from_version }) {
    const { data } = await client.post(`/prompts/${username}/${slug}/branches`, { name, from_version });
    return data;
}

export async function deleteBranch(username, slug, branchName) {
    const { data } = await client.delete(`/prompts/${username}/${slug}/branches/${branchName}`);
    return data;
}

export async function setDefaultBranch(username, slug, branchName) {
    const { data } = await client.patch(`/prompts/${username}/${slug}/branches/${branchName}/default`);
    return data;
}
```

`resources/js/spa/api/render.js`:
```javascript
import client from './client.js';

export async function renderPrompt(username, slug, { version, variables }) {
    const { data } = await client.post(`/prompts/${username}/${slug}/render`, { version, variables });
    return data;
}
```

`resources/js/spa/api/providers.js`:
```javascript
import client from './client.js';

export async function listProviders() {
    const { data } = await client.get('/pipeline-templates');
    return data;
}
```

Note: LLM providers don't have a dedicated API endpoint yet. For Phase 4, the Run with LLM feature will be limited — we can add an LLM providers API endpoint if needed, or defer to the classic UI for running LLMs.

- [ ] **Step 3: Run PHP tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: All 351+ tests pass (dual.auth accepts Bearer tokens via fallback).

- [ ] **Step 4: Build and commit**

```bash
npm run build
git add -A
git commit -m "feat: move all API routes to dual.auth, add workspace API modules"
```

---

## Task 2: WorkspacePage Shell + Editor

**Files:**
- Rewrite: `resources/js/spa/pages/WorkspacePage.jsx`
- Create: `resources/js/spa/components/workspace/Editor.jsx`

- [ ] **Step 1: Create Editor component**

The Editor component handles:
- Textarea for prompt content
- Commit message input
- Save button (creates new version via API)
- Variable detection (extracts `{{varName}}` patterns from content)
- Dirty state indicator
- Keyboard shortcut: Ctrl+S to save

```jsx
// resources/js/spa/components/workspace/Editor.jsx
import { useState, useEffect, useCallback, useRef } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { createVersion } from '../../api/versions.js';

const VAR_PATTERN = /\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/g;
const INCLUDE_PATTERN = /\{\{>([a-zA-Z0-9_-]+)\}\}/g;

export default function Editor({ prompt, version, username, slug, onVersionCreated }) {
    const [content, setContent] = useState('');
    const [commitMessage, setCommitMessage] = useState('');
    const [isDirty, setIsDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [variables, setVariables] = useState([]);
    const [includes, setIncludes] = useState([]);
    const textareaRef = useRef(null);
    const queryClient = useQueryClient();

    // Load version content
    useEffect(() => {
        if (version) {
            setContent(version.content || '');
            setIsDirty(false);
            setCommitMessage('');
        }
    }, [version?.id]);

    // Detect variables and includes
    useEffect(() => {
        const vars = [...content.matchAll(VAR_PATTERN)].map(m => m[1]);
        const incs = [...content.matchAll(INCLUDE_PATTERN)].map(m => m[1]);
        setVariables([...new Set(vars)]);
        setIncludes([...new Set(incs)]);
    }, [content]);

    const handleSave = useCallback(async () => {
        if (!isDirty || saving) return;
        setSaving(true);
        try {
            const result = await createVersion(username, slug, {
                content,
                commit_message: commitMessage || null,
                variable_metadata: version?.variable_metadata || null,
            });
            setIsDirty(false);
            setCommitMessage('');
            queryClient.invalidateQueries({ queryKey: ['workspace'] });
            onVersionCreated?.(result.data);
        } catch (err) {
            console.error('Save failed:', err);
        } finally {
            setSaving(false);
        }
    }, [content, commitMessage, isDirty, saving, username, slug, version, queryClient, onVersionCreated]);

    // Ctrl+S keyboard shortcut
    useEffect(() => {
        const handler = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                handleSave();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [handleSave]);

    return (
        <div className="flex flex-col h-full">
            {/* Toolbar */}
            <div className="flex items-center justify-between px-4 py-2 border-b border-gray-700 bg-gray-800/50">
                <div className="flex items-center gap-2">
                    <h2 className="text-sm font-semibold text-gray-100 truncate">{prompt?.name}</h2>
                    {version && (
                        <span className="text-[10px] bg-gray-700 text-gray-400 px-1.5 py-0.5 rounded">
                            v{version.version_number}
                        </span>
                    )}
                    {isDirty && (
                        <span className="text-[10px] text-amber-400">unsaved</span>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    {variables.length > 0 && (
                        <span className="text-[10px] text-blue-400">{variables.length} vars</span>
                    )}
                    {includes.length > 0 && (
                        <span className="text-[10px] text-indigo-400">{includes.length} includes</span>
                    )}
                </div>
            </div>

            {/* Editor area */}
            <div className="flex-1 overflow-hidden">
                <textarea
                    ref={textareaRef}
                    value={content}
                    onChange={(e) => { setContent(e.target.value); setIsDirty(true); }}
                    className="w-full h-full bg-gray-900 text-gray-100 font-mono text-sm p-4 resize-none outline-none border-none"
                    placeholder="Write your prompt..."
                    spellCheck={false}
                />
            </div>

            {/* Save bar */}
            <div className="flex items-center gap-2 px-4 py-2 border-t border-gray-700 bg-gray-800/50">
                <input
                    value={commitMessage}
                    onChange={(e) => setCommitMessage(e.target.value)}
                    placeholder="Commit message (optional)"
                    className="flex-1 bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-2.5 py-1.5 focus:border-indigo-500 outline-none"
                    onKeyDown={(e) => { if (e.key === 'Enter') handleSave(); }}
                />
                <button
                    onClick={handleSave}
                    disabled={!isDirty || saving}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-1.5 rounded disabled:opacity-50"
                >
                    {saving ? 'Saving...' : 'Save'}
                </button>
                <span className="text-[10px] text-gray-500">Ctrl+S</span>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Rewrite WorkspacePage**

The workspace fetches prompt data and renders a 3-panel layout:

```jsx
// resources/js/spa/pages/WorkspacePage.jsx
import { useState, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getPrompt } from '../api/prompts.js'; // Need to add this function
import Editor from '../components/workspace/Editor.jsx';

// getPrompt needs to be added to api/prompts.js:
// export async function getPrompt(username, slug) {
//     const { data } = await client.get(`/prompts/${username}/${slug}`);
//     return data;
// }

export default function WorkspacePage() {
    const { username, slug } = useParams();
    const queryClient = useQueryClient();
    const [currentVersionId, setCurrentVersionId] = useState(null);

    const { data: promptData, isLoading, error } = useQuery({
        queryKey: ['workspace', username, slug],
        queryFn: () => getPrompt(username, slug),
    });

    const prompt = promptData?.data;
    const activeVersion = prompt?.active_version;
    const currentVersion = currentVersionId
        ? prompt?.versions?.find(v => v.id === currentVersionId) || activeVersion
        : activeVersion;

    const handleVersionCreated = useCallback((newVersion) => {
        setCurrentVersionId(newVersion.id);
        queryClient.invalidateQueries({ queryKey: ['workspace', username, slug] });
    }, [username, slug, queryClient]);

    const handleVersionSelected = useCallback((versionId) => {
        setCurrentVersionId(versionId);
    }, []);

    if (isLoading) {
        return (
            <div className="h-full flex items-center justify-center text-gray-400">
                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    if (error || !prompt) {
        return (
            <div className="h-full flex items-center justify-center text-red-400">
                <div className="text-center">
                    <p className="mb-4">Prompt not found.</p>
                    <Link to="/canvas" className="text-indigo-400 hover:underline">← Back to Canvas</Link>
                </div>
            </div>
        );
    }

    return (
        <div className="h-full flex">
            {/* Left: Version Sidebar (Task 3) */}
            <div className="w-64 border-r border-gray-700 bg-gray-800 overflow-y-auto shrink-0">
                <div className="p-3 text-sm text-gray-400">Versions — coming next</div>
            </div>

            {/* Center: Editor */}
            <div className="flex-1 min-w-0">
                <Editor
                    prompt={prompt}
                    version={currentVersion}
                    username={username}
                    slug={slug}
                    onVersionCreated={handleVersionCreated}
                />
            </div>

            {/* Right: Results Panel (Task 4) */}
            <div className="w-80 border-l border-gray-700 bg-gray-800 overflow-y-auto shrink-0">
                <div className="p-3 text-sm text-gray-400">Results — coming next</div>
            </div>
        </div>
    );
}
```

Also add `getPrompt` to `resources/js/spa/api/prompts.js`:
```javascript
export async function getPrompt(username, slug) {
    const { data } = await client.get(`/prompts/${username}/${slug}`);
    return data;
}
```

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add -A
git commit -m "feat: WorkspacePage shell with Editor component"
```

---

## Task 3: VersionSidebar

**Files:**
- Create: `resources/js/spa/components/workspace/VersionSidebar.jsx`
- Modify: `resources/js/spa/pages/WorkspacePage.jsx` — wire in sidebar

- [ ] **Step 1: Create VersionSidebar**

Features:
- Branch selector dropdown (switch branches)
- Version list filtered by current branch
- Click version to load in editor
- Create branch button
- Display format: `main#3 (v7)`

```jsx
// resources/js/spa/components/workspace/VersionSidebar.jsx
import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listVersions } from '../../api/versions.js';
import { listBranches, createBranch } from '../../api/branches.js';

export default function VersionSidebar({ prompt, username, slug, currentVersionId, onVersionSelect }) {
    const queryClient = useQueryClient();
    const [currentBranchId, setCurrentBranchId] = useState(prompt?.default_branch_id);
    const [showBranchForm, setShowBranchForm] = useState(false);
    const [newBranchName, setNewBranchName] = useState('');

    const { data: branchesData } = useQuery({
        queryKey: ['workspace', username, slug, 'branches'],
        queryFn: () => listBranches(username, slug),
    });

    const { data: versionsData } = useQuery({
        queryKey: ['workspace', username, slug, 'versions'],
        queryFn: () => listVersions(username, slug),
    });

    const branches = branchesData?.data ?? [];
    const allVersions = versionsData?.data ?? [];
    const currentBranch = branches.find(b => b.id === currentBranchId) || branches.find(b => b.is_default);
    const versions = currentBranch
        ? allVersions.filter(v => v.branch_id === currentBranch.id)
        : allVersions;

    const handleCreateBranch = async () => {
        if (!newBranchName.trim()) return;
        try {
            await createBranch(username, slug, {
                name: newBranchName.trim(),
                from_version: currentVersionId,
            });
            queryClient.invalidateQueries({ queryKey: ['workspace'] });
            setNewBranchName('');
            setShowBranchForm(false);
        } catch (err) {
            console.error('Create branch failed:', err);
        }
    };

    return (
        <div className="flex flex-col h-full">
            {/* Branch selector */}
            <div className="p-3 border-b border-gray-700">
                <div className="flex items-center justify-between mb-2">
                    <span className="text-xs text-gray-500 uppercase tracking-wider">Branch</span>
                    <button
                        onClick={() => setShowBranchForm(!showBranchForm)}
                        className="text-xs text-indigo-400 hover:text-indigo-300"
                    >
                        + New
                    </button>
                </div>
                <select
                    value={currentBranch?.id || ''}
                    onChange={(e) => {
                        const branchId = parseInt(e.target.value);
                        setCurrentBranchId(branchId);
                        const branch = branches.find(b => b.id === branchId);
                        if (branch?.head_version_id) {
                            onVersionSelect(branch.head_version_id);
                        }
                    }}
                    className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-2 py-1.5 outline-none"
                >
                    {branches.map(b => (
                        <option key={b.id} value={b.id}>
                            {b.name} {b.is_default ? '(default)' : ''} — {b.versions_count || 0} versions
                        </option>
                    ))}
                </select>

                {showBranchForm && (
                    <div className="mt-2 flex gap-1">
                        <input
                            value={newBranchName}
                            onChange={(e) => setNewBranchName(e.target.value)}
                            placeholder="Branch name"
                            className="flex-1 bg-gray-900 border border-gray-600 text-gray-200 text-xs rounded px-2 py-1 outline-none"
                            onKeyDown={(e) => e.key === 'Enter' && handleCreateBranch()}
                        />
                        <button onClick={handleCreateBranch} className="bg-indigo-600 text-white text-xs px-2 py-1 rounded">
                            Create
                        </button>
                    </div>
                )}
            </div>

            {/* Version list */}
            <div className="flex-1 overflow-y-auto">
                <div className="p-2 space-y-1">
                    {versions.length === 0 && (
                        <p className="text-xs text-gray-500 p-2">No versions yet</p>
                    )}
                    {versions.map(v => (
                        <button
                            key={v.id}
                            onClick={() => onVersionSelect(v.id)}
                            className={`w-full text-left px-3 py-2 rounded text-xs transition-colors ${
                                v.id === currentVersionId
                                    ? 'bg-indigo-900/50 text-indigo-200 border border-indigo-700'
                                    : 'text-gray-300 hover:bg-gray-700'
                            }`}
                        >
                            <div className="flex items-center justify-between">
                                <span className="font-medium">
                                    {currentBranch?.name}#{v.branch_version_number} (v{v.version_number})
                                </span>
                            </div>
                            {v.commit_message && (
                                <div className="text-[10px] text-gray-500 mt-0.5 truncate">{v.commit_message}</div>
                            )}
                            <div className="text-[10px] text-gray-600 mt-0.5">
                                {new Date(v.created_at).toLocaleDateString()}
                            </div>
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Wire into WorkspacePage**

Replace the placeholder left panel with `<VersionSidebar>`, passing the needed props and `onVersionSelect`.

Also update the version loading logic: when a version is selected by ID, fetch it from the versions list or via API.

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add -A
git commit -m "feat: VersionSidebar with branch switcher and version list"
```

---

## Task 4: ResultsPanel

**Files:**
- Create: `resources/js/spa/components/workspace/ResultsPanel.jsx`
- Modify: `resources/js/spa/pages/WorkspacePage.jsx` — wire in results

- [ ] **Step 1: Create ResultsPanel**

Features:
- List results for current prompt (optionally filtered by version)
- Sort: newest, oldest, top rated, most tokens, fastest
- Star/unstar toggle
- Rating (1-5 stars)
- Delete result
- Show provider, model, token counts, duration

```jsx
// resources/js/spa/components/workspace/ResultsPanel.jsx
// Full component with sort dropdown, result cards with star/rate/delete,
// token count display, and duration. Uses listResults, updateResult, deleteResult APIs.
```

- [ ] **Step 2: Wire into WorkspacePage**

Replace the placeholder right panel with `<ResultsPanel>`.

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add -A
git commit -m "feat: ResultsPanel with star, rate, sort, delete"
```

---

## Task 5: ManualResultForm + RunWithLlm

**Files:**
- Create: `resources/js/spa/components/workspace/ManualResultForm.jsx`
- Create: `resources/js/spa/components/workspace/RunWithLlm.jsx`
- Modify: `resources/js/spa/components/workspace/ResultsPanel.jsx` — add toggle buttons

- [ ] **Step 1: Create ManualResultForm**

Simple form to paste a result manually:
- response_text (textarea, required)
- provider_name (input)
- model_name (input)
- notes (textarea)
- rating (1-5 selector)
- Calls `createResult()` with source='manual'

- [ ] **Step 2: Create RunWithLlm (simplified)**

For Phase 4, this is a simplified version:
- Shows a message directing to the classic UI for LLM execution
- The full LLM execution from the SPA requires additional API endpoints that don't exist yet (LLM providers list, LLM dispatch endpoint accessible via session auth)
- Link to classic workspace: `/prompts/{username}/{slug}`

OR if there IS a run endpoint available, implement the full flow:
- Provider selection from available providers
- Variable fill form
- Run button
- Result appears in ResultsPanel

Check if a dispatch/run API endpoint exists. If not, use the simplified version.

- [ ] **Step 3: Wire into ResultsPanel or WorkspacePage**

Add toggle buttons at the top of ResultsPanel: "Add Result" and "Run LLM"

- [ ] **Step 4: Build and commit**

```bash
npm run build
git add -A
git commit -m "feat: ManualResultForm and RunWithLlm panel"
```

---

## Task 6: Preview Panel

**Files:**
- Create: `resources/js/spa/components/workspace/PreviewPanel.jsx`
- Modify: `resources/js/spa/components/workspace/Editor.jsx` — add preview toggle

- [ ] **Step 1: Create PreviewPanel**

Live rendered preview that:
- Calls `renderPrompt()` API with current content and variable values
- Shows rendered output
- Shows detected variables with input fields for values
- Updates on content change (debounced)
- Shows resolved includes (green badges) and missing variables (amber badges)

- [ ] **Step 2: Add preview toggle to Editor toolbar**

Add a "Preview" button to Editor's toolbar bar. When active, split the editor area vertically — textarea on top, preview on bottom.

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add -A
git commit -m "feat: PreviewPanel with live template rendering"
```

---

## Task 7: Integration + Verify

- [ ] **Step 1: Verify build**

```bash
npm run build
```

- [ ] **Step 2: Run PHP tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 351+ tests pass.

- [ ] **Step 3: Manual E2E test**

1. Navigate to canvas → click a prompt node → "Open in Editor" → workspace opens
2. Editor shows prompt content, version badge
3. Edit content → dirty indicator shows
4. Ctrl+S → saves new version, version appears in sidebar
5. Switch branch → versions filter
6. Click older version → editor loads that content
7. Results panel shows existing results
8. Star/rate a result → persists
9. "Add Result" → paste form, save → appears in list
10. Preview toggle → shows rendered output with variable fill

- [ ] **Step 4: Deploy to Hostinger**

```bash
# On Hostinger server:
cd ~/domains/urge.acordado.org/app
bash deploy/hostinger.sh
```

- [ ] **Step 5: Commit and push**

```bash
git add -A
git commit -m "feat: Phase 4 complete — React workspace with editor, versions, results"
git push
```

---

## Verification Summary

| Feature | How to verify |
|---------|--------------|
| Editor loads content | Open workspace → content appears |
| Save version | Edit → Ctrl+S → new version in sidebar |
| Variable detection | Type `{{name}}` → variable count updates |
| Version switching | Click version in sidebar → editor loads it |
| Branch switching | Switch branch → version list filters |
| Create branch | New branch → appears in dropdown |
| Results list | Results show with provider, tokens, rating |
| Star/rate | Toggle star, set rating → persists on refresh |
| Delete result | Delete → removed from list |
| Sort results | Change sort → order updates |
| Manual result | Paste form → save → appears in results |
| Preview | Toggle preview → shows rendered content |
| Auth works | All API calls use session cookies successfully |
| PHP tests | 351+ pass |

## Deferred Features (Phase 4.5 or later)

These workspace features are NOT included in Phase 4 to keep scope manageable:
- **Visual composer** (drag-drop blocks) — complex, low priority
- **Autocomplete dropdown** ({{var}} and {{>slug}} suggestions) — nice-to-have
- **AI suggestions** (improvement suggestions via LLM) — requires additional API
- **Version diff viewer** — port diff.js to React
- **Import results** (file upload) — keep in classic UI
- **Export as .md / ZIP** — keep in classic UI
- **Pipeline run grouping** — keep in classic UI
- **Collection add from workspace** — keep in classic UI
