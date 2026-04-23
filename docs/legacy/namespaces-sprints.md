# URGE v2 — User Namespaces & Teams: Sprint Plan

> Phase 7 implementation plan.
> Each sprint is self-contained with a ready-to-use prompt for Claude Code.
> Status: [ ] = todo, [~] = in progress, [x] = done

---

## Sprint Order & Dependencies

```
Sprint N1 → N2 → N3 → N4 → N5 → N6 → N7 → N8
```

- **N1** (Data model) must come first — all other sprints depend on it
- **N2** (Query scoping) depends on N1
- **N3** (Browse UI) depends on N2
- **N4** (Workspace) depends on N2
- **N5** (Teams UI) depends on N1
- **N3-N5** can run in parallel after N2 (N5 only needs N1)
- **N6** (API) depends on N2
- **N7** (MCP) depends on N2
- **N6-N7** can run in parallel with N3-N5
- **N8** (Migration + polish) goes last

```
N1 (data model)
├── N2 (query scoping)
│   ├── N3 (browse UI)      ─┐
│   ├── N4 (workspace)       ├── can run in parallel
│   ├── N6 (REST API)        │
│   └── N7 (MCP)            ─┘
├── N5 (teams UI)            ── can run after N1
└── N8 (migration + polish)  ── last
```

---

## Sprint N1 — Data Model & Migrations

**Status:** [x]

**Goal:** Create the database tables and model relationships for teams, team membership, and prompt-team sharing. Add visibility column to prompts. Change slug uniqueness to per-user.

**Files to create:**
- `database/migrations/YYYY_MM_DD_create_teams_table.php`
- `database/migrations/YYYY_MM_DD_create_team_user_table.php`
- `database/migrations/YYYY_MM_DD_create_prompt_team_table.php`
- `database/migrations/YYYY_MM_DD_add_visibility_to_prompts.php`
- `database/migrations/YYYY_MM_DD_change_prompt_slug_uniqueness.php`
- `app/Models/Team.php`

**Files to modify:**
- `app/Models/Prompt.php` — add `visibility`, team relationships, `scopeVisibleTo()`
- `app/Models/User.php` — add team relationships

**Prompt:**
```
You are continuing development of URGE v2, a Laravel 12 / Livewire 3 prompt management system. Read CLAUDE.md for full context, then read docs/superpowers/specs/2026-03-25-user-namespaces-design.md for the design spec.

Implement Sprint N1: Data Model & Migrations.

### Migrations

Create these migrations in order:

1. **create_teams_table**: id, name (string), slug (string unique), created_by (FK users), timestamps.

2. **create_team_user_table**: id, team_id (FK teams, cascade delete), user_id (FK users, cascade delete), role (string, default 'member'), timestamps. Unique on (team_id, user_id).

3. **create_prompt_team_table**: id, prompt_id (FK prompts, cascade delete), team_id (FK teams, cascade delete), timestamps. Unique on (prompt_id, team_id).

4. **add_visibility_to_prompts**: Add `visibility` string column (default 'private') after `tags`. For ALL existing rows, set visibility to 'shared' (preserves current behavior where all prompts are visible to everyone).

5. **change_prompt_slug_uniqueness**: Drop the existing unique index on `prompts.slug`. Add a composite unique index on `(created_by, slug)`.

### Team Model

Create `app/Models/Team.php`:
- Fillable: name, slug, created_by
- Auto-generate slug from name (same pattern as Prompt/Category — use Str::slug with collision counter)
- Relationships:
  - `creator()`: belongsTo(User, 'created_by')
  - `members()`: belongsToMany(User, 'team_user')->withPivot('role')->withTimestamps()
  - `owners()`: members filtered to role='owner'
  - `prompts()`: belongsToMany(Prompt, 'prompt_team')->withTimestamps()

### User Model Updates

Add to `app/Models/User.php`:
- `teams()`: belongsToMany(Team, 'team_user')->withPivot('role')->withTimestamps()
- `ownedTeams()`: teams filtered to role='owner'

### Prompt Model Updates

Add to `app/Models/Prompt.php`:
- `visibility` to $fillable
- `teams()`: belongsToMany(Team, 'prompt_team')->withTimestamps()
- `isPrivate()`: returns $this->visibility === 'private'
- `isShared()`: returns $this->visibility === 'shared'
- `scopeVisibleTo($query, User $user)`:
  ```php
  $query->where(function ($q) use ($user) {
      $q->where('created_by', $user->id)
        ->orWhereHas('teams', function ($tq) use ($user) {
            $tq->whereHas('members', fn ($mq) => $mq->where('users.id', $user->id));
        });
  });
  ```

### Tests

Create `tests/Feature/NamespaceDataModelTest.php`:
- test_team_creation_with_auto_slug
- test_team_member_relationships
- test_prompt_visibility_scope_owner_sees_own
- test_prompt_visibility_scope_team_member_sees_shared
- test_prompt_visibility_scope_non_member_cannot_see
- test_prompt_slug_unique_per_user (two users can have same slug)
- test_existing_prompts_migrated_as_shared

Run `php artisan test` — all tests must pass (existing 190+ plus new ones).

After implementing, run tests, commit with a descriptive message.
```

---

## Sprint N2 — Query Scoping & Authorization

**Status:** [x]

**Goal:** Apply the `visibleTo` scope across all existing query paths (Browse, Workspace, internal API). Add authorization checks for ownership actions.

**Files to modify:**
- `app/Livewire/Browse.php` — apply `visibleTo` scope to all prompt queries
- `app/Livewire/Workspace/WorkspacePage.php` — verify access on mount
- `app/Livewire/Workspace/PromptMetadata.php` — restrict delete/rename to owner
- `app/Livewire/Workspace/Editor.php` — restrict based on visibility
- `app/Http/Controllers/InternalApiController.php` — scope fragment/variable queries
- `routes/web.php` — update workspace route for namespaced URLs

**Prompt:**
```
You are continuing development of URGE v2. Read CLAUDE.md for context, then read docs/superpowers/specs/2026-03-25-user-namespaces-design.md and docs/namespaces-sprints.md.

Sprint N1 (data model) is complete. Implement Sprint N2: Query Scoping & Authorization.

### Route Changes

In `routes/web.php`:
1. Change workspace route from `/prompts/{prompt:slug}` to `/prompts/{user}/{prompt}` where `{user}` is the username (slugified) and `{prompt}` is the prompt slug.
2. Add a legacy redirect: `GET /prompts/{slug}` → look up prompt by slug, redirect to `/prompts/{owner-name}/{slug}`. If ambiguous (multiple users), prefer current user's prompt, then fall back to oldest.

### WorkspacePage Changes

Update `app/Livewire/Workspace/WorkspacePage.php`:
- Mount receives both username and slug parameters
- Resolve the prompt: find user by name, then find prompt by (created_by, slug)
- Verify current user can see this prompt using `visibleTo` scope
- If not found or not visible → abort(404)
- Pass an `$isOwner` flag to child components

### Browse Changes

Update `app/Livewire/Browse.php`:
- Apply `Prompt::visibleTo(auth()->user())` to the main query
- Add a `$browseScope` property: 'mine', 'team:{slug}', or 'all'
- Default to 'mine' (show only user's own prompts)
- When scope is 'team:{slug}', filter to prompts shared with that team
- When scope is 'all', show all visible prompts (own + shared)
- Pass user's teams to the view for sidebar rendering
- Update the `createPrompt()` method — new prompts are private by default

### PromptMetadata Changes

Update `app/Livewire/Workspace/PromptMetadata.php`:
- Accept `$isOwner` parameter
- Only show delete button if `$isOwner` or admin
- Only allow rename if `$isOwner` or admin
- Show "Shared by {owner}" indicator if not owner

### Internal API Changes

Update `app/Http/Controllers/InternalApiController.php`:
- `variables()`: scope to visible prompts only
- `fragments()`: scope to visible prompts only

### Link Updates

Search all blade views for `route('workspace', $prompt)` and update to include the owner username:
`route('workspace', [$prompt->creator->name, $prompt->slug])` (or similar)

### Tests

Create `tests/Feature/NamespaceScopingTest.php`:
- test_browse_shows_only_own_prompts_by_default
- test_browse_shows_team_prompts_when_scoped
- test_workspace_accessible_by_owner
- test_workspace_accessible_by_team_member
- test_workspace_404_for_non_member
- test_legacy_slug_redirects_to_namespaced_url
- test_only_owner_can_delete_prompt
- test_only_owner_can_rename_prompt
- test_admin_can_access_any_prompt

Run `php artisan test` — all tests must pass.

After implementing, run tests, commit with a descriptive message.
```

---

## Sprint N3 — Browse UI with Sidebar

**Status:** [x]

**Goal:** Add the namespace sidebar to the Browse page showing "My Prompts" and team sections. Add owner badges to shared prompt cards.

**Files to modify:**
- `resources/views/livewire/browse.blade.php` — add sidebar layout, scope switching, owner badges
- `app/Livewire/Browse.php` — (already scoped in N2) pass teams and counts to view

**Prompt:**
```
You are continuing development of URGE v2. Read CLAUDE.md for context, then read docs/superpowers/specs/2026-03-25-user-namespaces-design.md and docs/namespaces-sprints.md.

Sprints N1-N2 are complete. Implement Sprint N3: Browse UI with Sidebar.

### Sidebar Layout

Restructure `resources/views/livewire/browse.blade.php`:

1. Add a left sidebar (w-56 on desktop, collapsible drawer on mobile):
   - "My Prompts" link with count badge — sets `$browseScope = 'mine'`
   - Divider
   - Each team the user belongs to, with prompt count badge — sets `$browseScope = 'team:{slug}'`
   - "All Prompts" link — sets `$browseScope = 'all'`
   - Active section highlighted with indigo background (same style as nav)

2. Main content area (flex-1) keeps the existing grid layout with:
   - Filter pills (type, category, tag, search) — work within the selected scope
   - Prompt cards grid
   - Pagination

3. Default selection: "My Prompts"

### Owner Badges on Prompt Cards

When browsing team or "all" views, show a subtle owner indicator on cards:
- Small avatar circle with initials (first letter of user name) + "by {name}" text
- Don't show on cards in "My Prompts" view (redundant)
- Position: bottom-left of card, muted text

### Mobile Behavior

On mobile (sm: breakpoint):
- Sidebar becomes a horizontal scroll strip at the top (below the quick-start header)
- Pills/chips style: "My Prompts" | "Family" | "Work" | "All"
- Tapping switches the scope

### Browse.php Updates

In `app/Livewire/Browse.php`:
- Add `switchScope(string $scope)` Livewire action
- Compute team prompt counts for sidebar badges
- Pass `$userTeams` and `$scopeCounts` to view

Keep the existing quick-start header, onboarding section, and filter pills intact. The sidebar is an addition, not a replacement.

After implementing, run `php artisan test`, commit with a descriptive message.
```

---

## Sprint N4 — Workspace Namespace Support

**Status:** [x]

**Goal:** Update the workspace to work with namespaced URLs. Add sharing controls for prompt owners. Show ownership indicators for non-owners.

**Files to modify:**
- `resources/views/livewire/workspace/prompt-metadata.blade.php` — sharing UI, owner indicator
- `app/Livewire/Workspace/PromptMetadata.php` — share/unshare methods
- `resources/views/livewire/workspace/editor.blade.php` — hide owner-only actions for non-owners
- `app/Livewire/Workspace/WorkspacePage.php` — (already updated in N2) pass isOwner
- `resources/views/layouts/app.blade.php` — update "Continue" link for namespaced URLs

**Prompt:**
```
You are continuing development of URGE v2. Read CLAUDE.md for context, then read docs/superpowers/specs/2026-03-25-user-namespaces-design.md and docs/namespaces-sprints.md.

Sprints N1-N2 are complete. Implement Sprint N4: Workspace Namespace Support.

### Sharing UI in PromptMetadata

Add a "Sharing" section to the metadata panel (only visible to prompt owner):

1. Below the existing tags section, add a "Sharing" header
2. List the owner's teams with toggle switches (Alpine)
3. Toggling a team on calls `shareWithTeam($teamId)` on PromptMetadata
4. Toggling off calls `unshareFromTeam($teamId)`
5. When shared with ≥1 team, automatically set `visibility = 'shared'`
6. When unshared from all teams, revert to `visibility = 'private'`
7. Show a lock icon + "Private" or a people icon + "Shared with N teams" status

### PromptMetadata.php Updates

Add to `app/Livewire/Workspace/PromptMetadata.php`:
- `$isOwner` property (passed from WorkspacePage)
- `shareWithTeam(int $teamId)`: verify ownership, attach team, set visibility='shared'
- `unshareFromTeam(int $teamId)`: verify ownership, detach team, revert visibility if no teams left
- `$sharedTeams`: computed from prompt's teams relationship
- `$availableTeams`: current user's teams (for the toggle list)

### Owner Indicator

When `$isOwner` is false, show a subtle banner or badge in the metadata panel:
- "Shared by {owner name}" with a small avatar/initials
- Hide delete button and rename capability
- Keep all editing actions (save version, etc.) available

### Editor Updates

In `resources/views/livewire/workspace/editor.blade.php`:
- Wrap the delete button in `@if($isOwner || auth()->user()->isAdmin())`

### Navigation Link Updates

In `resources/views/layouts/app.blade.php`, update the "Continue: {name}" link to use namespaced URL:
- `route('workspace', [$lastPrompt->creator->name, $lastPrompt->slug])` instead of `route('workspace', $lastPrompt)`

### All Workspace Links

Search all blade files for `route('workspace', ...)` and update to pass `[$prompt->creator->name, $prompt->slug]` or equivalent. This includes:
- Browse prompt cards
- Version sidebar links
- Any redirect after prompt creation

After implementing, run `php artisan test`, commit with a descriptive message.
```

---

## Sprint N5 — Teams Management UI

**Status:** [x]

**Goal:** Create the teams management page where users can create, manage, and join teams.

**Files to create:**
- `app/Livewire/Teams.php`
- `resources/views/livewire/teams.blade.php`
- `app/Livewire/TeamDetail.php`
- `resources/views/livewire/team-detail.blade.php`

**Files to modify:**
- `routes/web.php` — add team routes
- `resources/views/layouts/app.blade.php` — add Teams nav link

**Prompt:**
```
You are continuing development of URGE v2. Read CLAUDE.md for context, then read docs/superpowers/specs/2026-03-25-user-namespaces-design.md and docs/namespaces-sprints.md.

Sprint N1 (data model) is complete. Implement Sprint N5: Teams Management UI.

### Routes

Add to `routes/web.php` inside the auth middleware group:
- `GET /teams` → Teams::class, name 'teams'
- `GET /teams/{team:slug}` → TeamDetail::class, name 'team.detail'

### Navigation

In `resources/views/layouts/app.blade.php`, add a "Teams" nav link between Browse and Settings (both desktop and mobile nav).

### Teams List Page

Create `app/Livewire/Teams.php`:
- Lists teams the user belongs to (both owned and member)
- Create team form: name input + submit
- `createTeam(string $name)`: creates team, adds current user as 'owner' in pivot
- Each team card shows: name, member count, prompt count, user's role (owner/member)
- Click → navigate to team detail

Create `resources/views/livewire/teams.blade.php`:
- Clean card-based layout matching URGE's design system
- Create form at the top (similar to Browse's quick-start header)
- Team cards in a grid
- Empty state when user has no teams

### Team Detail Page

Create `app/Livewire/TeamDetail.php`:
- Mount receives Team via route model binding
- Verify current user is a member (or admin) — abort(404) otherwise
- Properties: team, members list, shared prompts list
- Methods (owner/admin only):
  - `inviteMember(string $emailOrName)`: find user, add as 'member', toast notification
  - `removeMember(int $userId)`: remove from pivot, toast notification
  - `leaveTeam()`: remove self (not allowed for sole owner), redirect to /teams
- `$isTeamOwner` computed: current user has role='owner' for this team

Create `resources/views/livewire/team-detail.blade.php`:
- Team name as header
- Members section: table/list with name, email, role, remove button (owner only)
- Invite form (owner only): text input for email/name + invite button
- Shared prompts section: list of prompts shared with this team, with links to workspace
- "Leave Team" button (for non-owners)
- "Delete Team" button (for owner only, with confirmation)

### Tests

Create `tests/Feature/TeamsTest.php`:
- test_user_can_create_team
- test_team_creator_is_owner
- test_owner_can_invite_member
- test_owner_can_remove_member
- test_member_can_leave_team
- test_non_member_cannot_see_team
- test_owner_can_delete_team
- test_admin_can_manage_any_team

Run `php artisan test` — all tests must pass.

After implementing, run tests, commit with a descriptive message.
```

---

## Sprint N6 — REST API Namespace Support

**Status:** [x]

**Goal:** Update all REST API endpoints to use namespaced URLs and apply visibility scoping. Add team management API endpoints.

**Files to modify:**
- `routes/api.php` — update prompt routes to `{username}/{slug}`, add team routes
- `app/Http/Controllers/Api/PromptController.php` — namespace-aware resolution, visibility scoping
- `app/Http/Controllers/Api/VersionController.php` — namespace-aware resolution
- `app/Http/Controllers/Api/RenderController.php` — namespace-aware resolution
- `app/Http/Controllers/Api/ResultController.php` — namespace-aware resolution
- `public/openapi.json` — update all paths, add team schemas/endpoints

**Files to create:**
- `app/Http/Controllers/Api/TeamController.php`
- `app/Http/Controllers/Api/ShareController.php` (API version)

**Prompt:**
```
You are continuing development of URGE v2. Read CLAUDE.md for context, then read docs/superpowers/specs/2026-03-25-user-namespaces-design.md and docs/namespaces-sprints.md.

Sprint N2 (query scoping) is complete. Implement Sprint N6: REST API Namespace Support.

### Route Changes

In `routes/api.php`, update all prompt routes from `{prompt:slug}` to `{username}/{promptSlug}`:

```php
// Namespaced prompt routes
Route::get('prompts', [PromptController::class, 'index']);
Route::post('prompts', [PromptController::class, 'store']);
Route::get('prompts/{username}/{promptSlug}', [PromptController::class, 'show']);
Route::patch('prompts/{username}/{promptSlug}', [PromptController::class, 'update']);
Route::delete('prompts/{username}/{promptSlug}', [PromptController::class, 'destroy']);
Route::get('prompts/{username}/{promptSlug}/versions', [VersionController::class, 'index']);
Route::post('prompts/{username}/{promptSlug}/versions', [VersionController::class, 'store']);
Route::get('prompts/{username}/{promptSlug}/versions/{version}', [VersionController::class, 'show']);
Route::post('prompts/{username}/{promptSlug}/render', [RenderController::class, 'render']);
Route::get('prompts/{username}/{promptSlug}/results', [ResultController::class, 'index']);
Route::post('prompts/{username}/{promptSlug}/results', [ResultController::class, 'store']);

// Legacy redirect
Route::get('prompts/{slug}', [PromptController::class, 'legacyRedirect'])->where('slug', '[^/]+');

// Team routes
Route::apiResource('teams', TeamController::class)->except('update');
Route::patch('teams/{team:slug}', [TeamController::class, 'update']);
Route::post('teams/{team:slug}/members', [TeamController::class, 'addMember']);
Route::delete('teams/{team:slug}/members/{user}', [TeamController::class, 'removeMember']);

// Share routes
Route::post('prompts/{username}/{promptSlug}/share', [ShareController::class, 'share']);
Route::delete('prompts/{username}/{promptSlug}/share/{team}', [ShareController::class, 'unshare']);
```

### Prompt Resolution Helper

Create a shared method (trait or base controller) to resolve `{username}/{promptSlug}`:
1. Find user by name (slugified match)
2. Find prompt by (created_by, slug)
3. Apply `visibleTo` check for the API key's user
4. Return prompt or abort(404)

### PromptController Changes

- `index()`: apply `Prompt::visibleTo($request->user())` scope. Add optional `scope` filter: 'mine', 'shared', 'all' (default 'all').
- `show()`: use namespace resolution
- `store()`: new prompts default to visibility='private', created_by = API user
- `update()`: verify ownership (owner or admin only)
- `destroy()`: new method, verify ownership (owner or admin only)
- `legacyRedirect()`: look up by slug, return 301 redirect to namespaced URL

### TeamController

Create `app/Http/Controllers/Api/TeamController.php`:
- `index()`: list teams the API user belongs to
- `store()`: create team, add user as owner
- `show()`: team details + members + prompt count
- `update()`: rename team (owner/admin only)
- `destroy()`: delete team (owner/admin only)
- `addMember()`: invite by user ID or email (owner/admin only)
- `removeMember()`: remove member (owner/admin only)

### ShareController (API)

Create `app/Http/Controllers/Api/ShareController.php`:
- `share()`: share prompt with team (owner only). Accepts `team_id` or `team_slug`.
- `unshare()`: unshare from team (owner only)
- Both auto-update visibility field

### OpenAPI Spec

Update `public/openapi.json`:
- Bump version to 3.0.0
- Update ALL prompt paths to include `{username}` parameter
- Add `username` path parameter definition
- Add Team schema: id, name, slug, created_by, member_count
- Add TeamMember schema: id, name, email, role
- Add all team endpoints
- Add share/unshare endpoints
- Update Prompt schema: add visibility (enum: private, shared), owner (object: id, name)
- Document legacy redirect behavior

### Tests

Create `tests/Feature/ApiNamespaceTest.php`:
- test_api_list_prompts_respects_visibility
- test_api_get_prompt_with_namespace
- test_api_create_prompt_defaults_to_private
- test_api_update_prompt_owner_only
- test_api_delete_prompt_owner_only
- test_api_legacy_slug_redirect
- test_api_team_crud
- test_api_share_unshare_prompt

Run `php artisan test` — all tests must pass.

After implementing, run tests, commit with a descriptive message.
```

---

## Sprint N7 — MCP Namespace Support

**Status:** [x]

**Goal:** Update all MCP tools and resources to be namespace-aware. Add new team and sharing tools.

**Files to modify:**
- `app/Services/McpToolHandler.php` — add namespace resolution, visibility scoping, new tools
- `app/Http/Controllers/McpController.php` — if any routing changes needed
- `app/Console/Commands/McpServerCommand.php` — if any changes needed

**Prompt:**
```
You are continuing development of URGE v2. Read CLAUDE.md for context, then read docs/superpowers/specs/2026-03-25-user-namespaces-design.md and docs/namespaces-sprints.md.

Sprint N2 (query scoping) is complete. Implement Sprint N7: MCP Namespace Support.

### McpToolHandler Changes

Update `app/Services/McpToolHandler.php`:

**Existing tools — add `owner` parameter:**

1. `get_prompt(slug, owner?, version?, variables?)`:
   - If `owner` provided: resolve as `{owner}/{slug}`
   - If omitted: search current user's prompts first, then all visible
   - Apply `visibleTo` scope

2. `list_prompts(type?, category?, tag?, search?, scope?)`:
   - Add `scope` parameter: 'mine' (default), 'shared', 'team:{slug}', 'all'
   - Apply `visibleTo` scope
   - Return `owner` field in each result

3. `render_prompt(slug, owner?, version?, variables{})`:
   - Namespace-aware resolution (same as get_prompt)
   - Apply `visibleTo` scope

4. `save_version(slug, owner?, content, commit_message?)`:
   - Namespace-aware resolution
   - Verify visibility (can see = can edit)

5. `store_result(slug, owner?, version, response_text, provider?, model?)`:
   - Namespace-aware resolution
   - Verify visibility

6. `get_results(slug, owner?, version?, starred?, limit?)`:
   - Namespace-aware resolution
   - Verify visibility

**New tools:**

7. `delete_prompt(slug, owner?)`:
   - Resolve prompt
   - Verify ownership (owner or admin only)
   - Soft delete

8. `share_prompt(slug, team_slug)`:
   - Verify ownership
   - Attach team, update visibility

9. `list_teams()`:
   - Return teams the current user belongs to
   - Include member count and prompt count per team

**Resource updates:**

- `urge://prompts` → list visible prompts (apply scope), include owner in each
- `urge://prompts/{owner}/{slug}` → namespace-aware
- `urge://prompts/{owner}/{slug}/v/{n}` → namespace-aware
- `urge://teams` → new resource, list user's teams
- Keep old `urge://prompts/{slug}` working as fallback (resolve to owner)

**Tool & resource definitions:**

Update `getToolDefinitions()`:
- Add `owner` parameter (string, optional) to existing tools
- Add `scope` parameter to list_prompts
- Add delete_prompt, share_prompt, list_teams tool definitions

Update `getResourceDefinitions()`:
- Update URI templates for namespaced format
- Add teams resource

### Tests

Update `tests/Feature/McpToolHandlerTest.php`:
- test_get_prompt_with_namespace
- test_list_prompts_respects_visibility
- test_list_prompts_scope_filter
- test_save_version_on_shared_prompt
- test_delete_prompt_owner_only
- test_share_prompt
- test_list_teams

Update `tests/Feature/McpControllerTest.php`:
- test_mcp_tool_definitions_include_new_tools
- test_mcp_resource_definitions_include_teams

Run `php artisan test` — all tests must pass.

After implementing, run tests, commit with a descriptive message.
```

---

## Sprint N8 — Migration, Documentation & Polish

**Status:** [x]

**Goal:** Run the actual data migration on existing data, update all documentation, fix edge cases, and final testing.

**Files to modify:**
- `README.md` — update features, API examples, test count
- `CLAUDE.md` — update architecture, data model, routes, components
- `CONTINUE.md` — add Phase 7 continuation prompt
- `documentation/architecture.md` — update data model, integration surfaces, component tree
- `documentation/claude-skill.md` — update API examples with namespaced URLs
- `documentation/install.md` — update first-run walkthrough
- `docs/ux-roadmap.md` — add namespace sprints reference
- `public/openapi.json` — (should be done in N6, verify completeness)

**Prompt:**
```
You are continuing development of URGE v2. Read CLAUDE.md for context, then read docs/superpowers/specs/2026-03-25-user-namespaces-design.md and docs/namespaces-sprints.md.

Sprints N1-N7 are complete. Implement Sprint N8: Migration, Documentation & Polish.

### Data Migration Verification

Run `php artisan migrate` and verify:
1. All existing prompts have `visibility = 'shared'`
2. Slug uniqueness is now per-user (composite index)
3. Teams tables exist and are empty
4. All existing functionality still works

### Documentation Updates

Update ALL documentation to reflect the namespace system:

**README.md:**
- Add "User namespaces & teams" to features list
- Update API table with namespaced URLs (e.g., `/prompts/{username}/{slug}`)
- Update MCP tools list (add delete_prompt, share_prompt, list_teams)
- Update MCP resources list
- Update test count

**CLAUDE.md:**
- Update Core Models section: add Team model, update Prompt model (visibility field)
- Update Data Flow: add namespace/visibility info
- Update Routes section: namespaced workspace route, team routes
- Update API Endpoints: all prompt routes now include {username}
- Update MCP Tools: add new tools, add owner parameter
- Update MCP Resources: namespaced URIs
- Update Livewire Components tree: add Teams.php, TeamDetail.php
- Update Auth & Roles section: document team permissions
- Update Phase Roadmap: add Phase 7
- Update test count

**CONTINUE.md:**
- Add Phase 7 continuation prompt section (for namespaces & teams)

**documentation/architecture.md:**
- Update Entity Relationships diagram (add Team)
- Update Tables section (add teams, team_user, prompt_team; update prompts)
- Update Design Decisions (add namespace rationale)
- Update REST API endpoints (namespaced)
- Update MCP tools and resources
- Update Component Architecture (add Teams components)
- Update Phase Roadmap (add Phase 7)

**documentation/claude-skill.md:**
- Update all curl examples with namespaced URLs
- Add team management examples
- Update MCP tools list
- Add namespace explanation

**documentation/install.md:**
- Update first-run walkthrough to mention namespaces
- Note that first user's prompts are in their namespace

### Edge Case Fixes

Check and fix:
1. Prompt creation: ensure `created_by` is always set
2. Slug generation: ensure uniqueness check uses per-user scope
3. Template engine includes (`{{>slug}}`): should resolve across all visible prompts
4. Import/export: include owner in exported metadata
5. Collections: collection items reference prompts that may be in different namespaces — verify access

### Final Test Suite

Run `php artisan test` — ALL tests must pass. Update the test count in docs.

After implementing, run full test suite, commit with a descriptive message, push.
```

---

## Summary

| Sprint | Focus | Depends On | Effort |
|--------|-------|------------|--------|
| N1 | Data model & migrations | — | Medium |
| N2 | Query scoping & authorization | N1 | Medium |
| N3 | Browse UI with sidebar | N2 | Medium |
| N4 | Workspace namespace support | N2 | Medium |
| N5 | Teams management UI | N1 | Medium |
| N6 | REST API namespace support | N2 | Large |
| N7 | MCP namespace support | N2 | Medium |
| N8 | Migration, docs & polish | N1-N7 | Medium |

**Parallelization opportunities:**
- After N2: N3 + N4 + N6 + N7 can run in parallel
- After N1: N5 can start immediately
- N8 must be last
