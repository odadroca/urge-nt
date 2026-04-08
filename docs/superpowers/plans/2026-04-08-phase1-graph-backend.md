# Phase 1: Graph Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add graph position storage, graph API endpoints, temporary SPA auth middleware, and SPA catch-all route — the backend foundation for the React Flow canvas.

**Architecture:** New `graph_positions` table stores per-user node positions. New `GraphController` exposes 5 endpoints for fetching nodes/edges and managing positions/includes. A temporary `spa.auth` middleware reuses Laravel's session auth for the SPA. A Blade wrapper at `/app/*` will serve the React SPA (Phase 2).

**Tech Stack:** Laravel 12 / PHP 8.3, SQLite, existing TemplateEngine + VersioningService

**Spec:** `docs/superpowers/specs/2026-04-08-react-flow-migration-design.md`

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `database/migrations/2026_04_08_000001_create_graph_positions_table.php` | Graph position schema |
| `app/Models/GraphPosition.php` | Position model with `bulkUpsert()` |
| `app/Http/Controllers/Api/GraphController.php` | 5 graph API endpoints |
| `resources/views/spa.blade.php` | Minimal Blade wrapper for React SPA entry point |
| `tests/Feature/Api/GraphApiTest.php` | Tests for all graph endpoints |
| `tests/Feature/GraphPositionTest.php` | Tests for GraphPosition model |

### Modified Files

| File | Change |
|------|--------|
| `bootstrap/app.php` | Add `spa.auth` middleware alias |
| `routes/api.php` | Add graph endpoint routes |
| `routes/web.php` | Add `/app/{any?}` catch-all route |

---

## Task 1: GraphPosition Migration + Model

**Files:**
- Create: `database/migrations/2026_04_08_000001_create_graph_positions_table.php`
- Create: `app/Models/GraphPosition.php`
- Create: `tests/Feature/GraphPositionTest.php`

- [ ] **Step 1: Write the failing test for GraphPosition**

```php
<?php
// tests/Feature/GraphPositionTest.php

namespace Tests\Feature;

use App\Models\GraphPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphPositionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'graphtest@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_bulk_upsert_creates_positions(): void
    {
        $positions = [
            ['node_type' => 'prompt', 'node_id' => 1, 'x' => 100.0, 'y' => 200.0],
            ['node_type' => 'fragment', 'node_id' => 2, 'x' => 300.0, 'y' => 400.0],
        ];

        $count = GraphPosition::bulkUpsert($this->user->id, $positions);

        $this->assertEquals(2, $count);
        $this->assertDatabaseCount('graph_positions', 2);
        $this->assertDatabaseHas('graph_positions', [
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 100.0,
            'y' => 200.0,
        ]);
    }

    public function test_bulk_upsert_updates_existing_positions(): void
    {
        GraphPosition::create([
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 100.0,
            'y' => 200.0,
        ]);

        $positions = [
            ['node_type' => 'prompt', 'node_id' => 1, 'x' => 500.0, 'y' => 600.0],
        ];

        GraphPosition::bulkUpsert($this->user->id, $positions);

        $this->assertDatabaseCount('graph_positions', 1);
        $this->assertDatabaseHas('graph_positions', [
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 500.0,
            'y' => 600.0,
        ]);
    }

    public function test_bulk_upsert_scoped_to_user(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        GraphPosition::create([
            'user_id' => $otherUser->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 10.0,
            'y' => 20.0,
        ]);

        $positions = [
            ['node_type' => 'prompt', 'node_id' => 1, 'x' => 500.0, 'y' => 600.0],
        ];

        GraphPosition::bulkUpsert($this->user->id, $positions);

        $this->assertDatabaseCount('graph_positions', 2);
        $this->assertDatabaseHas('graph_positions', [
            'user_id' => $otherUser->id,
            'x' => 10.0,
        ]);
        $this->assertDatabaseHas('graph_positions', [
            'user_id' => $this->user->id,
            'x' => 500.0,
        ]);
    }

    public function test_belongs_to_user(): void
    {
        $position = GraphPosition::create([
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 100.0,
            'y' => 200.0,
        ]);

        $this->assertEquals($this->user->id, $position->user->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/GraphPositionTest.php`
Expected: FAIL — table `graph_positions` does not exist

- [ ] **Step 3: Create the migration**

```php
<?php
// database/migrations/2026_04_08_000001_create_graph_positions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('node_type', 20);
            $table->unsignedBigInteger('node_id');
            $table->float('x');
            $table->float('y');
            $table->timestamps();

            $table->unique(['user_id', 'node_type', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_positions');
    }
};
```

- [ ] **Step 4: Create the GraphPosition model**

```php
<?php
// app/Models/GraphPosition.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraphPosition extends Model
{
    protected $fillable = ['user_id', 'node_type', 'node_id', 'x', 'y'];

    protected $casts = [
        'node_id' => 'integer',
        'x' => 'float',
        'y' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Bulk upsert positions for a user. Returns number of rows affected.
     */
    public static function bulkUpsert(int $userId, array $positions): int
    {
        $rows = array_map(fn (array $pos) => [
            'user_id' => $userId,
            'node_type' => $pos['node_type'],
            'node_id' => $pos['node_id'],
            'x' => $pos['x'],
            'y' => $pos['y'],
            'updated_at' => now(),
            'created_at' => now(),
        ], $positions);

        return self::upsert(
            $rows,
            ['user_id', 'node_type', 'node_id'],
            ['x', 'y', 'updated_at']
        );
    }
}
```

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate && php artisan test tests/Feature/GraphPositionTest.php`
Expected: All 4 tests PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_04_08_000001_create_graph_positions_table.php app/Models/GraphPosition.php tests/Feature/GraphPositionTest.php
git commit -m "feat: add graph_positions table and GraphPosition model"
```

---

## Task 2: SPA Auth Middleware + Catch-All Route

**Files:**
- Modify: `bootstrap/app.php` (line 16 — middleware alias array)
- Modify: `routes/web.php` (add after existing routes)
- Create: `resources/views/spa.blade.php`

- [ ] **Step 1: Add `spa.auth` middleware alias to bootstrap/app.php**

In `bootstrap/app.php`, add `'spa.auth'` to the middleware alias array (currently at line 16):

```php
$middleware->alias([
    'role'     => \App\Http\Middleware\RequireRole::class,
    'api.auth' => \App\Http\Middleware\ApiKeyAuthentication::class,
    'spa.auth' => 'auth',
]);
```

- [ ] **Step 2: Create spa.blade.php**

```html
<!-- resources/views/spa.blade.php -->
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'URGE') }}</title>
    @viteReactRefresh
    @vite(['resources/js/spa/main.jsx'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
```

- [ ] **Step 3: Add SPA catch-all route to routes/web.php**

Add at the very end of `routes/web.php`, after all existing routes:

```php
// SPA catch-all — serves React app at /app/*
Route::get('/app/{any?}', function () {
    return view('spa');
})->where('any', '.*')->middleware('auth')->name('spa');
```

- [ ] **Step 4: Verify routes**

Run: `php artisan route:list --path=app`
Expected: Shows `GET|HEAD app/{any?}` route with `auth` middleware

- [ ] **Step 5: Commit**

```bash
git add bootstrap/app.php routes/web.php resources/views/spa.blade.php
git commit -m "feat: add spa.auth middleware and /app catch-all route"
```

---

## Task 3: GraphController — Nodes Endpoint

**Files:**
- Create: `app/Http/Controllers/Api/GraphController.php`
- Modify: `routes/api.php` (add graph routes)
- Create: `tests/Feature/Api/GraphApiTest.php`

- [ ] **Step 1: Write failing test for GET /graph/nodes**

```php
<?php
// tests/Feature/Api/GraphApiTest.php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Collection;
use App\Models\GraphPosition;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Graph User',
            'email' => 'graph@example.com',
            'password' => bcrypt('password'),
            'slug' => 'graph-user',
        ]);
        $result = app(ApiKeyService::class)->generateKey($this->user, 'Graph Key');
        $this->apiKey = $result['key'];
        $this->headers = ['Authorization' => "Bearer {$this->apiKey}"];
    }

    public function test_get_nodes_returns_prompts_and_fragments(): void
    {
        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        $fragment = Prompt::create([
            'name' => 'Test Fragment',
            'type' => 'fragment',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.prompts')
            ->assertJsonPath('data.prompts.0.name', 'Test Prompt')
            ->assertJsonPath('data.prompts.1.name', 'Test Fragment');
    }

    public function test_get_nodes_includes_positions_for_current_user(): void
    {
        $prompt = Prompt::create([
            'name' => 'Positioned Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);
        GraphPosition::create([
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => $prompt->id,
            'x' => 150.0,
            'y' => 250.0,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.prompts.0.position.x', 150.0)
            ->assertJsonPath('data.prompts.0.position.y', 250.0);
    }

    public function test_get_nodes_returns_null_position_when_not_set(): void
    {
        Prompt::create([
            'name' => 'No Position',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.prompts.0.position', null);
    }

    public function test_get_nodes_includes_collections(): void
    {
        Collection::create([
            'title' => 'Test Collection',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.collections')
            ->assertJsonPath('data.collections.0.title', 'Test Collection');
    }

    public function test_get_nodes_respects_visibility(): void
    {
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'slug' => 'other',
        ]);
        Prompt::create([
            'name' => 'Private Prompt',
            'type' => 'prompt',
            'created_by' => $otherUser->id,
            'visibility' => 'private',
        ]);

        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.prompts');
    }

    public function test_get_nodes_includes_truncation_metadata(): void
    {
        $response = $this->getJson('/api/v1/graph/nodes', $this->headers);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['prompts', 'collections'], 'meta' => ['total_count', 'truncated']]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Api/GraphApiTest.php`
Expected: FAIL — route not defined

- [ ] **Step 3: Create GraphController with nodes() method**

```php
<?php
// app/Http/Controllers/Api/GraphController.php

namespace App\Http\Controllers\Api;

use App\Models\Collection;
use App\Models\GraphPosition;
use App\Models\Prompt;
use App\Services\TemplateEngine;
use App\Services\VersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphController extends ApiController
{
    private const MAX_NODES = 500;

    public function __construct(
        private TemplateEngine $templateEngine,
        private VersioningService $versioningService,
    ) {}

    public function nodes(Request $request): JsonResponse
    {
        $user = $request->user();

        $prompts = Prompt::visibleTo($user)
            ->with(['category', 'versions' => fn ($q) => $q->latest('version_number')->limit(1)])
            ->withCount(['versions', 'results'])
            ->latest('updated_at')
            ->limit(self::MAX_NODES)
            ->get();

        $totalPrompts = Prompt::visibleTo($user)->count();

        $positions = GraphPosition::where('user_id', $user->id)
            ->get()
            ->keyBy(fn ($p) => "{$p->node_type}:{$p->node_id}");

        $promptData = $prompts->map(function (Prompt $prompt) use ($positions) {
            $posKey = "prompt:{$prompt->id}";
            if ($prompt->type === 'fragment') {
                $posKey = "fragment:{$prompt->id}";
            }
            $position = $positions->get($posKey);
            $activeVersion = $prompt->activeVersion;

            return [
                'id' => $prompt->id,
                'slug' => $prompt->slug,
                'name' => $prompt->name,
                'type' => $prompt->type,
                'description' => $prompt->description,
                'tags' => $prompt->tags,
                'category' => $prompt->category ? [
                    'name' => $prompt->category->name,
                    'color' => $prompt->category->color,
                ] : null,
                'owner' => $prompt->creator->slug ?? $prompt->creator->name ?? null,
                'active_version' => $activeVersion ? [
                    'version_number' => $activeVersion->version_number,
                    'content' => $activeVersion->content,
                    'variables' => $activeVersion->variables,
                    'includes' => $activeVersion->includes,
                ] : null,
                'versions_count' => $prompt->versions_count,
                'results_count' => $prompt->results_count,
                'position' => $position ? ['x' => $position->x, 'y' => $position->y] : null,
            ];
        });

        $collections = Collection::where('created_by', $user->id)
            ->withCount('items')
            ->latest('updated_at')
            ->get();

        $collectionData = $collections->map(function (Collection $collection) use ($positions) {
            $posKey = "collection:{$collection->id}";
            $position = $positions->get($posKey);

            return [
                'id' => $collection->id,
                'slug' => $collection->slug,
                'title' => $collection->title,
                'description' => $collection->description,
                'items_count' => $collection->items_count,
                'position' => $position ? ['x' => $position->x, 'y' => $position->y] : null,
            ];
        });

        $totalCount = $totalPrompts + $collections->count();

        return $this->success([
            'prompts' => $promptData,
            'collections' => $collectionData,
        ], 200, [
            'total_count' => $totalCount,
            'truncated' => $totalPrompts > self::MAX_NODES,
        ]);
    }
}
```

**Note:** The base `ApiController::success()` returns `{'data': mixed}`. Since we need a `meta` key alongside `data`, return `response()->json()` directly instead of using the base method for this endpoint.

- [ ] **Step 4: Add the nodes route to routes/api.php**

Inside the `Route::middleware('api.auth')->group(function () { ... })` block, add:

```php
// Graph endpoints
Route::get('graph/nodes', [GraphController::class, 'nodes']);
```

Add the import at the top of `routes/api.php`:

```php
use App\Http\Controllers\Api\GraphController;
```

- [ ] **Step 5: Fix the Prompt model — add creator relationship if missing**

Check if `Prompt` has a `creator()` relationship. The model uses `created_by` FK. If not present, add to `app/Models/Prompt.php`:

```php
public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(User::class, 'created_by');
}
```

- [ ] **Step 6: Run tests**

Run: `php artisan test tests/Feature/Api/GraphApiTest.php`
Expected: All 6 nodes tests PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/GraphController.php tests/Feature/Api/GraphApiTest.php routes/api.php app/Models/Prompt.php
git commit -m "feat: add GET /graph/nodes endpoint"
```

---

## Task 4: GraphController — Positions Endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/GraphController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Api/GraphApiTest.php`

- [ ] **Step 1: Write failing test for POST /graph/positions**

Add to `tests/Feature/Api/GraphApiTest.php`:

```php
public function test_save_positions(): void
{
    Prompt::create([
        'name' => 'P1',
        'type' => 'prompt',
        'created_by' => $this->user->id,
    ]);

    $response = $this->postJson('/api/v1/graph/positions', [
        'positions' => [
            ['node_type' => 'prompt', 'node_id' => 1, 'x' => 100.5, 'y' => 200.5],
            ['node_type' => 'fragment', 'node_id' => 2, 'x' => 300.0, 'y' => 400.0],
        ],
    ], $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('data.saved', 2);

    $this->assertDatabaseHas('graph_positions', [
        'user_id' => $this->user->id,
        'node_type' => 'prompt',
        'node_id' => 1,
        'x' => 100.5,
    ]);
}

public function test_save_positions_validates_input(): void
{
    $response = $this->postJson('/api/v1/graph/positions', [
        'positions' => [
            ['node_type' => 'invalid', 'node_id' => 1, 'x' => 100, 'y' => 200],
        ],
    ], $this->headers);

    $response->assertStatus(422);
}

public function test_save_positions_empty_array(): void
{
    $response = $this->postJson('/api/v1/graph/positions', [
        'positions' => [],
    ], $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('data.saved', 0);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Api/GraphApiTest.php --filter=save_positions`
Expected: FAIL — route not defined

- [ ] **Step 3: Add positions() method to GraphController**

Add to `app/Http/Controllers/Api/GraphController.php`:

```php
public function positions(Request $request): JsonResponse
{
    $validated = $request->validate([
        'positions' => ['required', 'array'],
        'positions.*.node_type' => ['required', 'string', 'in:prompt,fragment,collection'],
        'positions.*.node_id' => ['required', 'integer', 'min:1'],
        'positions.*.x' => ['required', 'numeric'],
        'positions.*.y' => ['required', 'numeric'],
    ]);

    $count = 0;
    if (! empty($validated['positions'])) {
        $count = GraphPosition::bulkUpsert($request->user()->id, $validated['positions']);
    }

    return $this->success(['saved' => $count]);
}
```

- [ ] **Step 4: Add the route**

In `routes/api.php`, inside the `api.auth` group, add below the nodes route:

```php
Route::post('graph/positions', [GraphController::class, 'positions']);
```

- [ ] **Step 5: Run tests**

Run: `php artisan test tests/Feature/Api/GraphApiTest.php --filter=save_positions`
Expected: All 3 positions tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/GraphController.php routes/api.php tests/Feature/Api/GraphApiTest.php
git commit -m "feat: add POST /graph/positions endpoint"
```

---

## Task 5: GraphController — Edges Endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/GraphController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Api/GraphApiTest.php`

- [ ] **Step 1: Write failing test for GET /graph/edges**

Add to `tests/Feature/Api/GraphApiTest.php`:

```php
public function test_get_edges_from_includes(): void
{
    $prompt = Prompt::create([
        'name' => 'Main Prompt',
        'type' => 'prompt',
        'created_by' => $this->user->id,
    ]);
    $fragment = Prompt::create([
        'name' => 'My Fragment',
        'slug' => 'my-fragment',
        'type' => 'fragment',
        'created_by' => $this->user->id,
    ]);

    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'content' => 'Hello {{>my-fragment}} world',
        'version_number' => 1,
        'variables' => [],
        'includes' => ['my-fragment'],
        'branch_id' => $prompt->defaultBranch?->id ?? $this->createDefaultBranch($prompt),
        'branch_version_number' => 1,
    ]);

    $response = $this->getJson('/api/v1/graph/edges', $this->headers);

    $response->assertStatus(200);

    $edges = $response->json('data.composition');
    $this->assertCount(1, $edges);
    $this->assertEquals($prompt->slug, $edges[0]['source_slug']);
    $this->assertEquals('my-fragment', $edges[0]['target_slug']);
    $this->assertEquals('includes', $edges[0]['type']);
}

public function test_get_edges_from_collections(): void
{
    $prompt = Prompt::create([
        'name' => 'Collected Prompt',
        'type' => 'prompt',
        'created_by' => $this->user->id,
    ]);
    $version = PromptVersion::create([
        'prompt_id' => $prompt->id,
        'content' => 'Simple content',
        'version_number' => 1,
        'variables' => [],
        'includes' => [],
        'branch_id' => $prompt->defaultBranch?->id ?? $this->createDefaultBranch($prompt),
        'branch_version_number' => 1,
    ]);
    $collection = Collection::create([
        'title' => 'My Collection',
        'created_by' => $this->user->id,
    ]);
    $collection->items()->create([
        'item_type' => 'prompt_version',
        'item_id' => $version->id,
        'sort_order' => 0,
    ]);

    $response = $this->getJson('/api/v1/graph/edges', $this->headers);

    $response->assertStatus(200);

    $collectionEdges = $response->json('data.collection');
    $this->assertCount(1, $collectionEdges);
    $this->assertEquals($collection->slug, $collectionEdges[0]['collection_slug']);
}

/**
 * Helper to create a default branch for a prompt in tests.
 */
private function createDefaultBranch(Prompt $prompt): int
{
    $branch = \App\Models\PromptBranch::create([
        'prompt_id' => $prompt->id,
        'name' => 'main',
        'is_default' => true,
        'created_by' => $this->user->id,
    ]);
    $prompt->update(['default_branch_id' => $branch->id]);
    return $branch->id;
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Api/GraphApiTest.php --filter=edges`
Expected: FAIL — route not defined

- [ ] **Step 3: Add edges() method to GraphController**

Add to `app/Http/Controllers/Api/GraphController.php`:

```php
public function edges(Request $request): JsonResponse
{
    $user = $request->user();

    // Composition edges: derived from {{>slug}} includes in prompt content
    $prompts = Prompt::visibleTo($user)->get();
    $compositionEdges = [];

    foreach ($prompts as $prompt) {
        $activeVersion = $prompt->activeVersion;
        if (! $activeVersion) {
            continue;
        }

        $includes = $this->templateEngine->extractIncludes($activeVersion->content);
        foreach ($includes as $includeSlug) {
            $compositionEdges[] = [
                'source_id' => $prompt->id,
                'source_slug' => $prompt->slug,
                'source_type' => $prompt->type,
                'target_slug' => $includeSlug,
                'type' => 'includes',
            ];
        }
    }

    // Collection edges: from CollectionItem relationships
    $collections = Collection::where('created_by', $user->id)->with('items')->get();
    $collectionEdges = [];

    foreach ($collections as $collection) {
        foreach ($collection->items as $item) {
            $collectionEdges[] = [
                'collection_id' => $collection->id,
                'collection_slug' => $collection->slug,
                'item_type' => $item->item_type,
                'item_id' => $item->item_id,
            ];
        }
    }

    return response()->json([
        'data' => [
            'composition' => $compositionEdges,
            'collection' => $collectionEdges,
        ],
    ]);
}
```

- [ ] **Step 4: Add the route**

In `routes/api.php`, inside the `api.auth` group:

```php
Route::get('graph/edges', [GraphController::class, 'edges']);
```

- [ ] **Step 5: Run tests**

Run: `php artisan test tests/Feature/Api/GraphApiTest.php --filter=edges`
Expected: All 2 edges tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/GraphController.php routes/api.php tests/Feature/Api/GraphApiTest.php
git commit -m "feat: add GET /graph/edges endpoint"
```

---

## Task 6: GraphController — Append/Remove Include Endpoints

**Files:**
- Modify: `app/Http/Controllers/Api/GraphController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Api/GraphApiTest.php`

- [ ] **Step 1: Write failing tests for append and remove include**

Add to `tests/Feature/Api/GraphApiTest.php`:

```php
public function test_append_include(): void
{
    $prompt = Prompt::create([
        'name' => 'Target Prompt',
        'type' => 'prompt',
        'created_by' => $this->user->id,
    ]);
    $branchId = $this->createDefaultBranch($prompt);
    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'content' => 'Original content',
        'version_number' => 1,
        'variables' => [],
        'includes' => [],
        'branch_id' => $branchId,
        'branch_version_number' => 1,
    ]);

    Prompt::create([
        'name' => 'Fragment To Include',
        'slug' => 'fragment-to-include',
        'type' => 'fragment',
        'created_by' => $this->user->id,
    ]);

    $response = $this->postJson(
        "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/append-include",
        ['fragment_slug' => 'fragment-to-include'],
        $this->headers
    );

    $response->assertStatus(200);

    $newVersion = PromptVersion::where('prompt_id', $prompt->id)
        ->latest('version_number')
        ->first();
    $this->assertStringContainsString('{{>fragment-to-include}}', $newVersion->content);
    $this->assertEquals(2, $newVersion->version_number);
}

public function test_append_include_rejects_nonexistent_fragment(): void
{
    $prompt = Prompt::create([
        'name' => 'Target',
        'type' => 'prompt',
        'created_by' => $this->user->id,
    ]);
    $branchId = $this->createDefaultBranch($prompt);
    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'content' => 'Content',
        'version_number' => 1,
        'variables' => [],
        'includes' => [],
        'branch_id' => $branchId,
        'branch_version_number' => 1,
    ]);

    $response = $this->postJson(
        "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/append-include",
        ['fragment_slug' => 'nonexistent'],
        $this->headers
    );

    $response->assertStatus(404);
}

public function test_remove_include(): void
{
    $prompt = Prompt::create([
        'name' => 'Has Include',
        'type' => 'prompt',
        'created_by' => $this->user->id,
    ]);
    $branchId = $this->createDefaultBranch($prompt);
    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'content' => "First line\n{{>my-frag}}\nLast line",
        'version_number' => 1,
        'variables' => [],
        'includes' => ['my-frag'],
        'branch_id' => $branchId,
        'branch_version_number' => 1,
    ]);

    $response = $this->deleteJson(
        "/api/v1/prompts/{$this->user->slug}/{$prompt->slug}/remove-include",
        ['fragment_slug' => 'my-frag'],
        $this->headers
    );

    $response->assertStatus(200);

    $newVersion = PromptVersion::where('prompt_id', $prompt->id)
        ->latest('version_number')
        ->first();
    $this->assertStringNotContainsString('{{>my-frag}}', $newVersion->content);
    $this->assertEquals(2, $newVersion->version_number);
}

// Uses PHPUnit's built-in assertStringContainsString / assertStringNotContainsString
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Api/GraphApiTest.php --filter=include`
Expected: FAIL — route not defined

- [ ] **Step 3: Add appendInclude() and removeInclude() methods to GraphController**

Add to `app/Http/Controllers/Api/GraphController.php`:

```php
public function appendInclude(Request $request, string $username, string $promptSlug): JsonResponse
{
    $validated = $request->validate([
        'fragment_slug' => ['required', 'string'],
    ]);

    $user = $request->user();
    $prompt = Prompt::visibleTo($user)->whereHas('creator', fn ($q) => $q->where('slug', $username))
        ->where('slug', $promptSlug)->first();

    if (! $prompt) {
        return $this->error('Prompt not found', 404);
    }

    // Verify fragment exists
    $fragment = Prompt::where('slug', $validated['fragment_slug'])->where('type', 'fragment')->first();
    if (! $fragment) {
        return $this->error('Fragment not found', 404);
    }

    $activeVersion = $prompt->activeVersion;
    if (! $activeVersion) {
        return $this->error('Prompt has no active version', 400);
    }

    $newContent = $activeVersion->content . "\n{{>{$validated['fragment_slug']}}}";

    $version = $this->versioningService->createVersion($prompt, [
        'content' => $newContent,
        'commit_message' => "Added include: {$validated['fragment_slug']}",
        'variable_metadata' => $activeVersion->variable_metadata,
    ], $user);

    return $this->success([
        'version_number' => $version->version_number,
        'content' => $version->content,
        'includes' => $version->includes,
    ]);
}

public function removeInclude(Request $request, string $username, string $promptSlug): JsonResponse
{
    $validated = $request->validate([
        'fragment_slug' => ['required', 'string'],
    ]);

    $user = $request->user();
    $prompt = Prompt::visibleTo($user)->whereHas('creator', fn ($q) => $q->where('slug', $username))
        ->where('slug', $promptSlug)->first();

    if (! $prompt) {
        return $this->error('Prompt not found', 404);
    }

    $activeVersion = $prompt->activeVersion;
    if (! $activeVersion) {
        return $this->error('Prompt has no active version', 400);
    }

    $slug = preg_quote($validated['fragment_slug'], '/');
    $newContent = preg_replace("/\n?\{\{>{$slug}\}\}/", '', $activeVersion->content);
    $newContent = trim($newContent);

    if ($newContent === trim($activeVersion->content)) {
        return $this->error('Include not found in content', 400);
    }

    $version = $this->versioningService->createVersion($prompt, [
        'content' => $newContent,
        'commit_message' => "Removed include: {$validated['fragment_slug']}",
        'variable_metadata' => $activeVersion->variable_metadata,
    ], $user);

    return $this->success([
        'version_number' => $version->version_number,
        'content' => $version->content,
        'includes' => $version->includes,
    ]);
}
```

- [ ] **Step 4: Add the routes**

In `routes/api.php`, inside the `api.auth` group:

```php
Route::post('prompts/{username}/{promptSlug}/append-include', [GraphController::class, 'appendInclude']);
Route::delete('prompts/{username}/{promptSlug}/remove-include', [GraphController::class, 'removeInclude']);
```

- [ ] **Step 5: Run tests**

Run: `php artisan test tests/Feature/Api/GraphApiTest.php --filter=include`
Expected: All 3 include tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/GraphController.php routes/api.php tests/Feature/Api/GraphApiTest.php
git commit -m "feat: add append-include and remove-include endpoints"
```

---

## Task 7: Full Test Suite Verification

**Files:** None — verification only

- [ ] **Step 1: Run the full graph test suite**

Run: `php artisan test tests/Feature/Api/GraphApiTest.php tests/Feature/GraphPositionTest.php -v`
Expected: All tests PASS (approximately 14 tests)

- [ ] **Step 2: Run the entire project test suite**

Run: `php artisan test`
Expected: All 307+ tests PASS (existing 307 + new graph tests)

- [ ] **Step 3: Verify route list**

Run: `php artisan route:list --path=graph`
Expected output shows:
```
GET|HEAD  api/v1/graph/nodes ......... GraphController@nodes
POST      api/v1/graph/positions ..... GraphController@positions
GET|HEAD  api/v1/graph/edges ......... GraphController@edges
```

Run: `php artisan route:list --path=append-include`
Expected output shows:
```
POST      api/v1/prompts/{username}/{promptSlug}/append-include ... GraphController@appendInclude
```

Run: `php artisan route:list --path=app`
Expected output shows:
```
GET|HEAD  app/{any?} ................ spa
```

- [ ] **Step 4: Final commit and push**

```bash
git push origin main
```

---

## Summary

| Task | What it builds | Tests |
|------|---------------|-------|
| 1 | GraphPosition migration + model | 4 model tests |
| 2 | spa.auth middleware + /app catch-all + spa.blade.php | Route verification |
| 3 | GET /graph/nodes | 6 endpoint tests |
| 4 | POST /graph/positions | 3 endpoint tests |
| 5 | GET /graph/edges | 2 endpoint tests |
| 6 | POST append-include + DELETE remove-include | 3 endpoint tests |
| 7 | Full suite verification | All 307+ tests pass |
