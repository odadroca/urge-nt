# React Settings Page Migration

## Context

The Settings pages are the last major Livewire-only UI in URGE. All other pages (Browse, Canvas, Workspace) are in React. Migrating Settings to React at `/app/settings` completes Prompt 9 of the migration plan and enables Prompt 10 (Livewire removal).

## Decision

Port all 6 Settings tabs from Livewire to React with full feature parity. Create 15 new API endpoints to support the React components. The Livewire Settings at `/settings` remains functional as fallback until Prompt 10.

## Architecture

### Route

`/app/settings` — React SPA route, protected. Sidebar "Settings" link changes from `/settings` (full page load to Livewire) to `/settings` (React Router link).

### Tab Structure

Role-based visibility:

| Tab | Admin | Editor | Viewer |
|-----|-------|--------|--------|
| API Keys | Full CRUD | - | - |
| LLM Providers | Full CRUD + test | Read-only list | Read-only list |
| Categories | Full CRUD | Full CRUD | Read-only |
| Pipelines | Full CRUD + channels | Full CRUD + channels | - |
| Evaluation | Full config | Full config | - |
| Users | Full CRUD | - | - |

### API Endpoints (15 new)

All within `dual.auth` middleware group in `routes/api.php`.

**API Keys (4 new):**
- `GET /v1/api-keys` — list user's keys (with prompts_count, last_used_at)
- `POST /v1/api-keys` — create key (name, prompt_ids[]). Returns generated key ONCE.
- `PATCH /v1/api-keys/{id}` — toggle is_active
- `DELETE /v1/api-keys/{id}` — delete key

**LLM Providers (4 new, extend existing controller):**
- `GET /v1/providers` — already exists (active only). Extend: admin sees all, non-admin sees active only.
- `POST /v1/providers` — create provider (admin-only)
- `PATCH /v1/providers/{id}` — update provider (admin-only, api_key optional)
- `DELETE /v1/providers/{id}` — delete provider (admin-only)
- `POST /v1/providers/{id}/test` — test connection (admin-only)

**Categories (2 new, extend existing controller):**
- `GET /v1/categories` — already exists
- `POST /v1/categories` — already exists
- `PATCH /v1/categories/{id}` — update name/color (editor+)
- `DELETE /v1/categories/{id}` — delete, unlink prompts (editor+)

**Evaluation Settings (2 new):**
- `GET /v1/evaluation-settings` — read current settings
- `PATCH /v1/evaluation-settings` — save settings (enabled, auto_evaluate, provider_id, dimensions[])

**Users (3 new):**
- `GET /v1/users` — list all users (admin-only)
- `POST /v1/users` — create user (admin-only)
- `PATCH /v1/users/{id}` — update role (admin-only, no self-change)
- `DELETE /v1/users/{id}` — delete user (admin-only, no self-delete)

### React Components

```
resources/js/spa/
├── pages/
│   └── SettingsPage.jsx              — tab orchestrator, role-based visibility
├── components/settings/
│   ├── ApiKeysTab.jsx                — key list, create form, copy-to-clipboard
│   ├── LlmProvidersTab.jsx           — provider CRUD, driver-conditional fields, test connection
│   ├── CategoriesTab.jsx             — category CRUD, 18-color palette picker
│   ├── PipelinesTab.jsx              — pipeline accordion, nested channel editing
│   ├── EvaluationTab.jsx             — toggle switches, dimension table with weights
│   └── UserManagementTab.jsx         — user CRUD, role dropdown, self-protection
└── api/
    ├── apiKeys.js                    — CRUD wrappers
    ├── providers.js                  — extend with CRUD + test (already has listProviders)
    ├── evaluationSettings.js         — get/save settings
    └── users.js                      — CRUD wrappers
```

### UI Interactions

**API Keys:**
- Create form: name input + optional prompt multi-select
- Generated key shown in success banner with copy button (shown once, never retrievable)
- Key list: name, preview (`urge_abc...`), last used, active toggle, delete

**LLM Providers:**
- Create/edit form: name, driver dropdown, api_key (hidden for Ollama), model, endpoint (shown for OpenAI/Ollama only)
- Driver selection changes visible fields dynamically
- Test connection button with loading spinner, shows success/error result
- Active toggle per provider

**Categories:**
- 18-color circle palette (gray through rose, matching current Tailwind colors)
- Create/edit: name + color selection
- Show prompt count per category
- Delete unlinks prompts (sets category_id to null)

**Pipelines:**
- Expandable accordion (click to expand/collapse)
- Active/inactive toggle with green/gray dot
- Expanded view shows channel list
- Inline channel add/edit form: role_label, provider select, system_prompt textarea, trigger (parallel/synthesis)
- Channel delete with confirmation

**Evaluation:**
- Enable/disable toggle
- Auto-evaluate toggle
- Provider dropdown (from active providers)
- Dimension table: enabled checkbox, name, description, weight (0-5 number input)
- Add custom dimension form (name + description)
- Remove custom dimension (built-in dimensions are read-only)

**Users:**
- Create form: name, email, password, role select
- User list with role dropdown (disabled for self)
- "You" badge on current user row
- Delete with confirmation (disabled for self)

## Files to Create

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/ApiKeyController.php` | API key CRUD |
| `app/Http/Controllers/Api/EvaluationSettingsController.php` | Evaluation settings get/save |
| `app/Http/Controllers/Api/UserController.php` | User CRUD (admin) |
| `resources/js/spa/pages/SettingsPage.jsx` | Rewrite — tabbed settings |
| `resources/js/spa/components/settings/ApiKeysTab.jsx` | API key management |
| `resources/js/spa/components/settings/LlmProvidersTab.jsx` | Provider CRUD + test |
| `resources/js/spa/components/settings/CategoriesTab.jsx` | Category CRUD + color picker |
| `resources/js/spa/components/settings/PipelinesTab.jsx` | Pipeline + channel management |
| `resources/js/spa/components/settings/EvaluationTab.jsx` | Evaluation config |
| `resources/js/spa/components/settings/UserManagementTab.jsx` | User management |
| `resources/js/spa/api/apiKeys.js` | API key wrappers |
| `resources/js/spa/api/evaluationSettings.js` | Evaluation settings wrappers |
| `resources/js/spa/api/users.js` | User CRUD wrappers |

## Files to Modify

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/LlmProviderController.php` | Add store, update, destroy, test methods |
| `app/Http/Controllers/Api/CategoryController.php` | Add update, destroy methods |
| `resources/js/spa/api/providers.js` | Add CRUD + test wrappers |
| `resources/js/spa/api/categories.js` | Add update, delete wrappers |
| `resources/js/spa/components/Sidebar.jsx` | Change Settings link from `<a href="/settings">` to `<Link to="/settings">` |
| `resources/js/spa/App.jsx` | Add `/settings` route |
| `routes/api.php` | Add all new routes |

## Verification

1. `php artisan test` — 376+ tests pass
2. `npm run build` — clean build
3. Navigate to `/app/settings` — tabs render correctly
4. API Keys: create, copy, toggle, delete
5. LLM Providers: create with each driver, test connection, edit, delete
6. Categories: create with color, edit, delete (prompts unlinked)
7. Pipelines: create, add channels, edit channels, delete
8. Evaluation: toggle settings, add/remove dimensions, save
9. Users: create, change role, delete (self-protection works)
10. Role visibility: editor sees 4 tabs, viewer sees 2 read-only tabs
