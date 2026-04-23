# Phase 3: Sanctum + SPA Auth Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the temporary session-cookie auth hack with proper Laravel Sanctum SPA authentication, add login/logout API endpoints, and build a React LoginPage with auth state management.

**Architecture:** Sanctum SPA mode (cookie-based CSRF) for same-domain React SPA. Existing Bearer token auth for API/MCP consumers unchanged. DualAuthentication middleware updated to use Sanctum's `EnsureFrontendRequestsAreStateful` for session handling instead of manually prepending EncryptCookies + StartSession.

**Tech Stack:** Laravel Sanctum, React, React Query

**Spec:** `docs/superpowers/specs/2026-04-08-react-flow-migration-design.md` (Phase 3 section)
**PHP Path:** `C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `app/Http/Controllers/Api/AuthController.php` | Login, logout, user endpoints |
| `resources/js/spa/hooks/useAuth.js` | React Query hook for auth state |
| `resources/js/spa/components/ProtectedRoute.jsx` | Redirect to login if not authenticated |
| `resources/js/spa/pages/LoginPage.jsx` | Login form |
| `resources/js/spa/api/auth.js` | Auth API wrappers |

### Modified Files

| File | Change |
|------|--------|
| `composer.json` | Add laravel/sanctum |
| `bootstrap/app.php` | Add Sanctum stateful middleware to api group |
| `config/sanctum.php` | Published config with stateful domains |
| `config/cors.php` | Published config with supports_credentials |
| `routes/api.php` | Add auth routes, simplify graph routes middleware |
| `resources/js/spa/App.jsx` | Add login route, wrap routes in ProtectedRoute |
| `resources/js/spa/components/Layout.jsx` | Add logout button |

---

## Task 1: Install Sanctum + Backend Auth

**Files:**
- Modify: `composer.json` (via composer require)
- Create: `config/sanctum.php` (via vendor:publish)
- Create: `config/cors.php` (publish and configure)
- Modify: `bootstrap/app.php` (add Sanctum stateful middleware)
- Modify: `routes/api.php` (add auth routes, simplify graph middleware)
- Create: `app/Http/Controllers/Api/AuthController.php`

- [ ] **Step 1: Install Sanctum**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -d memory_limit=512M /usr/bin/env COMPOSER_ALLOW_SUPERUSER=1 composer require laravel/sanctum
```

If composer is not in PATH, find it first or use the PHP binary to run it.

- [ ] **Step 2: Publish Sanctum config**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

- [ ] **Step 3: Configure sanctum.php**

In `config/sanctum.php`, set the `stateful` domains:

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:8080,localhost:5173,127.0.0.1,127.0.0.1:8080')),
```

- [ ] **Step 4: Publish and configure CORS**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan config:publish cors
```

Then in `config/cors.php`, set:
- `'supports_credentials' => true`
- `'allowed_origins' => ['*']` (keep default, or restrict to localhost)

- [ ] **Step 5: Add Sanctum stateful middleware to API**

In `bootstrap/app.php`, add Sanctum's `EnsureFrontendRequestsAreStateful` to the api middleware group:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role'     => \App\Http\Middleware\RequireRole::class,
        'api.auth' => \App\Http\Middleware\ApiKeyAuthentication::class,
        'spa.auth'  => 'auth:sanctum',
        'dual.auth' => \App\Http\Middleware\DualAuthentication::class,
    ]);

    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);
})
```

This makes ALL API routes session-aware for requests from stateful domains. This means:
- The graph routes no longer need the manual EncryptCookies + StartSession hack
- DualAuthentication can use `$request->user()` directly since Sanctum handles session bootstrapping

- [ ] **Step 6: Simplify graph routes in routes/api.php**

Remove the manual session middleware from graph routes:

From:
```php
Route::middleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Session\Middleware\StartSession::class,
    'dual.auth',
])->group(function () {
```

To:
```php
Route::middleware('dual.auth')->group(function () {
```

- [ ] **Step 7: Simplify DualAuthentication middleware**

Since Sanctum now handles sessions on API routes, simplify:

```php
class DualAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sanctum handles session auth for stateful requests.
        // $request->user() works for both session (SPA) and Sanctum tokens.
        if ($request->user()) {
            return $next($request);
        }

        // Fall back to API key auth (Bearer token for external consumers)
        return app(ApiKeyAuthentication::class)->handle($request, $next);
    }
}
```

- [ ] **Step 8: Create AuthController**

```php
<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        return $this->success([
            'user' => $request->user()->only('id', 'name', 'email', 'slug', 'role'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->success(['message' => 'Logged out']);
    }

    public function user(Request $request): JsonResponse
    {
        return $this->success([
            'user' => $request->user()->only('id', 'name', 'email', 'slug', 'role'),
        ]);
    }
}
```

- [ ] **Step 9: Add auth routes to routes/api.php**

Inside the `Route::prefix('v1')` group, add:

```php
use App\Http\Controllers\Api\AuthController;

// SPA auth endpoints (Sanctum session-based)
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/user', [AuthController::class, 'user']);
});
```

- [ ] **Step 10: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: All 351+ tests pass. Graph tests still work through DualAuthentication.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: install Sanctum, add auth endpoints, simplify dual-auth"
```

---

## Task 2: React Auth Components

**Files:**
- Create: `resources/js/spa/api/auth.js`
- Create: `resources/js/spa/hooks/useAuth.js`
- Create: `resources/js/spa/components/ProtectedRoute.jsx`
- Create: `resources/js/spa/pages/LoginPage.jsx`
- Modify: `resources/js/spa/App.jsx` (add login route, protect routes)
- Modify: `resources/js/spa/components/Layout.jsx` (add user info + logout)

- [ ] **Step 1: Create auth API module**

```javascript
// resources/js/spa/api/auth.js
import client from './client.js';

export async function login(email, password) {
    // Get CSRF cookie first (Sanctum requirement)
    await client.get('/sanctum/csrf-cookie', { baseURL: '/' });
    const { data } = await client.post('/auth/login', { email, password });
    return data;
}

export async function logout() {
    const { data } = await client.post('/auth/logout');
    return data;
}

export async function getUser() {
    const { data } = await client.get('/auth/user');
    return data;
}
```

Note: The CSRF cookie endpoint is at `/sanctum/csrf-cookie` (no `/api/v1` prefix), so we override baseURL.

- [ ] **Step 2: Create useAuth hook**

```jsx
// resources/js/spa/hooks/useAuth.js
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { login as loginApi, logout as logoutApi, getUser } from '../api/auth.js';
import { useCallback } from 'react';

export default function useAuth() {
    const queryClient = useQueryClient();

    const { data, isLoading, error } = useQuery({
        queryKey: ['auth', 'user'],
        queryFn: getUser,
        retry: false,
        staleTime: 5 * 60 * 1000, // 5 minutes
    });

    const user = data?.data?.user ?? null;
    const isAuthenticated = !!user;

    const login = useCallback(async (email, password) => {
        const result = await loginApi(email, password);
        queryClient.invalidateQueries({ queryKey: ['auth'] });
        return result;
    }, [queryClient]);

    const logout = useCallback(async () => {
        await logoutApi();
        queryClient.clear();
        window.location.href = '/app/login';
    }, [queryClient]);

    return { user, isAuthenticated, isLoading, error, login, logout };
}
```

- [ ] **Step 3: Create ProtectedRoute**

```jsx
// resources/js/spa/components/ProtectedRoute.jsx
import { Navigate } from 'react-router-dom';
import useAuth from '../hooks/useAuth.js';

export default function ProtectedRoute({ children }) {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="h-screen flex items-center justify-center bg-gray-900 text-gray-400">
                <div className="animate-spin h-8 w-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    return children;
}
```

- [ ] **Step 4: Create LoginPage**

```jsx
// resources/js/spa/pages/LoginPage.jsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import useAuth from '../hooks/useAuth.js';

export default function LoginPage() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const { login, isAuthenticated } = useAuth();
    const navigate = useNavigate();

    if (isAuthenticated) {
        navigate('/canvas', { replace: true });
        return null;
    }

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await login(email, password);
            navigate('/canvas');
        } catch (err) {
            setError(err.response?.data?.message || err.response?.data?.errors?.email?.[0] || 'Login failed');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-900">
            <div className="w-full max-w-sm">
                <h1 className="text-2xl font-bold text-indigo-400 text-center mb-8">URGE</h1>
                <form onSubmit={handleSubmit} className="bg-gray-800 border border-gray-700 rounded-xl p-6 space-y-4">
                    <h2 className="text-lg font-semibold text-gray-100 text-center">Sign in</h2>

                    {error && (
                        <div className="bg-red-900/50 border border-red-700 text-red-300 text-sm px-3 py-2 rounded">
                            {error}
                        </div>
                    )}

                    <div>
                        <label className="block text-sm text-gray-400 mb-1">Email</label>
                        <input
                            type="email" value={email} onChange={(e) => setEmail(e.target.value)}
                            required autoFocus
                            className="w-full bg-gray-900 border border-gray-600 text-gray-100 rounded px-3 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        />
                    </div>

                    <div>
                        <label className="block text-sm text-gray-400 mb-1">Password</label>
                        <input
                            type="password" value={password} onChange={(e) => setPassword(e.target.value)}
                            required
                            className="w-full bg-gray-900 border border-gray-600 text-gray-100 rounded px-3 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        />
                    </div>

                    <button
                        type="submit" disabled={loading}
                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg font-medium disabled:opacity-50"
                    >
                        {loading ? 'Signing in...' : 'Sign in'}
                    </button>

                    <p className="text-center text-sm text-gray-500">
                        <a href="/register" className="text-indigo-400 hover:underline">Create account</a>
                    </p>
                </form>
            </div>
        </div>
    );
}
```

- [ ] **Step 5: Update App.jsx**

```jsx
import { Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout.jsx';
import ProtectedRoute from './components/ProtectedRoute.jsx';
import CanvasPage from './pages/CanvasPage.jsx';
import WorkspacePage from './pages/WorkspacePage.jsx';
import BrowsePage from './pages/BrowsePage.jsx';
import SettingsPage from './pages/SettingsPage.jsx';
import LoginPage from './pages/LoginPage.jsx';

export default function App() {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/*" element={
                <ProtectedRoute>
                    <Layout>
                        <Routes>
                            <Route path="/canvas" element={<CanvasPage />} />
                            <Route path="/workspace/:username/:slug" element={<WorkspacePage />} />
                            <Route path="/browse" element={<BrowsePage />} />
                            <Route path="/settings" element={<SettingsPage />} />
                            <Route path="*" element={<Navigate to="/canvas" replace />} />
                        </Routes>
                    </Layout>
                </ProtectedRoute>
            } />
        </Routes>
    );
}
```

- [ ] **Step 6: Update Layout.jsx with logout**

Add useAuth import and logout button to the nav:

```jsx
import useAuth from '../hooks/useAuth.js';

export default function Layout({ children }) {
    const { user, logout } = useAuth();

    return (
        <div className="h-screen w-screen flex flex-col bg-gray-900 text-gray-100">
            <nav className="h-12 flex items-center justify-between px-4 bg-gray-800 border-b border-gray-700 shrink-0 z-50">
                <a href="/app/canvas" className="text-lg font-bold text-indigo-400">URGE</a>
                <div className="flex items-center gap-4 text-sm">
                    <a href="/app/canvas" className="text-gray-300 hover:text-white">Canvas</a>
                    <a href="/browse" className="text-gray-400 hover:text-white">Browse</a>
                    <a href="/settings" className="text-gray-400 hover:text-white">Settings</a>
                    {user && (
                        <>
                            <span className="text-gray-500">|</span>
                            <span className="text-gray-400">{user.name}</span>
                            <button onClick={logout} className="text-gray-500 hover:text-red-400">Logout</button>
                        </>
                    )}
                </div>
            </nav>
            <main className="flex-1 overflow-hidden">{children}</main>
        </div>
    );
}
```

- [ ] **Step 7: Build and verify**

```bash
npm run build
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: LoginPage, useAuth hook, ProtectedRoute, Layout logout"
```

---

## Task 3: Verification + Push

- [ ] **Step 1: Run full PHP test suite**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: 351+ tests pass.

- [ ] **Step 2: Manual E2E test**

1. Open `/app/canvas` → should redirect to `/app/login` (not the Blade login)
2. Login with existing credentials → redirects to canvas
3. Canvas loads with nodes
4. Nav bar shows username + Logout button
5. Click Logout → redirects to login page
6. Classic Blade UI at `/browse` still works

- [ ] **Step 3: Commit and push**

```bash
git push
```

---

## Verification Summary

| Feature | How to verify |
|---------|--------------|
| Sanctum installed | `php artisan route:list --path=sanctum` shows CSRF cookie route |
| Login endpoint | `POST /api/v1/auth/login` returns user data |
| Logout endpoint | `POST /api/v1/auth/logout` invalidates session |
| User endpoint | `GET /api/v1/auth/user` returns current user |
| LoginPage | `/app/login` shows styled form |
| ProtectedRoute | `/app/canvas` redirects to login if not authenticated |
| Auth state | Nav bar shows user name + logout button |
| Graph API | Still works via session cookies (Sanctum) |
| Bearer token API | Still works for external consumers |
| PHP tests | 351+ pass |
