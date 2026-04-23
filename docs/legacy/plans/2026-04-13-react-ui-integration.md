# React UI Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unify the disconnected Livewire and React UIs into a single React-primary experience with a slim sidebar nav, migrated Browse page, and proper redirect chain.

**Architecture:** Replace the top nav bar with a persistent icon-rail sidebar (56px). Rewrite BrowsePage in React with tabs (prompts/fragments/collections/starred), inline filter bar, and content grid. All data from existing API endpoints except one new `GET /api/v1/results/starred` endpoint. Redirect chain updated so login lands at `/app/browse`.

**Tech Stack:** React 19, React Query v5, React Router DOM, Tailwind CSS, Axios
**PHP Path:** `C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`
**Spec:** `docs/superpowers/specs/2026-04-13-react-ui-integration-design.md`

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `resources/js/spa/components/Sidebar.jsx` | Icon-rail sidebar navigation (56px, always visible) |
| `resources/js/spa/components/browse/PromptCard.jsx` | Prompt/fragment card for grid display |
| `resources/js/spa/components/browse/CollectionList.jsx` | Collections tab with expand/collapse |
| `resources/js/spa/components/browse/StarredResults.jsx` | Starred results tab |
| `resources/js/spa/components/browse/CreatePromptForm.jsx` | Inline new prompt creation |
| `resources/js/spa/api/collections.js` | Collection API wrappers |
| `resources/js/spa/api/categories.js` | Category API wrappers |

### Modified Files

| File | Change |
|------|--------|
| `resources/js/spa/components/Layout.jsx` | Replace top nav with Sidebar + main area |
| `resources/js/spa/App.jsx` | Default redirect `/canvas` → `/browse` |
| `resources/js/spa/pages/LoginPage.jsx` | Post-login redirect `/canvas` → `/browse` |
| `resources/js/spa/pages/BrowsePage.jsx` | Full rewrite: tabs, filters, grid |
| `resources/js/spa/pages/SettingsPage.jsx` | Link to Livewire `/settings` (keep as redirect) |
| `routes/web.php` | Change `/` and `/dashboard` redirects to `/app/browse` |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Post-login redirect to `/app/browse` |
| `routes/api.php` | Add starred results endpoint |
| `app/Http/Controllers/Api/ResultController.php` | Add `starred()` method |

---

## Task 1: Starred Results API Endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/ResultController.php`
- Modify: `routes/api.php`

The Browse page needs a global starred results endpoint (current API only returns results per-prompt).

- [ ] **Step 1: Add starred method to ResultController**

Add to `app/Http/Controllers/Api/ResultController.php`:

```php
public function starred(Request $request): JsonResponse
{
    $query = Result::where('starred', true)
        ->whereHas('prompt', function ($q) use ($request) {
            $q->visibleTo($request->user());
        })
        ->with(['prompt.creator', 'promptVersion'])
        ->orderByDesc('created_at');

    return $this->paginated($query, $request);
}
```

- [ ] **Step 2: Add route**

In `routes/api.php`, inside the `dual.auth` middleware group, add after the existing results routes:

```php
Route::get('results/starred', [ResultController::class, 'starred']);
```

Place it **before** `Route::get('results/{result}', ...)` so it doesn't get captured by the `{result}` wildcard.

- [ ] **Step 3: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 351+ tests pass.

- [ ] **Step 4: Commit**

```bash
git add routes/api.php app/Http/Controllers/Api/ResultController.php
git commit -m "feat: add GET /api/v1/results/starred endpoint for global starred results"
```

---

## Task 2: Redirect Chain Updates

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `resources/js/spa/App.jsx`
- Modify: `resources/js/spa/pages/LoginPage.jsx`

- [ ] **Step 1: Update Laravel redirects**

In `routes/web.php`, change the root redirect:

```php
// Before:
Route::get('/', fn () => redirect()->route('browse'));

// After:
Route::get('/', fn () => redirect('/app/browse'));
```

Change the dashboard redirect:

```php
// Before:
Route::get('/dashboard', fn () => redirect()->route('browse'))->name('dashboard');

// After:
Route::get('/dashboard', fn () => redirect('/app/browse'))->name('dashboard');
```

- [ ] **Step 2: Update post-login redirect**

In `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, change the `store` method:

```php
// Before:
return redirect()->intended(route('dashboard', absolute: false));

// After:
return redirect()->intended('/app/browse');
```

- [ ] **Step 3: Update React default route**

In `resources/js/spa/App.jsx`, change the catch-all:

```jsx
// Before:
<Route path="*" element={<Navigate to="/canvas" replace />} />

// After:
<Route path="*" element={<Navigate to="/browse" replace />} />
```

- [ ] **Step 4: Update React login redirect**

In `resources/js/spa/pages/LoginPage.jsx`, change both navigations:

```jsx
// Before (isAuthenticated check):
navigate('/canvas', { replace: true });

// After:
navigate('/browse', { replace: true });

// Before (handleSubmit success):
navigate('/canvas');

// After:
navigate('/browse');
```

- [ ] **Step 5: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 351+ tests pass.

- [ ] **Step 6: Build and commit**

```bash
npm run build
git add routes/web.php app/Http/Controllers/Auth/AuthenticatedSessionController.php resources/js/spa/App.jsx resources/js/spa/pages/LoginPage.jsx public/build/
git commit -m "feat: redirect chain updated — login lands on /app/browse"
```

---

## Task 3: Sidebar Component

**Files:**
- Create: `resources/js/spa/components/Sidebar.jsx`
- Modify: `resources/js/spa/components/Layout.jsx`

- [ ] **Step 1: Create Sidebar component**

Create `resources/js/spa/components/Sidebar.jsx`:

```jsx
import { Link, useLocation } from 'react-router-dom';
import useAuth from '../hooks/useAuth.js';

const navItems = [
    { path: '/browse', label: 'Browse', icon: BrowseIcon },
    { path: '/canvas', label: 'Canvas', icon: CanvasIcon },
];

const externalItems = [
    { href: '/settings', label: 'Settings', icon: SettingsIcon },
    { href: '/teams', label: 'Teams', icon: TeamsIcon },
];

function BrowseIcon() {
    return (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
        </svg>
    );
}

function CanvasIcon() {
    return (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
        </svg>
    );
}

function SettingsIcon() {
    return (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>
    );
}

function TeamsIcon() {
    return (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
        </svg>
    );
}

export default function Sidebar() {
    const location = useLocation();
    const { user, logout } = useAuth();

    const isActive = (path) => location.pathname === path || location.pathname.startsWith(path + '/');

    const NavLink = ({ path, label, icon: Icon }) => (
        <Link
            to={path}
            title={label}
            className={`w-9 h-9 rounded-lg flex items-center justify-center transition-colors ${
                isActive(path)
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-500 hover:text-gray-300 hover:bg-gray-800'
            }`}
        >
            <Icon />
        </Link>
    );

    const ExtLink = ({ href, label, icon: Icon }) => (
        <a
            href={href}
            title={label}
            className="w-9 h-9 rounded-lg flex items-center justify-center text-gray-500 hover:text-gray-300 hover:bg-gray-800 transition-colors"
        >
            <Icon />
        </a>
    );

    return (
        <>
            {/* Desktop: vertical sidebar */}
            <div className="hidden md:flex w-14 bg-gray-900 border-r border-gray-700 flex-col items-center py-3 shrink-0">
                <Link to="/browse" className="text-indigo-400 font-bold text-lg mb-6">U</Link>
                <div className="flex flex-col items-center gap-2">
                    {navItems.map(item => <NavLink key={item.path} {...item} />)}
                </div>
                <div className="flex-1" />
                <div className="flex flex-col items-center gap-2 mb-3">
                    {externalItems.map(item => <ExtLink key={item.href} {...item} />)}
                </div>
                {user && (
                    <div className="flex flex-col items-center gap-2">
                        <div title={user.name} className="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-xs text-gray-300 font-medium">
                            {user.name?.charAt(0)?.toUpperCase()}
                        </div>
                        <button onClick={logout} title="Logout" className="text-gray-600 hover:text-red-400 transition-colors">
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                        </button>
                    </div>
                )}
            </div>

            {/* Mobile: bottom tab bar */}
            <div className="md:hidden fixed bottom-0 left-0 right-0 bg-gray-900 border-t border-gray-700 flex items-center justify-around py-2 z-50">
                {navItems.map(({ path, label, icon: Icon }) => (
                    <Link
                        key={path}
                        to={path}
                        className={`flex flex-col items-center gap-0.5 px-3 py-1 ${
                            isActive(path) ? 'text-indigo-400' : 'text-gray-500'
                        }`}
                    >
                        <Icon />
                        <span className="text-[9px]">{label}</span>
                    </Link>
                ))}
                {externalItems.map(({ href, label, icon: Icon }) => (
                    <a
                        key={href}
                        href={href}
                        className="flex flex-col items-center gap-0.5 px-3 py-1 text-gray-500"
                    >
                        <Icon />
                        <span className="text-[9px]">{label}</span>
                    </a>
                ))}
            </div>
        </>
    );
}
```

- [ ] **Step 2: Rewrite Layout to use Sidebar**

Replace `resources/js/spa/components/Layout.jsx`:

```jsx
import Sidebar from './Sidebar.jsx';

export default function Layout({ children }) {
    return (
        <div className="h-screen w-screen flex bg-gray-900 text-gray-100">
            <Sidebar />
            <main className="flex-1 overflow-hidden pb-14 md:pb-0">{children}</main>
        </div>
    );
}
```

Note: `pb-14 md:pb-0` adds bottom padding on mobile to avoid content hiding behind the bottom tab bar.

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add resources/js/spa/components/Sidebar.jsx resources/js/spa/components/Layout.jsx public/build/
git commit -m "feat: slim sidebar navigation replacing top nav bar"
```

---

## Task 4: API Modules for Browse

**Files:**
- Create: `resources/js/spa/api/collections.js`
- Create: `resources/js/spa/api/categories.js`
- Modify: `resources/js/spa/api/results.js`

- [ ] **Step 1: Create collections API module**

Create `resources/js/spa/api/collections.js`:

```javascript
import client from './client.js';

export async function listCollections(params = {}) {
    const { data } = await client.get('/collections', { params });
    return data;
}

export async function getCollection(slug) {
    const { data } = await client.get(`/collections/${slug}`);
    return data;
}
```

- [ ] **Step 2: Create categories API module**

Create `resources/js/spa/api/categories.js`:

```javascript
import client from './client.js';

export async function listCategories() {
    const { data } = await client.get('/categories');
    return data;
}
```

- [ ] **Step 3: Add starred results to results API**

Add to `resources/js/spa/api/results.js`:

```javascript
export async function listStarredResults(params = {}) {
    const { data } = await client.get('/results/starred', { params });
    return data;
}
```

- [ ] **Step 4: Add createPrompt to prompts API**

Add to `resources/js/spa/api/prompts.js`:

```javascript
export async function createPrompt({ name, type }) {
    const { data } = await client.post('/prompts', { name, type });
    return data;
}
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/spa/api/collections.js resources/js/spa/api/categories.js resources/js/spa/api/results.js resources/js/spa/api/prompts.js
git commit -m "feat: API modules for collections, categories, starred results, create prompt"
```

---

## Task 5: Browse Page — Prompts & Fragments Tabs

**Files:**
- Rewrite: `resources/js/spa/pages/BrowsePage.jsx`
- Create: `resources/js/spa/components/browse/PromptCard.jsx`
- Create: `resources/js/spa/components/browse/CreatePromptForm.jsx`

- [ ] **Step 1: Create PromptCard component**

Create `resources/js/spa/components/browse/PromptCard.jsx`:

```jsx
import { Link } from 'react-router-dom';

export default function PromptCard({ prompt }) {
    const owner = prompt.creator?.username || prompt.creator?.name;

    return (
        <Link
            to={`/workspace/${owner}/${prompt.slug}`}
            className="block bg-gray-800 border border-gray-700 rounded-lg p-4 hover:border-indigo-500 transition-colors"
        >
            <div className="flex items-start justify-between mb-2">
                <h3 className="text-sm font-medium text-gray-100 truncate">{prompt.name}</h3>
                {prompt.category && (
                    <span
                        className="text-[10px] px-1.5 py-0.5 rounded shrink-0 ml-2"
                        style={{
                            backgroundColor: (prompt.category.color || '#4b5563') + '20',
                            color: prompt.category.color || '#9ca3af',
                        }}
                    >
                        {prompt.category.name}
                    </span>
                )}
            </div>

            {prompt.description && (
                <p className="text-xs text-gray-400 line-clamp-2 mb-2">{prompt.description}</p>
            )}

            <div className="flex items-center gap-2 text-[10px] text-gray-500">
                {prompt.latest_version && (
                    <span>v{prompt.latest_version.version_number}</span>
                )}
                <span>{prompt.results_count || 0} results</span>
                {owner && <span>by {owner}</span>}
            </div>

            {prompt.tags?.length > 0 && (
                <div className="flex gap-1 mt-2 flex-wrap">
                    {prompt.tags.slice(0, 4).map(tag => (
                        <span key={tag} className="text-[10px] bg-blue-900/30 text-blue-400 px-1.5 py-0.5 rounded">
                            {tag}
                        </span>
                    ))}
                </div>
            )}
        </Link>
    );
}
```

- [ ] **Step 2: Create CreatePromptForm component**

Create `resources/js/spa/components/browse/CreatePromptForm.jsx`:

```jsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { createPrompt } from '../../api/prompts.js';

export default function CreatePromptForm({ onClose }) {
    const [name, setName] = useState('');
    const [type, setType] = useState('prompt');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!name.trim()) return;
        setSaving(true);
        setError('');
        try {
            const result = await createPrompt({ name: name.trim(), type });
            const prompt = result.data;
            queryClient.invalidateQueries({ queryKey: ['browse'] });
            navigate(`/workspace/${prompt.creator?.username || prompt.created_by}/${prompt.slug}`);
        } catch (err) {
            setError(err.response?.data?.message || 'Create failed');
            setSaving(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="flex items-center gap-2 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2">
            <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Prompt name..."
                autoFocus
                className="flex-1 bg-transparent text-gray-100 text-sm outline-none placeholder-gray-500"
            />
            <select
                value={type}
                onChange={(e) => setType(e.target.value)}
                className="bg-gray-900 border border-gray-600 text-gray-300 text-xs rounded px-2 py-1 outline-none"
            >
                <option value="prompt">Prompt</option>
                <option value="fragment">Fragment</option>
            </select>
            {error && <span className="text-red-400 text-xs">{error}</span>}
            <button
                type="submit"
                disabled={!name.trim() || saving}
                className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1 rounded disabled:opacity-50"
            >
                {saving ? '...' : 'Create'}
            </button>
            <button type="button" onClick={onClose} className="text-gray-500 hover:text-gray-300 text-sm">&times;</button>
        </form>
    );
}
```

- [ ] **Step 3: Rewrite BrowsePage**

Rewrite `resources/js/spa/pages/BrowsePage.jsx`:

```jsx
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { listPrompts } from '../api/prompts.js';
import { listCategories } from '../api/categories.js';
import PromptCard from '../components/browse/PromptCard.jsx';
import CreatePromptForm from '../components/browse/CreatePromptForm.jsx';

const TABS = [
    { key: 'prompts', label: 'Prompts' },
    { key: 'fragments', label: 'Fragments' },
    { key: 'collections', label: 'Collections' },
    { key: 'starred', label: 'Starred' },
];

export default function BrowsePage() {
    const [tab, setTab] = useState('prompts');
    const [search, setSearch] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [tagFilter, setTagFilter] = useState('');
    const [scope, setScope] = useState('mine');
    const [showCreate, setShowCreate] = useState(false);
    const [page, setPage] = useState(1);

    const isPromptTab = tab === 'prompts' || tab === 'fragments';
    const type = tab === 'fragments' ? 'fragment' : 'prompt';

    const { data: promptsData, isLoading } = useQuery({
        queryKey: ['browse', 'prompts', { type, search, categoryId, tagFilter, scope, page }],
        queryFn: () => listPrompts({
            type,
            search: search || undefined,
            category_id: categoryId || undefined,
            tag: tagFilter || undefined,
            scope,
            page,
        }),
        enabled: isPromptTab,
    });

    const { data: categoriesData } = useQuery({
        queryKey: ['browse', 'categories'],
        queryFn: listCategories,
    });

    const prompts = promptsData?.data ?? [];
    const pagination = promptsData?.meta ?? {};
    const categories = categoriesData?.data ?? [];

    const handleTabChange = (newTab) => {
        setTab(newTab);
        setPage(1);
    };

    const clearFilters = () => {
        setSearch('');
        setCategoryId('');
        setTagFilter('');
        setPage(1);
    };

    const hasFilters = search || categoryId || tagFilter;

    return (
        <div className="h-full flex flex-col overflow-hidden">
            {/* Tabs + New button */}
            <div className="flex items-center justify-between px-6 pt-4 pb-0">
                <div className="flex gap-1 border-b border-gray-700">
                    {TABS.map(t => (
                        <button
                            key={t.key}
                            onClick={() => handleTabChange(t.key)}
                            className={`px-4 py-2 text-sm transition-colors ${
                                tab === t.key
                                    ? 'text-white border-b-2 border-indigo-500'
                                    : 'text-gray-500 hover:text-gray-300'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
                <button
                    onClick={() => setShowCreate(!showCreate)}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-1.5 rounded"
                >
                    + New
                </button>
            </div>

            {/* Create form */}
            {showCreate && (
                <div className="px-6 pt-3">
                    <CreatePromptForm onClose={() => setShowCreate(false)} />
                </div>
            )}

            {/* Filter bar (prompt/fragment tabs only) */}
            {isPromptTab && (
                <div className="flex items-center gap-3 px-6 py-3">
                    <input
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        placeholder="Search..."
                        className="bg-gray-800 border border-gray-600 text-gray-200 text-sm rounded px-3 py-1.5 w-48 outline-none focus:border-indigo-500"
                    />
                    <select
                        value={categoryId}
                        onChange={(e) => { setCategoryId(e.target.value); setPage(1); }}
                        className="bg-gray-800 border border-gray-600 text-gray-300 text-sm rounded px-2 py-1.5 outline-none"
                    >
                        <option value="">All categories</option>
                        {categories.map(c => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                    <select
                        value={scope}
                        onChange={(e) => { setScope(e.target.value); setPage(1); }}
                        className="bg-gray-800 border border-gray-600 text-gray-300 text-sm rounded px-2 py-1.5 outline-none"
                    >
                        <option value="mine">My prompts</option>
                        <option value="shared">Shared with me</option>
                        <option value="all">All visible</option>
                    </select>
                    {hasFilters && (
                        <button onClick={clearFilters} className="text-xs text-gray-500 hover:text-gray-300">
                            Clear filters
                        </button>
                    )}
                </div>
            )}

            {/* Content */}
            <div className="flex-1 overflow-y-auto px-6 pb-6">
                {isPromptTab && (
                    <>
                        {isLoading ? (
                            <div className="flex justify-center py-12">
                                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
                            </div>
                        ) : prompts.length === 0 ? (
                            <div className="text-center py-12 text-gray-500">
                                <p className="mb-2">No {tab} found.</p>
                                {hasFilters && <button onClick={clearFilters} className="text-indigo-400 text-sm">Clear filters</button>}
                            </div>
                        ) : (
                            <>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {prompts.map(p => <PromptCard key={p.id} prompt={p} />)}
                                </div>
                                {/* Pagination */}
                                {pagination.last_page > 1 && (
                                    <div className="flex justify-center gap-2 mt-6">
                                        <button
                                            onClick={() => setPage(p => Math.max(1, p - 1))}
                                            disabled={page <= 1}
                                            className="text-sm text-gray-400 hover:text-white disabled:opacity-30 px-3 py-1"
                                        >
                                            Prev
                                        </button>
                                        <span className="text-sm text-gray-500">{page} / {pagination.last_page}</span>
                                        <button
                                            onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))}
                                            disabled={page >= pagination.last_page}
                                            className="text-sm text-gray-400 hover:text-white disabled:opacity-30 px-3 py-1"
                                        >
                                            Next
                                        </button>
                                    </div>
                                )}
                            </>
                        )}
                    </>
                )}

                {tab === 'collections' && (
                    <div className="text-gray-500 text-sm py-4">Loading collections...</div>
                )}

                {tab === 'starred' && (
                    <div className="text-gray-500 text-sm py-4">Loading starred results...</div>
                )}
            </div>
        </div>
    );
}
```

Note: Collections and Starred tabs have placeholders here — they get implemented in Tasks 6 and 7.

- [ ] **Step 4: Build and commit**

```bash
npm run build
git add resources/js/spa/pages/BrowsePage.jsx resources/js/spa/components/browse/PromptCard.jsx resources/js/spa/components/browse/CreatePromptForm.jsx resources/js/spa/api/prompts.js public/build/
git commit -m "feat: Browse page with prompts/fragments tabs, filters, grid, create form"
```

---

## Task 6: Browse Page — Collections Tab

**Files:**
- Create: `resources/js/spa/components/browse/CollectionList.jsx`
- Modify: `resources/js/spa/pages/BrowsePage.jsx`

- [ ] **Step 1: Create CollectionList component**

Create `resources/js/spa/components/browse/CollectionList.jsx`:

```jsx
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { listCollections, getCollection } from '../../api/collections.js';

function CollectionItem({ item }) {
    if (item.item_type === 'prompt_version') {
        return (
            <Link
                to={`/workspace/${item.prompt_owner || '_'}/${item.prompt_slug}`}
                className="block px-3 py-2 text-xs text-gray-300 hover:bg-gray-700 rounded"
            >
                <span className="text-gray-100">{item.prompt_name}</span>
                <span className="text-gray-500 ml-2">v{item.version_number}</span>
            </Link>
        );
    }
    if (item.item_type === 'result') {
        return (
            <div className="px-3 py-2 text-xs text-gray-400">
                <span className="text-gray-300">{item.prompt_name}</span>
                <span className="text-gray-500 ml-2">{item.provider_name} / {item.model_name}</span>
                {item.rating && <span className="text-amber-400 ml-2">{'*'.repeat(item.rating)}</span>}
            </div>
        );
    }
    if (item.item_type === 'collection') {
        return (
            <div className="px-3 py-2 text-xs text-gray-400">
                <span className="text-indigo-400">{item.title}</span>
                {item.children_count != null && (
                    <span className="text-gray-600 ml-2">{item.children_count} items</span>
                )}
            </div>
        );
    }
    return null;
}

function CollectionCard({ collection }) {
    const [expanded, setExpanded] = useState(false);

    const { data: detailData } = useQuery({
        queryKey: ['browse', 'collection', collection.slug],
        queryFn: () => getCollection(collection.slug),
        enabled: expanded,
    });

    const items = detailData?.data?.items ?? [];

    return (
        <div className="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
            <button
                onClick={() => setExpanded(!expanded)}
                className="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-750 transition-colors text-left"
            >
                <div>
                    <h3 className="text-sm font-medium text-gray-100">{collection.title}</h3>
                    {collection.description && (
                        <p className="text-xs text-gray-500 mt-0.5 truncate">{collection.description}</p>
                    )}
                </div>
                <span className="text-gray-500 text-xs">{expanded ? '−' : '+'}</span>
            </button>
            {expanded && (
                <div className="border-t border-gray-700">
                    {items.length === 0 ? (
                        <p className="px-4 py-3 text-xs text-gray-600">Empty collection</p>
                    ) : (
                        items.map(item => <CollectionItem key={item.id} item={item} />)
                    )}
                </div>
            )}
        </div>
    );
}

export default function CollectionList() {
    const { data, isLoading } = useQuery({
        queryKey: ['browse', 'collections'],
        queryFn: () => listCollections(),
    });

    const collections = data?.data ?? [];

    if (isLoading) {
        return (
            <div className="flex justify-center py-12">
                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    if (collections.length === 0) {
        return <p className="text-center py-12 text-gray-500">No collections yet.</p>;
    }

    return (
        <div className="space-y-3">
            {collections.map(c => <CollectionCard key={c.id} collection={c} />)}
        </div>
    );
}
```

- [ ] **Step 2: Wire into BrowsePage**

In `resources/js/spa/pages/BrowsePage.jsx`, add the import at the top:

```jsx
import CollectionList from '../components/browse/CollectionList.jsx';
```

Replace the collections placeholder:

```jsx
// Before:
{tab === 'collections' && (
    <div className="text-gray-500 text-sm py-4">Loading collections...</div>
)}

// After:
{tab === 'collections' && <CollectionList />}
```

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add resources/js/spa/components/browse/CollectionList.jsx resources/js/spa/pages/BrowsePage.jsx public/build/
git commit -m "feat: Collections tab with expand/collapse and nested items"
```

---

## Task 7: Browse Page — Starred Results Tab

**Files:**
- Create: `resources/js/spa/components/browse/StarredResults.jsx`
- Modify: `resources/js/spa/pages/BrowsePage.jsx`

- [ ] **Step 1: Create StarredResults component**

Create `resources/js/spa/components/browse/StarredResults.jsx`:

```jsx
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { listStarredResults } from '../../api/results.js';

export default function StarredResults() {
    const [page, setPage] = useState(1);

    const { data, isLoading } = useQuery({
        queryKey: ['browse', 'starred', page],
        queryFn: () => listStarredResults({ page }),
    });

    const results = data?.data ?? [];
    const pagination = data?.meta ?? {};

    if (isLoading) {
        return (
            <div className="flex justify-center py-12">
                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    if (results.length === 0) {
        return <p className="text-center py-12 text-gray-500">No starred results yet.</p>;
    }

    return (
        <>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {results.map(r => {
                    const owner = r.prompt?.creator?.username || r.prompt?.creator?.name;
                    return (
                        <div key={r.id} className="bg-gray-800 border border-gray-700 rounded-lg p-4">
                            <div className="flex items-start justify-between mb-2">
                                <Link
                                    to={`/workspace/${owner}/${r.prompt?.slug}`}
                                    className="text-sm font-medium text-indigo-400 hover:underline"
                                >
                                    {r.prompt?.name}
                                </Link>
                                <span className="text-amber-400 text-xs">
                                    {'*'.repeat(r.rating || 0)}
                                </span>
                            </div>
                            <p className="text-xs text-gray-300 line-clamp-3 mb-2">{r.response_text}</p>
                            <div className="flex items-center gap-3 text-[10px] text-gray-500">
                                {r.provider_name && <span>{r.provider_name}</span>}
                                {r.model_name && <span>{r.model_name}</span>}
                                {r.prompt_version && <span>v{r.prompt_version.version_number}</span>}
                            </div>
                        </div>
                    );
                })}
            </div>
            {pagination.last_page > 1 && (
                <div className="flex justify-center gap-2 mt-6">
                    <button
                        onClick={() => setPage(p => Math.max(1, p - 1))}
                        disabled={page <= 1}
                        className="text-sm text-gray-400 hover:text-white disabled:opacity-30 px-3 py-1"
                    >
                        Prev
                    </button>
                    <span className="text-sm text-gray-500">{page} / {pagination.last_page}</span>
                    <button
                        onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))}
                        disabled={page >= pagination.last_page}
                        className="text-sm text-gray-400 hover:text-white disabled:opacity-30 px-3 py-1"
                    >
                        Next
                    </button>
                </div>
            )}
        </>
    );
}
```

- [ ] **Step 2: Wire into BrowsePage**

In `resources/js/spa/pages/BrowsePage.jsx`, add the import:

```jsx
import StarredResults from '../components/browse/StarredResults.jsx';
```

Replace the starred placeholder:

```jsx
// Before:
{tab === 'starred' && (
    <div className="text-gray-500 text-sm py-4">Loading starred results...</div>
)}

// After:
{tab === 'starred' && <StarredResults />}
```

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add resources/js/spa/components/browse/StarredResults.jsx resources/js/spa/pages/BrowsePage.jsx public/build/
git commit -m "feat: Starred results tab with pagination"
```

---

## Task 8: Integration + Verify

- [ ] **Step 1: Build**

```bash
npm run build
```

- [ ] **Step 2: Run PHP tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 351+ tests pass.

- [ ] **Step 3: Manual E2E test**

1. Go to `http://127.0.0.1:8000/` → redirects to `/app/browse`
2. Login if needed → lands on `/app/browse`
3. Sidebar visible with Browse (active), Canvas, Settings, Teams icons
4. Browse shows prompts tab by default with card grid
5. Switch to Fragments tab → shows fragments
6. Search → filters grid
7. Category dropdown → filters grid
8. Scope dropdown (mine/shared/all) → filters grid
9. "+ New" → create form, create prompt → navigates to workspace
10. Workspace shows sidebar still visible
11. Click Canvas in sidebar → canvas page
12. Click Browse in sidebar → back to browse
13. Collections tab → shows collections with expand/collapse
14. Starred tab → shows starred results
15. Settings in sidebar → goes to Livewire settings page
16. Teams in sidebar → goes to Livewire teams page

- [ ] **Step 4: Build and push**

```bash
npm run build
git add -A
git commit -m "feat: React UI integration — sidebar nav, Browse page, redirect chain"
git push
```

---

## Verification Summary

| Feature | How to verify |
|---------|--------------|
| Root redirect | `/` → `/app/browse` |
| Post-login redirect | Login → `/app/browse` |
| Sidebar navigation | Icon rail visible on all pages |
| Sidebar active state | Active page has indigo highlight |
| Browse prompts | Card grid with name, category, tags, version |
| Browse fragments | Switch tab, shows fragment cards |
| Search filter | Type → grid updates |
| Category filter | Select → grid filters |
| Scope filter | mine/shared/all → grid changes |
| Create prompt | + New → form → workspace |
| Collections tab | Expand/collapse, shows items |
| Starred tab | Shows starred results with prompt links |
| Workspace via card | Click card → workspace with sidebar |
| Canvas via sidebar | Click canvas icon → canvas page |
| Settings link | Goes to Livewire `/settings` |
| Teams link | Goes to Livewire `/teams` |
| PHP tests | 351+ pass |
