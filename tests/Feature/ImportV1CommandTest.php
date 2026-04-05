<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportV1CommandTest extends TestCase
{
    use RefreshDatabase;

    private string $v1DbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->v1DbPath = tempnam(sys_get_temp_dir(), 'urge_v1_test_') . '.sqlite';
        touch($this->v1DbPath);
        $this->createV1Database();
    }

    protected function tearDown(): void
    {
        // Purge all v1-related connections to release file locks
        foreach (['v1', 'v1_setup', 'v1_seed'] as $conn) {
            try {
                DB::purge($conn);
            } catch (\Exception) {
            }
        }

        if (file_exists($this->v1DbPath)) {
            @unlink($this->v1DbPath);
        }
        parent::tearDown();
    }

    private function createV1Database(): void
    {
        config(['database.connections.v1_setup' => [
            'driver' => 'sqlite',
            'database' => $this->v1DbPath,
            'prefix' => '',
        ]]);

        $db = DB::connection('v1_setup');

        $db->statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT "viewer",
            created_at TEXT,
            updated_at TEXT
        )');

        $db->statement('CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            color TEXT DEFAULT "gray",
            sort_order INTEGER DEFAULT 0,
            created_at TEXT,
            updated_at TEXT
        )');

        $db->statement('CREATE TABLE prompts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            description TEXT,
            category_id INTEGER,
            tags TEXT,
            active_version_id INTEGER,
            created_by INTEGER,
            deleted_at TEXT,
            created_at TEXT,
            updated_at TEXT
        )');

        $db->statement('CREATE TABLE prompt_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prompt_id INTEGER NOT NULL,
            version_number INTEGER NOT NULL,
            content TEXT NOT NULL,
            commit_message TEXT,
            variables TEXT,
            variable_metadata TEXT,
            includes TEXT,
            created_by INTEGER,
            created_at TEXT
        )');

        $db->statement('CREATE TABLE llm_providers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            driver TEXT NOT NULL,
            name TEXT NOT NULL,
            model TEXT NOT NULL,
            base_url TEXT,
            api_key_encrypted TEXT,
            enabled INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT,
            updated_at TEXT
        )');

        $db->statement('CREATE TABLE prompt_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prompt_id INTEGER NOT NULL,
            prompt_version_id INTEGER NOT NULL,
            rendered_content TEXT,
            variables_used TEXT,
            created_by INTEGER,
            created_at TEXT
        )');

        $db->statement('CREATE TABLE llm_responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prompt_run_id INTEGER NOT NULL,
            llm_provider_id INTEGER,
            model_used TEXT,
            response_text TEXT,
            input_tokens INTEGER,
            output_tokens INTEGER,
            duration_ms INTEGER,
            status TEXT DEFAULT "success",
            error_message TEXT,
            rating INTEGER,
            created_at TEXT
        )');

        $db->statement('CREATE TABLE library_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prompt_id INTEGER NOT NULL,
            prompt_version_id INTEGER NOT NULL,
            llm_provider_id INTEGER,
            model_used TEXT,
            response_text TEXT,
            notes TEXT,
            rating INTEGER,
            created_by INTEGER,
            created_at TEXT,
            updated_at TEXT
        )');

        $db->statement('CREATE TABLE stories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            created_by INTEGER,
            created_at TEXT,
            updated_at TEXT
        )');

        $db->statement('CREATE TABLE story_steps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            story_id INTEGER NOT NULL,
            sort_order INTEGER DEFAULT 0,
            prompt_id INTEGER,
            prompt_version_id INTEGER,
            library_entry_id INTEGER,
            notes TEXT,
            created_at TEXT,
            updated_at TEXT
        )');

        $db->statement('CREATE TABLE api_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            key_hash TEXT NOT NULL UNIQUE,
            key_encrypted TEXT,
            key_preview TEXT,
            last_used_at TEXT,
            expires_at TEXT,
            created_at TEXT,
            updated_at TEXT
        )');

        $db->statement('CREATE TABLE api_key_prompt (
            api_key_id INTEGER NOT NULL,
            prompt_id INTEGER NOT NULL,
            PRIMARY KEY (api_key_id, prompt_id)
        )');

        DB::purge('v1_setup');
    }

    private function seedV1Data(): void
    {
        config(['database.connections.v1_seed' => [
            'driver' => 'sqlite',
            'database' => $this->v1DbPath,
            'prefix' => '',
        ]]);

        $db = DB::connection('v1_seed');

        // Users
        $db->table('users')->insert([
            ['id' => 1, 'name' => 'Admin User', 'email' => 'admin@test.com', 'password' => bcrypt('password'), 'role' => 'admin', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 2, 'name' => 'Editor User', 'email' => 'editor@test.com', 'password' => bcrypt('password'), 'role' => 'editor', 'created_at' => '2025-01-02 00:00:00', 'updated_at' => '2025-01-02 00:00:00'],
        ]);

        // Categories
        $db->table('categories')->insert([
            ['id' => 1, 'name' => 'Writing', 'slug' => 'writing', 'color' => 'blue', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
        ]);

        // Prompts
        $db->table('prompts')->insert([
            ['id' => 1, 'name' => 'Test Prompt', 'slug' => 'test-prompt', 'description' => 'A test prompt', 'category_id' => 1, 'tags' => '["test","demo"]', 'active_version_id' => 2, 'created_by' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00', 'deleted_at' => null],
        ]);

        // Prompt Versions
        $db->table('prompt_versions')->insert([
            ['id' => 1, 'prompt_id' => 1, 'version_number' => 1, 'content' => 'Hello {{ name }}', 'commit_message' => 'Initial version', 'variables' => '["name"]', 'variable_metadata' => null, 'created_by' => 1, 'created_at' => '2025-01-01 00:00:00'],
            ['id' => 2, 'prompt_id' => 1, 'version_number' => 2, 'content' => 'Hello {{ name }}, welcome!', 'commit_message' => 'Added greeting', 'variables' => '["name"]', 'variable_metadata' => '{"name":{"type":"string","default":"World"}}', 'created_by' => 1, 'created_at' => '2025-01-02 00:00:00'],
        ]);

        // LLM Providers
        $encryptedKey = Crypt::encryptString('sk-test-key-12345');
        $db->table('llm_providers')->insert([
            ['id' => 1, 'driver' => 'openai', 'name' => 'OpenAI', 'model' => 'gpt-4', 'base_url' => null, 'api_key_encrypted' => $encryptedKey, 'enabled' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 2, 'driver' => 'ollama', 'name' => 'Ollama', 'model' => 'llama3', 'base_url' => 'http://localhost:11434', 'api_key_encrypted' => null, 'enabled' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
        ]);

        // Prompt Runs + LLM Responses
        $db->table('prompt_runs')->insert([
            ['id' => 1, 'prompt_id' => 1, 'prompt_version_id' => 2, 'rendered_content' => 'Hello World, welcome!', 'variables_used' => '{"name":"World"}', 'created_by' => 1, 'created_at' => '2025-01-03 00:00:00'],
        ]);

        $db->table('llm_responses')->insert([
            ['id' => 1, 'prompt_run_id' => 1, 'llm_provider_id' => 1, 'model_used' => 'gpt-4', 'response_text' => 'Hello! Nice to meet you, World.', 'input_tokens' => 10, 'output_tokens' => 15, 'duration_ms' => 1200, 'status' => 'success', 'error_message' => null, 'rating' => 4, 'created_at' => '2025-01-03 00:00:00'],
        ]);

        // Library Entries
        $db->table('library_entries')->insert([
            ['id' => 1, 'prompt_id' => 1, 'prompt_version_id' => 1, 'llm_provider_id' => 1, 'model_used' => 'gpt-4', 'response_text' => 'A saved library entry.', 'notes' => 'Great response', 'rating' => 5, 'created_by' => 1, 'created_at' => '2025-01-04 00:00:00', 'updated_at' => '2025-01-04 00:00:00'],
        ]);

        // Stories + Steps
        $db->table('stories')->insert([
            ['id' => 1, 'title' => 'My Story', 'description' => 'A test story', 'created_by' => 1, 'created_at' => '2025-01-05 00:00:00', 'updated_at' => '2025-01-05 00:00:00'],
        ]);

        $db->table('story_steps')->insert([
            ['id' => 1, 'story_id' => 1, 'sort_order' => 0, 'prompt_id' => 1, 'prompt_version_id' => 1, 'notes' => 'First step', 'created_at' => '2025-01-05 00:00:00', 'updated_at' => '2025-01-05 00:00:00'],
            ['id' => 2, 'story_id' => 1, 'sort_order' => 1, 'prompt_id' => 1, 'prompt_version_id' => 2, 'notes' => 'Second step', 'created_at' => '2025-01-05 00:00:00', 'updated_at' => '2025-01-05 00:00:00'],
        ]);

        // API Keys + Pivot
        $db->table('api_keys')->insert([
            ['id' => 1, 'user_id' => 1, 'name' => 'Test Key', 'key_hash' => hash('sha256', 'urge_testkey123'), 'key_preview' => 'urge_tes...', 'created_at' => '2025-01-06 00:00:00', 'updated_at' => '2025-01-06 00:00:00'],
        ]);

        $db->table('api_key_prompt')->insert([
            ['api_key_id' => 1, 'prompt_id' => 1],
        ]);

        DB::purge('v1_seed');
    }

    public function test_import_fails_with_nonexistent_file(): void
    {
        $this->artisan('urge:import-v1', ['path' => '/nonexistent/db.sqlite'])
            ->expectsOutput('Database file not found: /nonexistent/db.sqlite')
            ->assertExitCode(1);
    }

    public function test_import_users(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'admin@test.com', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'editor@test.com', 'role' => 'editor']);
    }

    public function test_import_categories(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $this->assertDatabaseHas('categories', ['slug' => 'writing', 'color' => 'blue']);
    }

    public function test_import_prompts_with_category(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $prompt = Prompt::where('slug', 'test-prompt')->first();
        $this->assertNotNull($prompt);
        $this->assertEquals('A test prompt', $prompt->description);
        $this->assertEquals('prompt', $prompt->type);
        $this->assertNotNull($prompt->category_id);
        $this->assertEquals(['test', 'demo'], $prompt->tags);
    }

    public function test_import_prompt_versions(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $prompt = Prompt::where('slug', 'test-prompt')->first();
        $versions = PromptVersion::where('prompt_id', $prompt->id)->orderBy('version_number')->get();

        $this->assertCount(2, $versions);
        $this->assertEquals('Hello {{ name }}', $versions[0]->content);
        $this->assertEquals('Hello {{ name }}, welcome!', $versions[1]->content);
        $this->assertEquals('Initial version', $versions[0]->commit_message);
    }

    public function test_import_links_active_version_to_pinned(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $prompt = Prompt::where('slug', 'test-prompt')->first();
        $this->assertNotNull($prompt->pinned_version_id);

        $pinnedVersion = PromptVersion::find($prompt->pinned_version_id);
        $this->assertEquals(2, $pinnedVersion->version_number);
    }

    public function test_import_llm_providers_with_decrypted_key(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $openai = LlmProvider::where('driver', 'openai')->where('model', 'gpt-4')->first();
        $this->assertNotNull($openai);
        $this->assertEquals('sk-test-key-12345', $openai->api_key);

        $ollama = LlmProvider::where('driver', 'ollama')->first();
        $this->assertNotNull($ollama);
        $this->assertEquals('http://localhost:11434', $ollama->endpoint);
    }

    public function test_import_runs_as_results(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $result = Result::where('source', 'api')->first();
        $this->assertNotNull($result);
        $this->assertEquals('Hello! Nice to meet you, World.', $result->response_text);
        $this->assertEquals('gpt-4', $result->model_name);
        $this->assertEquals(10, $result->input_tokens);
        $this->assertEquals(15, $result->output_tokens);
        $this->assertEquals(1200, $result->duration_ms);
        $this->assertEquals(4, $result->rating);
        $this->assertEquals('Hello World, welcome!', $result->rendered_content);
    }

    public function test_import_library_entries_as_starred_results(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $result = Result::where('source', 'manual')->where('starred', true)->first();
        $this->assertNotNull($result);
        $this->assertEquals('A saved library entry.', $result->response_text);
        $this->assertEquals('Great response', $result->notes);
        $this->assertEquals(5, $result->rating);
    }

    public function test_import_stories_as_collections(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $collection = Collection::where('title', 'My Story')->first();
        $this->assertNotNull($collection);
        $this->assertEquals('A test story', $collection->description);

        $items = CollectionItem::where('collection_id', $collection->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $items);
        $this->assertEquals('prompt_version', $items[0]->item_type);
        $this->assertEquals('First step', $items[0]->notes);
    }

    public function test_import_api_keys_with_pivot(): void
    {
        $this->seedV1Data();

        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $apiKey = ApiKey::where('key_hash', hash('sha256', 'urge_testkey123'))->first();
        $this->assertNotNull($apiKey);
        $this->assertEquals('Test Key', $apiKey->name);

        $prompt = Prompt::where('slug', 'test-prompt')->first();
        $this->assertTrue($apiKey->prompts->contains($prompt));
    }

    public function test_import_is_idempotent(): void
    {
        $this->seedV1Data();

        // Run twice
        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);
        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        // Should not duplicate data
        $this->assertEquals(2, User::count());
        $this->assertEquals(1, Prompt::count());
        $this->assertEquals(2, PromptVersion::count());
        $this->assertEquals(1, Category::count());
    }

    public function test_import_with_empty_v1_database(): void
    {
        // Don't seed - empty tables
        $this->artisan('urge:import-v1', ['path' => $this->v1DbPath])
            ->assertExitCode(0);

        $this->assertEquals(0, Prompt::count());
        $this->assertEquals(0, Result::count());
    }
}
