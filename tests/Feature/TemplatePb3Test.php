<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Prompt;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\ShareLinkService;
use App\Services\TemplateEngine;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B Sprint 3 regression tests — closes the template-engine,
 * include-DoS, and share-link findings (TPL-01..09).
 *
 * First user is the auto-admin; alice/bob are non-admin tenants so
 * cross-tenant assertions are not bypassed by the visibleTo admin
 * override.
 */
class TemplatePb3Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $alice;
    private User $bob;
    private array $aliceHeaders;
    private TemplateEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'  => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->alice = User::create([
            'name'  => 'Alice',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->bob = User::create([
            'name'  => 'Bob',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
        ]);

        $aliceKey = app(ApiKeyService::class)->generateKey($this->alice, 'Alice Key');
        $this->aliceHeaders = ['Authorization' => "Bearer {$aliceKey['key']}"];

        $this->engine = app(TemplateEngine::class);
    }

    // ---------- TPL-01 / TPL-02: include resolution scoped by user ----------

    public function test_render_with_null_user_does_not_fall_back_to_global_slug(): void
    {
        // Bob has a private fragment with a guessable slug
        $secret = Prompt::create(['name' => 'Secret', 'slug' => 'secret', 'type' => 'fragment', 'created_by' => $this->bob->id]);
        app(VersioningService::class)->createVersion($secret, ['content' => 'BOB_SECRET'], $this->bob);

        // No user supplied → must NOT expand
        $result = $this->engine->render('include: {{>secret}}', []);

        $this->assertStringNotContainsString('BOB_SECRET', $result['rendered']);
        $this->assertEquals('include: {{>secret}}', $result['rendered']);
    }

    public function test_render_with_unauthorized_user_does_not_resolve_private_include(): void
    {
        // Bob owns a private fragment
        $secret = Prompt::create(['name' => 'Secret', 'slug' => 'secret', 'type' => 'fragment', 'created_by' => $this->bob->id]);
        app(VersioningService::class)->createVersion($secret, ['content' => 'BOB_SECRET'], $this->bob);

        // Alice tries to include it
        $result = $this->engine->render('{{>secret}}', [], null, $this->alice);

        $this->assertStringNotContainsString('BOB_SECRET', $result['rendered']);
    }

    public function test_share_page_does_not_leak_other_tenants_private_fragments(): void
    {
        // Bob has a private fragment slug "secret"
        $bobSecret = Prompt::create(['name' => 'Secret', 'slug' => 'secret', 'type' => 'fragment', 'created_by' => $this->bob->id]);
        app(VersioningService::class)->createVersion($bobSecret, ['content' => 'BOB_PRIVATE_DATA'], $this->bob);

        // Alice owns a public-shareable prompt that includes {{>secret}}
        $alicePrompt = Prompt::create(['name' => 'Alice', 'slug' => 'alice-prompt', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($alicePrompt, ['content' => 'before {{>secret}} after'], $this->alice);
        $version = $alicePrompt->versions()->first();

        // Wrap into a collection + share link
        $collection = Collection::create(['title' => 'C', 'created_by' => $this->alice->id]);
        CollectionItem::create([
            'collection_id' => $collection->id,
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
            'sort_order' => 0,
        ]);
        $link = app(ShareLinkService::class)->createLink($collection, $this->alice, null, '1h');

        $response = $this->get("/share/{$link->token}");

        $response->assertStatus(200);
        $this->assertStringNotContainsString('BOB_PRIVATE_DATA', $response->getContent());
    }

    // ---------- TPL-03: variable value type validation ----------

    public function test_render_rejects_array_variable_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Variable 'x' must be a scalar");
        $this->engine->render('{{x}}', ['x' => ['a', 'b']]);
    }

    public function test_render_api_returns_422_for_array_variable_value(): void
    {
        $prompt = Prompt::create(['name' => 'P', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($prompt, ['content' => 'Hi {{x}}'], $this->alice);

        $response = $this->postJson(
            "/api/v1/prompts/{$this->alice->slug}/{$prompt->slug}/render",
            ['variables' => ['x' => ['array', 'not', 'scalar']]],
            $this->aliceHeaders,
        );

        $response->assertStatus(422);
    }

    // ---------- TPL-04: include expansion budget ----------

    public function test_render_blocks_billion_laughs_via_expansion_budget(): void
    {
        // a includes 3 copies of b; b includes 3 of c; ... up to ~10 levels.
        // With depth 10 and fanout 3 you'd see ~3^9 ≈ 19k expansions —
        // budget caps at 500.
        config(['urge.max_include_expansions' => 50]);

        $frag = Prompt::create(['name' => 'F', 'slug' => 'frag', 'type' => 'fragment', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($frag, ['content' => '{{>g}}{{>g}}{{>g}}'], $this->alice);
        $g = Prompt::create(['name' => 'G', 'slug' => 'g', 'type' => 'fragment', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($g, ['content' => 'leaf'], $this->alice);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expansion budget');

        // Top-level template repeats {{>frag}} many times → siblings inflate count
        $top = str_repeat('{{>frag}}', 30);
        $this->engine->render($top, [], null, $this->alice);
    }

    public function test_render_blocks_over_size_budget(): void
    {
        config(['urge.max_render_bytes' => 100]);

        $frag = Prompt::create(['name' => 'Big', 'slug' => 'big', 'type' => 'fragment', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($frag, ['content' => str_repeat('A', 200)], $this->alice);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('size exceeded');
        $this->engine->render('{{>big}}', [], null, $this->alice);
    }

    // ---------- TPL-05: included-fragment metadata does NOT leak defaults ----------

    public function test_included_fragment_metadata_default_does_not_inject_into_parent(): void
    {
        // Bob writes a fragment whose metadata claims a default for `secret_inj`
        $frag = Prompt::create(['name' => 'F', 'slug' => 'f', 'type' => 'fragment', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion(
            $frag,
            ['content' => 'frag-content', 'variable_metadata' => ['secret_inj' => ['default' => 'INJECTED']]],
            $this->alice,
        );

        // Parent prompt references {{secret_inj}} but supplies no metadata
        // and no value. With PB-3, the fragment-supplied default must NOT
        // fill it; the variable stays in `missing`.
        $result = $this->engine->render('{{>f}} | {{secret_inj}}', [], null, $this->alice);

        $this->assertStringNotContainsString('INJECTED', $result['rendered']);
        $this->assertContains('secret_inj', $result['variables_missing']);
    }

    // ---------- TPL-06: share-link expiry required + throttle ----------

    public function test_share_link_creation_requires_expiry(): void
    {
        $collection = Collection::create(['title' => 'C', 'created_by' => $this->alice->id]);

        $response = $this->postJson(
            "/api/v1/collections/{$collection->slug}/share-links",
            ['label' => 'no-expiry'],
            $this->aliceHeaders,
        );

        $response->assertStatus(422);
    }

    public function test_share_link_service_rejects_null_expiry(): void
    {
        $collection = Collection::create(['title' => 'C', 'created_by' => $this->alice->id]);

        $this->expectException(\InvalidArgumentException::class);
        app(ShareLinkService::class)->createLink($collection, $this->alice, null, null);
    }

    public function test_share_route_is_throttled(): void
    {
        $collection = Collection::create(['title' => 'C', 'created_by' => $this->alice->id]);
        $link = app(ShareLinkService::class)->createLink($collection, $this->alice, null, '1h');

        // Throttle is 30/min on /share/{token}; trip it
        $last = null;
        for ($i = 0; $i < 31; $i++) {
            $last = $this->get("/share/{$link->token}");
        }
        $this->assertEquals(429, $last->status());
    }

    // ---------- TPL-07: cycle-safe collection render ----------

    public function test_collection_render_with_cycle_does_not_infinite_recurse(): void
    {
        // Build a cycle bypassing the validator (direct DB insert):
        // parent → child → parent.
        $parent = Collection::create(['title' => 'P', 'created_by' => $this->alice->id]);
        $child  = Collection::create(['title' => 'C', 'created_by' => $this->alice->id]);

        CollectionItem::create(['collection_id' => $parent->id, 'item_type' => 'collection', 'item_id' => $child->id, 'sort_order' => 0]);
        CollectionItem::create(['collection_id' => $child->id,  'item_type' => 'collection', 'item_id' => $parent->id, 'sort_order' => 0]);

        $link = app(ShareLinkService::class)->createLink($parent, $this->alice, null, '1h');

        $response = $this->get("/share/{$link->token}");

        // We don't care what it shows, only that it returned at all
        $response->assertStatus(200);
    }
}
