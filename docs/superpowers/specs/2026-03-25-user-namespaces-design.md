# User Namespaces & Teams — Design Spec

> Date: 2026-03-25
> Status: Approved
> Phase: 7 (follows Phases 1-6 + UX sprints)

## Overview

Add user-specific namespaces and team-based sharing to URGE v2. Each user gets a private namespace for their prompts. Prompts are private by default and can be explicitly shared with teams. Teams are flat groups (no hierarchy) that any user can create.

## Goals

1. Users have private prompts that only they can see
2. Users can share prompts with teams they belong to
3. Team members can edit shared prompts (wiki-style, version-tracked)
4. Only prompt owners can delete, rename, or change sharing settings
5. Prompt slugs are scoped per user (user-prefixed URLs like GitHub repos)
6. All surfaces (Web UI, REST API, MCP, openapi.json) are namespace-aware

## Data Model

### New Tables

**`teams`**
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | |
| slug | string, unique | Auto-generated from name |
| created_by | FK → users | Team creator |
| timestamps | | |

**`team_user`** (pivot)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| team_id | FK → teams | |
| user_id | FK → users | |
| role | enum: owner, member | Creator = owner |
| timestamps | | |
| unique | (team_id, user_id) | |

**`prompt_team`** (pivot)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| prompt_id | FK → prompts | |
| team_id | FK → teams | |
| timestamps | | |
| unique | (prompt_id, team_id) | |

### Changes to Existing Tables

**`prompts`**
- Add `visibility` enum column: `private` (default), `shared`
- Change slug uniqueness: drop global unique index, add `UNIQUE(created_by, slug)`

### No Changes Required

- `prompt_versions` — continues tracking `created_by` per version
- `results` — continues tracking `created_by` per result
- `categories` — remain global (shared across all users)
- `api_keys` — existing pivot scoping continues to work
- `collections` — already user-scoped via `created_by`

## Visibility Rules

A user can see a prompt if **any** of these conditions is true:

1. **Owner**: `prompt.created_by == user.id` (always visible)
2. **Team member**: prompt is shared with a team the user belongs to

```
Prompt::scopeVisibleTo($query, User $user)
    → WHERE created_by = $user->id
      OR id IN (
          SELECT prompt_id FROM prompt_team
          WHERE team_id IN (
              SELECT team_id FROM team_user WHERE user_id = $user->id
          )
      )
```

This scope is applied **everywhere**: Browse, Workspace access, API, MCP.

Attempting to access a prompt you can't see returns **404** (not 403) to avoid leaking existence.

## Permission Model

### Prompt Actions

| Action | Owner | Team Member | Admin |
|--------|-------|-------------|-------|
| View/read | ✓ | ✓ (if shared) | ✓ (all) |
| Edit content / create version | ✓ | ✓ (if shared) | ✓ (all) |
| Delete prompt | ✓ | ✗ | ✓ (all) |
| Rename / change slug | ✓ | ✗ | ✓ (all) |
| Change sharing settings | ✓ | ✗ | ✓ (all) |

### Team Actions

| Action | Team Owner | Team Member | Admin |
|--------|------------|-------------|-------|
| Invite members | ✓ | ✗ | ✓ (all) |
| Remove members | ✓ | ✗ | ✓ (all) |
| Remove prompt from team | ✓ | ✗ | ✓ (all) |
| Delete team | ✓ | ✗ | ✓ (all) |
| Leave team | ✓ | ✓ | ✓ |
| View team prompts | ✓ | ✓ | ✓ |

### Default Behavior

- New prompts are **private** by default
- Team creators are automatically the team **owner**
- Any authenticated user can create teams

## URL Structure

### Web Routes

| Route | Purpose | Replaces |
|-------|---------|----------|
| `/prompts/{username}/{slug}` | View/edit prompt | `/prompts/{slug}` |
| `/teams` | List user's teams | New |
| `/teams/{team:slug}` | Team detail + members | New |
| `/prompts/{slug}` | Legacy redirect → `/prompts/{owner}/{slug}` | Backward compat |

### API Routes (prefix `/api/v1/`)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/prompts` | List visible prompts (respects `visibleTo` scope) |
| POST | `/prompts` | Create prompt (private by default) |
| GET | `/prompts/{username}/{slug}` | Get prompt with active version |
| PATCH | `/prompts/{username}/{slug}` | Update metadata (owner/admin only) |
| DELETE | `/prompts/{username}/{slug}` | Delete prompt (owner/admin only) |
| GET | `/prompts/{username}/{slug}/versions` | List versions |
| POST | `/prompts/{username}/{slug}/versions` | Create version (visible = can edit) |
| GET | `/prompts/{username}/{slug}/versions/{n}` | Get specific version |
| POST | `/prompts/{username}/{slug}/render` | Render with variables |
| GET | `/prompts/{username}/{slug}/results` | List results |
| POST | `/prompts/{username}/{slug}/results` | Store result |
| GET | `/prompts/{slug}` | Legacy redirect → `/{owner}/{slug}` |
| GET | `/teams` | List user's teams |
| POST | `/teams` | Create team |
| GET | `/teams/{slug}` | Get team details + members |
| PATCH | `/teams/{slug}` | Update team (owner/admin) |
| DELETE | `/teams/{slug}` | Delete team (owner/admin) |
| POST | `/teams/{slug}/members` | Invite member |
| DELETE | `/teams/{slug}/members/{user}` | Remove member |
| POST | `/prompts/{username}/{slug}/share` | Share with team(s) |
| DELETE | `/prompts/{username}/{slug}/share/{team}` | Unshare from team |

### MCP Resources

| URI | Purpose |
|-----|---------|
| `urge://prompts` | List visible prompts |
| `urge://prompts/{username}/{slug}` | Prompt with active version |
| `urge://prompts/{username}/{slug}/v/{n}` | Specific version |
| `urge://teams` | List user's teams |

### MCP Tools (updated)

All existing tools gain optional `owner` parameter for namespaced access:

| Tool | Change |
|------|--------|
| `get_prompt` | Add `owner` param (optional, defaults to current user) |
| `list_prompts` | Add `scope` filter: `mine`, `shared`, `team:{slug}`, `all` |
| `render_prompt` | Add `owner` param |
| `save_version` | Add `owner` param |
| `store_result` | Add `owner` param |
| `get_results` | Add `owner` param |
| `delete_prompt` | New tool (owner/admin only) |
| `share_prompt` | New tool (owner/admin only) |
| `list_teams` | New tool |

## Slug Uniqueness & Migration

### New Constraint

- Slugs are unique **per user**: `UNIQUE(created_by, slug)`
- Two users can both have `email-template`
- URL format: `/prompts/alexandre/email-template`

### Migration of Existing Data

1. All existing prompts retain their current slugs
2. Existing prompts are assigned to their creator (already tracked via `created_by`)
3. Drop global unique index on `slug`, add composite unique on `(created_by, slug)`
4. Add `visibility` column defaulting to `shared` for existing prompts (preserves current behavior where everyone sees everything)
5. New prompts default to `private`

### Legacy URL Redirect

`/prompts/{slug}` (old format) → look up prompt by slug, redirect to `/prompts/{owner-username}/{slug}`

If slug is ambiguous (multiple users with same slug after namespace migration), resolve to the current user's prompt first, then fall back to oldest.

## Browse UI Changes

### Sidebar Navigation

Replace the current flat browse with a sidebar + content layout:

```
┌─────────────────┬──────────────────────────────┐
│ My Prompts  [3] │                              │
│ ─────────────── │   Prompt cards grid          │
│ Team: Family [5]│   (filtered by sidebar       │
│ Team: Work  [12]│    selection)                │
│                 │                              │
│                 │   [type] [category] [tag]    │
│                 │   filter pills still work    │
└─────────────────┴──────────────────────────────┘
```

- Default view: "My Prompts" (user's own prompts)
- Team sections show prompt count badges
- Existing filter pills (type, category, tag, search) apply within the selected section
- "All visible" option to see everything at once

### Prompt Cards

- Add subtle owner badge (avatar/initials + name) on shared prompt cards
- Owner's own prompts don't show the badge (redundant in "My Prompts" view)

## Workspace Changes

### Sharing UI

Add a "Share" action in prompt metadata panel (PromptMetadata component):
- Only visible to prompt owner
- Shows list of owner's teams with toggle switches
- Toggling shares/unshares the prompt with that team
- When prompt is shared with ≥1 team, visibility auto-updates to `shared`
- When unshared from all teams, visibility reverts to `private`

### Access Control in Workspace

- Workspace route changes: `/prompts/{username}/{slug}`
- On mount, verify current user can see the prompt (owner OR team member)
- Non-owners see a subtle "Shared by {owner}" indicator
- Delete/rename actions hidden for non-owners

## Teams UI

### Teams Page (`/teams`)

New Livewire component `app/Livewire/Teams.php`:

- List teams the user belongs to (as owner or member)
- Create team form (name)
- Click team → team detail page

### Team Detail Page (`/teams/{slug}`)

New Livewire component `app/Livewire/TeamDetail.php`:

- Member list with roles (owner/member)
- Invite member form (email or username)
- Remove member (owner/admin only)
- List of prompts shared with this team
- Leave team action (for non-owners)

### Navigation

Add "Teams" link in the nav bar between Browse and Settings.

## API & MCP Updates

### REST API

- All prompt endpoints change from `{slug}` to `{username}/{slug}`
- `GET /prompts` applies `visibleTo` scope (only returns prompts the API key's user can see)
- New team management endpoints (see URL structure above)
- New share/unshare endpoints
- Legacy `{slug}` routes redirect with 301

### MCP Server

- Update `McpToolHandler` to apply `visibleTo` scope
- Add `owner` parameter to all prompt tools
- Add `list_teams`, `share_prompt`, `delete_prompt` tools
- Update resource URIs to `urge://prompts/{username}/{slug}`
- Update `getToolDefinitions()` and `getResourceDefinitions()`

### OpenAPI Spec

- Bump version to 3.0.0
- Update all prompt paths to include `{username}` parameter
- Add Team schemas and endpoints
- Add share/unshare endpoints
- Update Prompt schema with `visibility` and `owner` fields
- Add legacy redirect documentation

## Testing Strategy

### Unit Tests

- `Team` model: create, relationships, slug generation
- `Prompt::scopeVisibleTo()`: owner sees own, team member sees shared, non-member doesn't see
- Permission checks: owner can delete, non-owner cannot, admin can override

### Feature Tests

- Team CRUD: create, invite, remove, leave, delete
- Prompt sharing: share with team, verify visibility, unshare
- Namespace routing: `/prompts/{username}/{slug}` resolves correctly
- Legacy redirect: `/prompts/{slug}` → `/prompts/{owner}/{slug}`
- API scoping: `GET /prompts` only returns visible prompts
- MCP tools: namespaced access, visibility enforcement
- Migration: existing prompts accessible under owner namespace

### Edge Cases

- User with no teams sees only their own prompts
- Prompt shared with multiple teams: visible to members of any
- Team deletion: prompts unlinked from team but not deleted
- User removal from team: loses access to that team's shared prompts (prompt stays shared)
- Slug collision after migration: two users with same slug handled by owner prefix
- API key scoping + namespace scoping: both filters apply (intersection)

## Migration Plan

1. Create `teams`, `team_user`, `prompt_team` tables
2. Add `visibility` column to `prompts` (default `private`, existing rows set to `shared`)
3. Modify slug index: drop unique on `slug`, add unique on `(created_by, slug)`
4. Add `name` column to `users` if not present (for username in URLs — use existing `name` field, slugified)
5. Ensure all existing prompts have valid `created_by` references
6. Deploy with backward-compatible routing (old URLs redirect)
