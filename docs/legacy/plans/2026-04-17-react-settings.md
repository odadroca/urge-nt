# React Settings Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port all 6 Settings tabs from Livewire to React at `/app/settings`, with 15 new API endpoints and role-based tab visibility.

**Architecture:** New API controllers for API Keys, Users, and Evaluation Settings. Extend existing LlmProviderController and CategoryController. React SettingsPage orchestrates 6 tab components, each fetching data via React Query. Role-based visibility from `useAuth().user.role`.

**Tech Stack:** Laravel 12 / PHP 8.3, React 19, React Query v5, Axios, Tailwind CSS
**PHP Path:** `C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`
**Spec:** `docs/superpowers/specs/2026-04-17-react-settings-design.md`

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `app/Http/Controllers/Api/ApiKeyController.php` | API key CRUD (list, create, toggle, delete) |
| `app/Http/Controllers/Api/UserController.php` | User CRUD (admin-only) |
| `app/Http/Controllers/Api/EvaluationSettingsController.php` | Evaluation settings get/save |
| `resources/js/spa/api/apiKeys.js` | API key API wrappers |
| `resources/js/spa/api/users.js` | User API wrappers |
| `resources/js/spa/api/evaluationSettings.js` | Evaluation settings API wrappers |
| `resources/js/spa/api/pipelines.js` | Pipeline + channel API wrappers |
| `resources/js/spa/components/settings/ApiKeysTab.jsx` | API key management UI |
| `resources/js/spa/components/settings/LlmProvidersTab.jsx` | Provider CRUD + test connection |
| `resources/js/spa/components/settings/CategoriesTab.jsx` | Category CRUD + color picker |
| `resources/js/spa/components/settings/PipelinesTab.jsx` | Pipeline accordion + channel editing |
| `resources/js/spa/components/settings/EvaluationTab.jsx` | Evaluation config UI |
| `resources/js/spa/components/settings/UserManagementTab.jsx` | User CRUD (admin) |

### Modified Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/LlmProviderController.php` | Add store, update, destroy, test |
| `app/Http/Controllers/Api/CategoryController.php` | Add update, destroy |
| `resources/js/spa/api/providers.js` | Add CRUD + test wrappers |
| `resources/js/spa/api/categories.js` | Add update, delete wrappers |
| `resources/js/spa/pages/SettingsPage.jsx` | Full rewrite — tabbed settings |
| `resources/js/spa/components/Sidebar.jsx` | Change Settings to SPA link |
| `routes/api.php` | Add all new routes |

---

## Task 1: API Key Controller + Routes

**Files:**
- Create: `app/Http/Controllers/Api/ApiKeyController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create ApiKeyController**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiKey;
use App\Models\Prompt;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $keys = ApiKey::where('user_id', $request->user()->id)
            ->with('prompts:id,name,slug')
            ->withCount('prompts')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($keys);
    }

    public function store(Request $request, ApiKeyService $service): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'prompt_ids'  => 'nullable|array',
            'prompt_ids.*' => 'integer|exists:prompts,id',
        ]);

        $result = $service->generateKey(
            $request->user(),
            $validated['name'],
            $validated['prompt_ids'] ?? []
        );

        return $this->success([
            'key'   => $result['key'],
            'model' => $result['model']->load('prompts:id,name,slug'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $key = ApiKey::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $key->update($validated);

        return $this->success($key->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $key = ApiKey::where('user_id', $request->user()->id)->findOrFail($id);
        $key->delete();

        return $this->success(['message' => 'API key deleted.']);
    }
}
```

- [ ] **Step 2: Add routes**

In `routes/api.php`, add the import at top:

```php
use App\Http\Controllers\Api\ApiKeyController;
```

Inside the `dual.auth` group, add:

```php
// API Keys
Route::get('api-keys', [ApiKeyController::class, 'index']);
Route::post('api-keys', [ApiKeyController::class, 'store']);
Route::patch('api-keys/{id}', [ApiKeyController::class, 'update']);
Route::delete('api-keys/{id}', [ApiKeyController::class, 'destroy']);
```

- [ ] **Step 3: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 376+ tests pass.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/ApiKeyController.php routes/api.php
git commit -m "feat: API key CRUD endpoints"
```

---

## Task 2: Extend LlmProviderController + Routes

**Files:**
- Modify: `app/Http/Controllers/Api/LlmProviderController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Rewrite LlmProviderController with full CRUD + test**

Replace `app/Http/Controllers/Api/LlmProviderController.php` with:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\LlmProvider;
use App\Services\LlmDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlmProviderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = LlmProvider::orderBy('name');

        // Non-admins only see active providers
        if (!$request->user()->isAdmin()) {
            $query->where('is_active', true);
        }

        $providers = $query->get(['id', 'name', 'driver', 'model', 'endpoint', 'is_active']);

        return $this->success($providers);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'driver'   => 'required|in:openai,anthropic,mistral,gemini,ollama,openrouter',
            'api_key'  => 'nullable|string',
            'model'    => 'required|string|max:255',
            'endpoint' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $provider = LlmProvider::create([
            'name'      => $validated['name'],
            'driver'    => $validated['driver'],
            'api_key'   => $validated['api_key'] ?? null,
            'model'     => $validated['model'],
            'endpoint'  => $validated['endpoint'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->success($provider, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $provider = LlmProvider::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'driver'    => 'sometimes|required|in:openai,anthropic,mistral,gemini,ollama,openrouter',
            'api_key'   => 'nullable|string',
            'model'     => 'sometimes|required|string|max:255',
            'endpoint'  => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        // Only update api_key if a non-empty value was provided
        if (array_key_exists('api_key', $validated) && ($validated['api_key'] === null || $validated['api_key'] === '')) {
            unset($validated['api_key']);
        }

        $provider->update($validated);

        return $this->success($provider->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        LlmProvider::findOrFail($id)->delete();

        return $this->success(['message' => 'Provider deleted.']);
    }

    public function test(Request $request, int $id, LlmDispatchService $dispatchService): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $provider = LlmProvider::findOrFail($id);

        try {
            $result = $dispatchService->dispatch($provider, 'Say "Connection successful" in exactly two words.');

            if ($result->success) {
                return $this->success([
                    'status'  => 'success',
                    'message' => "Connected: {$result->modelUsed} ({$result->durationMs}ms)",
                ]);
            }

            return $this->success([
                'status'  => 'error',
                'message' => "Failed: {$result->error}",
            ]);
        } catch (\Throwable $e) {
            return $this->success([
                'status'  => 'error',
                'message' => "Error: {$e->getMessage()}",
            ]);
        }
    }
}
```

- [ ] **Step 2: Add routes**

In `routes/api.php`, replace the single providers GET route with:

```php
// LLM Providers
Route::get('providers', [LlmProviderController::class, 'index']);
Route::post('providers', [LlmProviderController::class, 'store']);
Route::patch('providers/{id}', [LlmProviderController::class, 'update']);
Route::delete('providers/{id}', [LlmProviderController::class, 'destroy']);
Route::post('providers/{id}/test', [LlmProviderController::class, 'test']);
```

- [ ] **Step 3: Run tests and commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Http/Controllers/Api/LlmProviderController.php routes/api.php
git commit -m "feat: LLM provider CRUD + test connection endpoints"
```

---

## Task 3: Extend CategoryController + Routes

**Files:**
- Modify: `app/Http/Controllers/Api/CategoryController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Add update and destroy methods to CategoryController**

Add after the existing `store` method:

```php
public function update(Request $request, int $id): JsonResponse
{
    if (!$request->user()->isEditor()) {
        return $this->error('Editor access required.', 403);
    }

    $category = Category::findOrFail($id);

    $validated = $request->validate([
        'name'  => 'sometimes|required|string|max:255',
        'color' => 'sometimes|required|string|max:30',
    ]);

    $category->update($validated);

    return $this->success($category->fresh());
}

public function destroy(Request $request, int $id): JsonResponse
{
    if (!$request->user()->isEditor()) {
        return $this->error('Editor access required.', 403);
    }

    $category = Category::findOrFail($id);
    $category->prompts()->update(['category_id' => null]);
    $category->delete();

    return $this->success(['message' => 'Category deleted.']);
}
```

Also update the `index` method to include prompt counts:

```php
public function index(): JsonResponse
{
    return $this->success(Category::withCount('prompts')->orderBy('name')->get());
}
```

- [ ] **Step 2: Add routes**

In `routes/api.php`, after the existing categories routes, add:

```php
Route::patch('categories/{id}', [CategoryController::class, 'update']);
Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
```

- [ ] **Step 3: Run tests and commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Http/Controllers/Api/CategoryController.php routes/api.php
git commit -m "feat: category update + delete endpoints"
```

---

## Task 4: EvaluationSettings + User Controllers + Routes

**Files:**
- Create: `app/Http/Controllers/Api/EvaluationSettingsController.php`
- Create: `app/Http/Controllers/Api/UserController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create EvaluationSettingsController**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\EvaluationSetting;
use App\Models\LlmProvider;
use App\Models\Prompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationSettingsController extends ApiController
{
    public function show(): JsonResponse
    {
        return $this->success([
            'enabled'             => (bool) EvaluationSetting::get('enabled', false),
            'auto_evaluate'       => (bool) EvaluationSetting::get('auto_evaluate', false),
            'default_provider_id' => EvaluationSetting::get('default_provider_id'),
            'prompt_slug'         => EvaluationSetting::get('prompt_slug', 'system-evaluation-template'),
            'dimensions'          => EvaluationSetting::get('dimensions', config('urge.evaluation.default_dimensions')),
            'providers'           => LlmProvider::where('is_active', true)->get(['id', 'name', 'model']),
            'eval_prompts'        => Prompt::where('type', 'fragment')->get(['id', 'slug', 'name']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled'             => 'boolean',
            'auto_evaluate'       => 'boolean',
            'default_provider_id' => 'nullable|integer',
            'prompt_slug'         => 'nullable|string',
            'dimensions'          => 'nullable|array',
        ]);

        foreach ($validated as $key => $value) {
            EvaluationSetting::set($key, $value);
        }

        return $this->success(['message' => 'Evaluation settings saved.']);
    }
}
```

- [ ] **Step 2: Create UserController**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        return $this->success(User::orderBy('name')->get(['id', 'name', 'slug', 'email', 'role', 'created_at']));
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:admin,editor,viewer',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        return $this->success($user->only(['id', 'name', 'slug', 'email', 'role', 'created_at']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return $this->error('Cannot change your own role.', 422);
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,editor,viewer',
        ]);

        $user->update($validated);

        return $this->success($user->fresh()->only(['id', 'name', 'slug', 'email', 'role', 'created_at']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Admin access required.', 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return $this->error('Cannot delete yourself.', 422);
        }

        $user->delete();

        return $this->success(['message' => 'User deleted.']);
    }
}
```

- [ ] **Step 3: Add routes**

Add imports at top of `routes/api.php`:

```php
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\EvaluationSettingsController;
use App\Http\Controllers\Api\UserController;
```

Inside the `dual.auth` group:

```php
// Evaluation Settings
Route::get('evaluation-settings', [EvaluationSettingsController::class, 'show']);
Route::patch('evaluation-settings', [EvaluationSettingsController::class, 'update']);

// Users (admin)
Route::get('users', [UserController::class, 'index']);
Route::post('users', [UserController::class, 'store']);
Route::patch('users/{id}', [UserController::class, 'update']);
Route::delete('users/{id}', [UserController::class, 'destroy']);
```

- [ ] **Step 4: Run tests and commit**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
git add app/Http/Controllers/Api/EvaluationSettingsController.php app/Http/Controllers/Api/UserController.php routes/api.php
git commit -m "feat: evaluation settings + user management API endpoints"
```

---

## Task 5: React API Wrappers

**Files:**
- Create: `resources/js/spa/api/apiKeys.js`
- Create: `resources/js/spa/api/users.js`
- Create: `resources/js/spa/api/evaluationSettings.js`
- Create: `resources/js/spa/api/pipelines.js`
- Modify: `resources/js/spa/api/providers.js`
- Modify: `resources/js/spa/api/categories.js`

- [ ] **Step 1: Create apiKeys.js**

```javascript
import client from './client.js';

export async function listApiKeys() {
    const { data } = await client.get('/api-keys');
    return data;
}

export async function createApiKey({ name, prompt_ids }) {
    const { data } = await client.post('/api-keys', { name, prompt_ids });
    return data;
}

export async function updateApiKey(id, { is_active }) {
    const { data } = await client.patch(`/api-keys/${id}`, { is_active });
    return data;
}

export async function deleteApiKey(id) {
    const { data } = await client.delete(`/api-keys/${id}`);
    return data;
}
```

- [ ] **Step 2: Create users.js**

```javascript
import client from './client.js';

export async function listUsers() {
    const { data } = await client.get('/users');
    return data;
}

export async function createUser({ name, email, password, role }) {
    const { data } = await client.post('/users', { name, email, password, role });
    return data;
}

export async function updateUser(id, { role }) {
    const { data } = await client.patch(`/users/${id}`, { role });
    return data;
}

export async function deleteUser(id) {
    const { data } = await client.delete(`/users/${id}`);
    return data;
}
```

- [ ] **Step 3: Create evaluationSettings.js**

```javascript
import client from './client.js';

export async function getEvaluationSettings() {
    const { data } = await client.get('/evaluation-settings');
    return data;
}

export async function saveEvaluationSettings(settings) {
    const { data } = await client.patch('/evaluation-settings', settings);
    return data;
}
```

- [ ] **Step 4: Create pipelines.js**

```javascript
import client from './client.js';

export async function listPipelines() {
    const { data } = await client.get('/pipelines');
    return data;
}

export async function createPipeline({ name, description }) {
    const { data } = await client.post('/pipelines', { name, description });
    return data;
}

export async function getPipeline(slug) {
    const { data } = await client.get(`/pipelines/${slug}`);
    return data;
}

export async function updatePipeline(slug, updates) {
    const { data } = await client.patch(`/pipelines/${slug}`, updates);
    return data;
}

export async function deletePipeline(slug) {
    const { data } = await client.delete(`/pipelines/${slug}`);
    return data;
}

export async function addChannel(pipelineSlug, channelData) {
    const { data } = await client.post(`/pipelines/${pipelineSlug}/channels`, channelData);
    return data;
}

export async function updateChannel(pipelineSlug, channelId, updates) {
    const { data } = await client.patch(`/pipelines/${pipelineSlug}/channels/${channelId}`, updates);
    return data;
}

export async function removeChannel(pipelineSlug, channelId) {
    const { data } = await client.delete(`/pipelines/${pipelineSlug}/channels/${channelId}`);
    return data;
}
```

- [ ] **Step 5: Extend providers.js**

Add to `resources/js/spa/api/providers.js`:

```javascript
export async function createProvider(data) {
    const { data: response } = await client.post('/providers', data);
    return response;
}

export async function updateProvider(id, data) {
    const { data: response } = await client.patch(`/providers/${id}`, data);
    return response;
}

export async function deleteProvider(id) {
    const { data: response } = await client.delete(`/providers/${id}`);
    return response;
}

export async function testProvider(id) {
    const { data: response } = await client.post(`/providers/${id}/test`);
    return response;
}
```

- [ ] **Step 6: Extend categories.js**

Read `resources/js/spa/api/categories.js` and add:

```javascript
export async function updateCategory(id, data) {
    const { data: response } = await client.patch(`/categories/${id}`, data);
    return response;
}

export async function deleteCategory(id) {
    const { data: response } = await client.delete(`/categories/${id}`);
    return response;
}
```

- [ ] **Step 7: Commit**

```bash
git add resources/js/spa/api/apiKeys.js resources/js/spa/api/users.js resources/js/spa/api/evaluationSettings.js resources/js/spa/api/pipelines.js resources/js/spa/api/providers.js resources/js/spa/api/categories.js
git commit -m "feat: React API wrappers for all settings tabs"
```

---

## Task 6: SettingsPage + Sidebar Update

**Files:**
- Rewrite: `resources/js/spa/pages/SettingsPage.jsx`
- Modify: `resources/js/spa/components/Sidebar.jsx`

- [ ] **Step 1: Rewrite SettingsPage**

The page renders a tab bar and the active tab component. Tab visibility is role-based via `useAuth().user.role`.

```jsx
import { useState } from 'react';
import useAuth from '../hooks/useAuth.js';
import ApiKeysTab from '../components/settings/ApiKeysTab.jsx';
import LlmProvidersTab from '../components/settings/LlmProvidersTab.jsx';
import CategoriesTab from '../components/settings/CategoriesTab.jsx';
import PipelinesTab from '../components/settings/PipelinesTab.jsx';
import EvaluationTab from '../components/settings/EvaluationTab.jsx';
import UserManagementTab from '../components/settings/UserManagementTab.jsx';

const TABS = [
    { key: 'api-keys', label: 'API Keys', roles: ['admin'] },
    { key: 'llm-providers', label: 'LLM Providers', roles: ['admin', 'editor', 'viewer'] },
    { key: 'categories', label: 'Categories', roles: ['admin', 'editor', 'viewer'] },
    { key: 'pipelines', label: 'Pipelines', roles: ['admin', 'editor'] },
    { key: 'evaluation', label: 'Evaluation', roles: ['admin', 'editor'] },
    { key: 'users', label: 'Users', roles: ['admin'] },
];

const TAB_COMPONENTS = {
    'api-keys': ApiKeysTab,
    'llm-providers': LlmProvidersTab,
    'categories': CategoriesTab,
    'pipelines': PipelinesTab,
    'evaluation': EvaluationTab,
    'users': UserManagementTab,
};

export default function SettingsPage() {
    const { user } = useAuth();
    const role = user?.role || 'viewer';

    const visibleTabs = TABS.filter(t => t.roles.includes(role));
    const [activeTab, setActiveTab] = useState(visibleTabs[0]?.key || 'llm-providers');

    const TabComponent = TAB_COMPONENTS[activeTab];

    return (
        <div className="h-full flex flex-col overflow-hidden">
            {/* Tab bar */}
            <div className="flex items-center gap-1 px-6 pt-4 border-b border-gray-700">
                {visibleTabs.map(t => (
                    <button
                        key={t.key}
                        onClick={() => setActiveTab(t.key)}
                        className={`px-4 py-2 text-sm transition-colors ${
                            activeTab === t.key
                                ? 'text-white border-b-2 border-indigo-500'
                                : 'text-gray-500 hover:text-gray-300'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {/* Tab content */}
            <div className="flex-1 overflow-y-auto p-6">
                {TabComponent && <TabComponent role={role} />}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Update Sidebar — Settings becomes SPA link**

In `resources/js/spa/components/Sidebar.jsx`, change the Settings entry in `bottomItems`:

```javascript
// Change spa: false to spa: true for settings
{
    key: 'settings',
    label: 'Settings',
    to: '/settings',
    spa: true,  // was: false
    icon: ( /* existing SVG stays the same */ ),
},
```

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add resources/js/spa/pages/SettingsPage.jsx resources/js/spa/components/Sidebar.jsx public/build/
git commit -m "feat: SettingsPage tabbed layout + Sidebar SPA link"
```

---

## Task 7: ApiKeysTab Component

**Files:**
- Create: `resources/js/spa/components/settings/ApiKeysTab.jsx`

- [ ] **Step 1: Create ApiKeysTab**

Full component with:
- Key list with name, preview, last used, active toggle, delete
- Create form with name input and prompt scope multi-select
- Generated key banner with copy-to-clipboard (shown once)

```jsx
import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { listApiKeys, createApiKey, updateApiKey, deleteApiKey } from '../../api/apiKeys.js';
import { listPrompts } from '../../api/prompts.js';

export default function ApiKeysTab() {
    const queryClient = useQueryClient();
    const [showForm, setShowForm] = useState(false);
    const [name, setName] = useState('');
    const [selectedPromptIds, setSelectedPromptIds] = useState([]);
    const [generatedKey, setGeneratedKey] = useState(null);
    const [copied, setCopied] = useState(false);
    const [saving, setSaving] = useState(false);

    const { data: keysData, isLoading } = useQuery({
        queryKey: ['settings', 'apiKeys'],
        queryFn: listApiKeys,
    });

    const { data: promptsData } = useQuery({
        queryKey: ['settings', 'prompts'],
        queryFn: () => listPrompts({ per_page: 100 }),
        enabled: showForm,
    });

    const keys = keysData?.data ?? [];
    const prompts = promptsData?.data ?? [];

    const handleCreate = useCallback(async () => {
        if (!name.trim() || saving) return;
        setSaving(true);
        try {
            const result = await createApiKey({ name: name.trim(), prompt_ids: selectedPromptIds });
            setGeneratedKey(result.data.key);
            setName('');
            setSelectedPromptIds([]);
            setShowForm(false);
            queryClient.invalidateQueries({ queryKey: ['settings', 'apiKeys'] });
        } catch (err) {
            console.error('Create key failed:', err);
        } finally {
            setSaving(false);
        }
    }, [name, selectedPromptIds, saving, queryClient]);

    const handleToggle = useCallback(async (id, currentActive) => {
        await updateApiKey(id, { is_active: !currentActive });
        queryClient.invalidateQueries({ queryKey: ['settings', 'apiKeys'] });
    }, [queryClient]);

    const handleDelete = useCallback(async (id) => {
        if (!confirm('Delete this API key?')) return;
        await deleteApiKey(id);
        queryClient.invalidateQueries({ queryKey: ['settings', 'apiKeys'] });
    }, [queryClient]);

    const handleCopy = useCallback(() => {
        navigator.clipboard.writeText(generatedKey);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }, [generatedKey]);

    return (
        <div className="max-w-3xl space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-100">API Keys</h2>
                <button
                    onClick={() => setShowForm(!showForm)}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-1.5 rounded"
                >
                    + New Key
                </button>
            </div>

            {/* Generated key banner */}
            {generatedKey && (
                <div className="bg-green-900/50 border border-green-700 rounded-lg p-4">
                    <p className="text-xs text-green-400 mb-2">Key generated — copy it now, it won't be shown again:</p>
                    <div className="flex items-center gap-2">
                        <code className="flex-1 bg-gray-900 text-green-300 text-xs px-3 py-2 rounded font-mono break-all">{generatedKey}</code>
                        <button onClick={handleCopy} className="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-2 rounded shrink-0">
                            {copied ? 'Copied!' : 'Copy'}
                        </button>
                    </div>
                    <button onClick={() => setGeneratedKey(null)} className="text-xs text-green-600 mt-2 hover:text-green-400">Dismiss</button>
                </div>
            )}

            {/* Create form */}
            {showForm && (
                <div className="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-3">
                    <input
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="Key name..."
                        className="w-full bg-gray-900 border border-gray-600 text-gray-200 text-sm rounded px-3 py-2 outline-none focus:border-indigo-500"
                        onKeyDown={(e) => e.key === 'Enter' && handleCreate()}
                    />
                    {prompts.length > 0 && (
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">Scope to prompts (optional):</label>
                            <div className="max-h-32 overflow-y-auto space-y-1">
                                {prompts.map(p => (
                                    <label key={p.id} className="flex items-center gap-2 text-xs text-gray-300">
                                        <input
                                            type="checkbox"
                                            checked={selectedPromptIds.includes(p.id)}
                                            onChange={() => setSelectedPromptIds(prev =>
                                                prev.includes(p.id) ? prev.filter(x => x !== p.id) : [...prev, p.id]
                                            )}
                                            className="rounded border-gray-600 bg-gray-900 text-indigo-600"
                                        />
                                        {p.name}
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}
                    <div className="flex gap-2">
                        <button onClick={handleCreate} disabled={!name.trim() || saving} className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-1.5 rounded disabled:opacity-50">
                            {saving ? 'Creating...' : 'Create'}
                        </button>
                        <button onClick={() => setShowForm(false)} className="text-gray-400 text-sm px-4 py-1.5">Cancel</button>
                    </div>
                </div>
            )}

            {/* Key list */}
            {isLoading ? (
                <div className="flex justify-center py-8">
                    <div className="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full" />
                </div>
            ) : keys.length === 0 ? (
                <p className="text-gray-500 text-sm py-4">No API keys yet.</p>
            ) : (
                <div className="space-y-2">
                    {keys.map(key => (
                        <div key={key.id} className="bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 flex items-center justify-between">
                            <div>
                                <span className="text-sm text-gray-200 font-medium">{key.name}</span>
                                <div className="text-xs text-gray-500 mt-0.5">
                                    <code>{key.key_preview}...</code>
                                    {key.last_used_at && <span className="ml-3">Last used: {new Date(key.last_used_at).toLocaleDateString()}</span>}
                                    {key.prompts_count > 0 && <span className="ml-3">Scoped to {key.prompts_count} prompt(s)</span>}
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <button
                                    onClick={() => handleToggle(key.id, key.is_active)}
                                    className={`text-xs px-2 py-1 rounded ${key.is_active ? 'bg-green-900/50 text-green-400' : 'bg-gray-700 text-gray-500'}`}
                                >
                                    {key.is_active ? 'Active' : 'Inactive'}
                                </button>
                                <button onClick={() => handleDelete(key.id)} className="text-gray-500 hover:text-red-400 text-sm">&times;</button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 2: Build and commit**

```bash
npm run build
git add resources/js/spa/components/settings/ApiKeysTab.jsx public/build/
git commit -m "feat: API Keys settings tab"
```

---

## Task 8: LlmProvidersTab Component

**Files:**
- Create: `resources/js/spa/components/settings/LlmProvidersTab.jsx`

- [ ] **Step 1: Create LlmProvidersTab**

Full component with provider list, create/edit form, driver-conditional fields, test connection, active toggle, delete. Non-admins see read-only list.

The component should:
- Fetch providers via `listProviders()` (already shows all for admins)
- Create/edit form with: name, driver dropdown, api_key (hidden for ollama), model, endpoint (shown for openai/ollama)
- Test connection button per provider
- Active toggle per provider
- Delete with confirmation
- Non-admins: read-only list (no edit/delete buttons)

This is a large component (~200 lines). Follow the exact same patterns as ApiKeysTab — useState for form state, useCallback for handlers, useQuery + queryClient.invalidateQueries for data management. Import `listProviders, createProvider, updateProvider, deleteProvider, testProvider` from `../../api/providers.js`.

Key details:
- Driver options: `['openai', 'anthropic', 'mistral', 'gemini', 'ollama', 'openrouter']`
- When driver is 'ollama': api_key field hidden
- When driver is 'openai' or 'ollama': show endpoint field
- Edit mode: clear api_key field (never expose existing key), only update if non-empty value provided
- Test result: show inline success/error message below the provider row

- [ ] **Step 2: Build and commit**

```bash
npm run build
git add resources/js/spa/components/settings/LlmProvidersTab.jsx public/build/
git commit -m "feat: LLM Providers settings tab"
```

---

## Task 9: CategoriesTab Component

**Files:**
- Create: `resources/js/spa/components/settings/CategoriesTab.jsx`

- [ ] **Step 1: Create CategoriesTab**

Component with category list, create/edit form, 18-color palette picker. Non-editors see read-only list.

Color palette (18 colors with hex values):

```javascript
const COLORS = {
    gray: '#6b7280', red: '#ef4444', orange: '#f97316', amber: '#f59e0b',
    yellow: '#eab308', lime: '#84cc16', green: '#22c55e', emerald: '#10b981',
    teal: '#14b8a6', cyan: '#06b6d4', sky: '#0ea5e9', blue: '#3b82f6',
    indigo: '#6366f1', violet: '#8b5cf6', purple: '#a855f7', fuchsia: '#d946ef',
    pink: '#ec4899', rose: '#f43f5e',
};
```

Color picker UI: grid of circles (`w-6 h-6 rounded-full cursor-pointer`), selected one gets `ring-2 ring-white scale-110`.

Each category row shows: color swatch, name, prompt count, edit/delete buttons (editor+ only).

Import `listCategories` from existing `../../api/categories.js`, plus the new `updateCategory, deleteCategory`.

- [ ] **Step 2: Build and commit**

```bash
npm run build
git add resources/js/spa/components/settings/CategoriesTab.jsx public/build/
git commit -m "feat: Categories settings tab with color picker"
```

---

## Task 10: PipelinesTab Component

**Files:**
- Create: `resources/js/spa/components/settings/PipelinesTab.jsx`

- [ ] **Step 1: Create PipelinesTab**

Expandable accordion for pipelines with nested channel editing. Uses all pipeline API endpoints (which already exist).

Features:
- Pipeline list with: name, description, channel count, active dot, expand/collapse
- Create pipeline form: name + description
- Expanded pipeline: channel list with inline add/edit/delete
- Channel form: role_label, provider select (from active providers), system_prompt textarea, trigger (parallel/synthesis), sort_order
- Active toggle per pipeline (via `updatePipeline(slug, { is_active: !current })`)
- Delete pipeline with confirmation

Import from `../../api/pipelines.js` and `../../api/providers.js` (for provider select dropdown).

When a pipeline is expanded, fetch its details with channels via `getPipeline(slug)`.

- [ ] **Step 2: Build and commit**

```bash
npm run build
git add resources/js/spa/components/settings/PipelinesTab.jsx public/build/
git commit -m "feat: Pipelines settings tab with channel management"
```

---

## Task 11: EvaluationTab Component

**Files:**
- Create: `resources/js/spa/components/settings/EvaluationTab.jsx`

- [ ] **Step 1: Create EvaluationTab**

Configuration UI for evaluation settings. Fetches settings via `getEvaluationSettings()`, saves via `saveEvaluationSettings()`.

Features:
- Enable/disable toggle checkbox
- Auto-evaluate toggle checkbox
- Provider dropdown (from settings response `providers` field)
- Evaluation prompt dropdown (from settings response `eval_prompts` field)
- Dimensions table:
  - Columns: enabled (checkbox), name, description, weight (number input 0-5 step 0.1), delete (only for non-builtin)
  - Add custom dimension form: name + description inputs, "Add" button
- Save button at bottom

All state is local until user clicks Save, which sends the full settings object to the API.

- [ ] **Step 2: Build and commit**

```bash
npm run build
git add resources/js/spa/components/settings/EvaluationTab.jsx public/build/
git commit -m "feat: Evaluation settings tab"
```

---

## Task 12: UserManagementTab Component

**Files:**
- Create: `resources/js/spa/components/settings/UserManagementTab.jsx`

- [ ] **Step 1: Create UserManagementTab**

Admin-only user CRUD. Self-protection: cannot change own role or delete self.

Features:
- User list: name (with "You" badge for current user), email, role dropdown, delete button
- Role dropdown: admin/editor/viewer — disabled for current user, onChange calls `updateUser(id, { role })`
- Create form: name, email, password, role select
- Delete with confirmation — disabled for current user

Import from `../../api/users.js`. Use `useAuth().user.id` to identify current user for self-protection.

- [ ] **Step 2: Build and commit**

```bash
npm run build
git add resources/js/spa/components/settings/UserManagementTab.jsx public/build/
git commit -m "feat: User Management settings tab"
```

---

## Task 13: Integration + Verify

- [ ] **Step 1: Build**

```bash
npm run build
```

- [ ] **Step 2: Run PHP tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 376+ tests pass.

- [ ] **Step 3: Manual E2E test**

1. Login as admin → `/app/settings` shows all 6 tabs
2. API Keys: create key → copy → toggle active → delete
3. LLM Providers: create with openai driver → fields show correctly → test connection → edit (api_key blank) → toggle active → delete
4. Categories: create with blue color → edit → delete
5. Pipelines: create → expand → add channel → edit channel → delete channel → toggle active → delete pipeline
6. Evaluation: toggle enabled → select provider → add dimension → adjust weight → save → refresh → settings persisted
7. Users: create user → change role → delete user → verify self-protection
8. Login as editor → sees 4 tabs (LLM Providers read-only, Categories, Pipelines, Evaluation)
9. Login as viewer → sees 2 tabs (LLM Providers read-only, Categories read-only)
10. Sidebar Settings icon navigates within SPA (no page reload)

- [ ] **Step 4: Commit and push**

```bash
npm run build
git add -A
git commit -m "feat: React Settings page — all 6 tabs migrated from Livewire"
git push
```

---

## Verification Summary

| Feature | How to verify |
|---------|--------------|
| API Keys CRUD | Create, toggle, delete via UI |
| LLM Providers CRUD | Create with each driver, test, edit, delete |
| Test connection | Click test → see success/error message |
| Categories CRUD | Create with color, edit, delete |
| Color picker | 18 circles, selected has ring |
| Pipelines CRUD | Create, expand, add/edit/delete channels |
| Pipeline toggle | Active/inactive with green/gray dot |
| Evaluation config | Toggle, select provider, dimensions table, save |
| User CRUD | Create, change role, delete |
| Self-protection | Cannot change own role or delete self |
| Role visibility | Admin: 6 tabs, Editor: 4, Viewer: 2 |
| Sidebar SPA link | Settings click stays in SPA |
| PHP tests | 376+ pass |
| Build | Clean |
