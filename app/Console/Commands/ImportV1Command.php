<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportV1Command extends Command
{
    protected $signature = 'urge:import-v1 {path : Path to v1 SQLite database file}';
    protected $description = 'Import data from a URGE v1 SQLite database';

    private array $userMap = [];
    private array $categoryMap = [];
    private array $promptMap = [];
    private array $versionMap = [];
    private array $providerMap = [];
    private array $apiKeyMap = [];
    private array $libraryEntryMap = [];
    private int $created = 0;
    private int $skipped = 0;

    public function handle(): int
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("Database file not found: {$path}");
            return self::FAILURE;
        }

        config(['database.connections.v1' => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        try {
            DB::connection('v1')->getPdo();
        } catch (\Exception $e) {
            $this->error("Could not connect to v1 database: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info('Starting URGE v1 import...');

        try {
            DB::transaction(function () {
                $this->importUsers();
                $this->importCategories();
                $this->importPrompts();
                $this->importPromptVersions();
                $this->linkActiveVersions();
                $this->importLlmProviders();
                $this->importRunsAndResponses();
                $this->importLibraryEntries();
                $this->importStories();
                $this->importApiKeys();
            });
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Import complete! Created: {$this->created}, Skipped (existing): {$this->skipped}");

        return self::SUCCESS;
    }

    private function importUsers(): void
    {
        $this->info('Importing users...');
        $rows = DB::connection('v1')->table('users')->get();

        foreach ($rows as $row) {
            $user = User::firstOrCreate(
                ['email' => $row->email],
                [
                    'name' => $row->name,
                    'password' => $row->password,
                    'role' => $row->role ?? 'viewer',
                ]
            );

            $this->userMap[$row->id] = $user->id;

            if ($user->wasRecentlyCreated) {
                $this->created++;
            } else {
                $this->skipped++;
            }
        }

        $this->line("  Users: {$rows->count()} processed");
    }

    private function importCategories(): void
    {
        $this->info('Importing categories...');

        if (!$this->v1TableExists('categories')) {
            $this->line('  Categories table not found, skipping');
            return;
        }

        $rows = DB::connection('v1')->table('categories')->get();

        foreach ($rows as $row) {
            $category = Category::firstOrCreate(
                ['slug' => $row->slug],
                [
                    'name' => $row->name,
                    'color' => $row->color ?? 'gray',
                ]
            );

            $this->categoryMap[$row->id] = $category->id;

            if ($category->wasRecentlyCreated) {
                $this->created++;
            } else {
                $this->skipped++;
            }
        }

        $this->line("  Categories: {$rows->count()} processed");
    }

    private function importPrompts(): void
    {
        $this->info('Importing prompts...');
        $rows = DB::connection('v1')->table('prompts')->get();

        foreach ($rows as $row) {
            $prompt = Prompt::withTrashed()->firstOrCreate(
                ['slug' => $row->slug],
                [
                    'name' => $row->name,
                    'description' => $row->description,
                    'type' => 'prompt',
                    'category_id' => isset($row->category_id) ? ($this->categoryMap[$row->category_id] ?? null) : null,
                    'tags' => isset($row->tags) ? json_decode($row->tags, true) : null,
                    'created_by' => $this->userMap[$row->created_by] ?? null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]
            );

            if ($row->deleted_at && !$prompt->trashed()) {
                $prompt->delete();
            }

            $this->promptMap[$row->id] = $prompt->id;

            if ($prompt->wasRecentlyCreated) {
                $this->created++;
            } else {
                $this->skipped++;
            }
        }

        $this->line("  Prompts: {$rows->count()} processed");
    }

    private function importPromptVersions(): void
    {
        $this->info('Importing prompt versions...');
        $rows = DB::connection('v1')->table('prompt_versions')->get();

        foreach ($rows as $row) {
            $promptId = $this->promptMap[$row->prompt_id] ?? null;
            if (!$promptId) {
                continue;
            }

            $existing = PromptVersion::where('prompt_id', $promptId)
                ->where('version_number', $row->version_number)
                ->first();

            if ($existing) {
                $this->versionMap[$row->id] = $existing->id;
                $this->skipped++;
                continue;
            }

            $version = PromptVersion::create([
                'prompt_id' => $promptId,
                'version_number' => $row->version_number,
                'content' => $row->content,
                'commit_message' => $row->commit_message,
                'variables' => isset($row->variables) ? json_decode($row->variables, true) : null,
                'variable_metadata' => isset($row->variable_metadata) ? json_decode($row->variable_metadata, true) : null,
                'includes' => isset($row->includes) ? json_decode($row->includes, true) : null,
                'created_by' => $this->userMap[$row->created_by] ?? null,
                'created_at' => $row->created_at,
            ]);

            $this->versionMap[$row->id] = $version->id;
            $this->created++;
        }

        $this->line("  Prompt versions: {$rows->count()} processed");
    }

    private function linkActiveVersions(): void
    {
        $this->info('Linking pinned versions...');
        $rows = DB::connection('v1')->table('prompts')
            ->whereNotNull('active_version_id')
            ->get(['id', 'active_version_id']);

        $linked = 0;
        foreach ($rows as $row) {
            $promptId = $this->promptMap[$row->id] ?? null;
            $versionId = $this->versionMap[$row->active_version_id] ?? null;

            if ($promptId && $versionId) {
                Prompt::withTrashed()->where('id', $promptId)
                    ->update(['pinned_version_id' => $versionId]);
                $linked++;
            }
        }

        $this->line("  Pinned versions linked: {$linked}");
    }

    private function importLlmProviders(): void
    {
        $this->info('Importing LLM providers...');
        $rows = DB::connection('v1')->table('llm_providers')->get();

        foreach ($rows as $row) {
            $existing = LlmProvider::where('driver', $row->driver)
                ->where('model', $row->model)
                ->first();

            if ($existing) {
                $this->providerMap[$row->id] = $existing->id;
                $this->skipped++;
                continue;
            }

            // Try to decrypt v1 API key
            $apiKey = null;
            if (!empty($row->api_key_encrypted)) {
                try {
                    $apiKey = Crypt::decryptString($row->api_key_encrypted);
                } catch (\Exception $e) {
                    $this->warn("  Could not decrypt API key for {$row->name} (different APP_KEY?). Skipping key.");
                }
            }

            $provider = LlmProvider::create([
                'name' => $row->name,
                'driver' => $row->driver,
                'api_key' => $apiKey,
                'model' => $row->model,
                'endpoint' => $row->base_url ?? null,
                'is_active' => (bool) ($row->enabled ?? false),
            ]);

            $this->providerMap[$row->id] = $provider->id;
            $this->created++;
        }

        $this->line("  LLM providers: {$rows->count()} processed");
    }

    private function importRunsAndResponses(): void
    {
        $this->info('Importing prompt runs + responses as results...');

        if (!$this->v1TableExists('prompt_runs')) {
            $this->line('  Prompt runs table not found, skipping');
            return;
        }

        $runs = DB::connection('v1')->table('prompt_runs')->get();
        $count = 0;

        foreach ($runs as $run) {
            $promptId = $this->promptMap[$run->prompt_id] ?? null;
            $versionId = $this->versionMap[$run->prompt_version_id] ?? null;

            if (!$promptId || !$versionId) {
                continue;
            }

            $responses = DB::connection('v1')->table('llm_responses')
                ->where('prompt_run_id', $run->id)
                ->get();

            foreach ($responses as $resp) {
                // Deduplicate by checking existing results
                $exists = Result::where('prompt_id', $promptId)
                    ->where('prompt_version_id', $versionId)
                    ->where('source', 'api')
                    ->where('response_text', $resp->response_text)
                    ->where('created_at', $resp->created_at)
                    ->exists();

                if ($exists) {
                    $this->skipped++;
                    continue;
                }

                $providerId = $this->providerMap[$resp->llm_provider_id] ?? null;
                $provider = $providerId ? LlmProvider::find($providerId) : null;

                Result::create([
                    'prompt_id' => $promptId,
                    'prompt_version_id' => $versionId,
                    'source' => 'api',
                    'provider_name' => $provider->name ?? null,
                    'model_name' => $resp->model_used,
                    'llm_provider_id' => $providerId,
                    'rendered_content' => $run->rendered_content,
                    'variables_used' => isset($run->variables_used) ? json_decode($run->variables_used, true) : null,
                    'response_text' => $resp->response_text,
                    'rating' => $resp->rating,
                    'input_tokens' => $resp->input_tokens,
                    'output_tokens' => $resp->output_tokens,
                    'duration_ms' => $resp->duration_ms,
                    'status' => $resp->status ?? 'success',
                    'error_message' => $resp->error_message,
                    'created_by' => $this->userMap[$run->created_by] ?? null,
                    'created_at' => $resp->created_at,
                    'updated_at' => $resp->created_at,
                ]);

                $this->created++;
                $count++;
            }
        }

        $this->line("  Results from runs: {$count} created");
    }

    private function importLibraryEntries(): void
    {
        $this->info('Importing library entries as starred results...');

        if (!$this->v1TableExists('library_entries')) {
            $this->line('  Library entries table not found, skipping');
            return;
        }

        $rows = DB::connection('v1')->table('library_entries')->get();
        $count = 0;

        foreach ($rows as $row) {
            $promptId = $this->promptMap[$row->prompt_id] ?? null;
            $versionId = $this->versionMap[$row->prompt_version_id] ?? null;

            if (!$promptId || !$versionId) {
                continue;
            }

            // Deduplicate: check if this response_text already exists as a result
            $exists = Result::where('prompt_id', $promptId)
                ->where('prompt_version_id', $versionId)
                ->where('response_text', $row->response_text)
                ->exists();

            if ($exists) {
                // Star the existing result instead
                Result::where('prompt_id', $promptId)
                    ->where('prompt_version_id', $versionId)
                    ->where('response_text', $row->response_text)
                    ->update(['starred' => true, 'notes' => $row->notes]);
                $this->skipped++;
                continue;
            }

            $providerId = isset($row->llm_provider_id) ? ($this->providerMap[$row->llm_provider_id] ?? null) : null;
            $provider = $providerId ? LlmProvider::find($providerId) : null;

            Result::create([
                'prompt_id' => $promptId,
                'prompt_version_id' => $versionId,
                'source' => 'manual',
                'provider_name' => $provider->name ?? null,
                'model_name' => $row->model_used,
                'llm_provider_id' => $providerId,
                'response_text' => $row->response_text,
                'notes' => $row->notes,
                'rating' => $row->rating,
                'starred' => true,
                'created_by' => $this->userMap[$row->created_by] ?? null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $this->created++;
            $count++;
        }

        $this->line("  Library entries: {$count} created");
    }

    private function importStories(): void
    {
        $this->info('Importing stories as collections...');

        if (!$this->v1TableExists('stories')) {
            $this->line('  Stories table not found, skipping');
            return;
        }

        $rows = DB::connection('v1')->table('stories')->get();
        $storyMap = [];

        foreach ($rows as $row) {
            $slug = Str::slug($row->title);
            $collection = Collection::withTrashed()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $row->title,
                    'description' => $row->description,
                    'created_by' => $this->userMap[$row->created_by] ?? null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]
            );

            $storyMap[$row->id] = $collection->id;

            if ($collection->wasRecentlyCreated) {
                $this->created++;
            } else {
                $this->skipped++;
            }
        }

        // Import story steps as collection items
        if ($this->v1TableExists('story_steps')) {
            $steps = DB::connection('v1')->table('story_steps')->orderBy('sort_order')->get();
            $stepCount = 0;

            foreach ($steps as $step) {
                $collectionId = $storyMap[$step->story_id] ?? null;
                $versionId = $this->versionMap[$step->prompt_version_id] ?? null;

                if (!$collectionId || !$versionId) {
                    continue;
                }

                $item = CollectionItem::firstOrCreate([
                    'collection_id' => $collectionId,
                    'item_type' => 'prompt_version',
                    'item_id' => $versionId,
                ], [
                    'sort_order' => $step->sort_order,
                    'notes' => $step->notes,
                ]);

                if ($item->wasRecentlyCreated) {
                    $this->created++;
                    $stepCount++;
                } else {
                    $this->skipped++;
                }
            }

            $this->line("  Story steps as collection items: {$stepCount} created");
        }

        $this->line("  Stories as collections: {$rows->count()} processed");
    }

    private function importApiKeys(): void
    {
        $this->info('Importing API keys...');
        $rows = DB::connection('v1')->table('api_keys')->get();

        foreach ($rows as $row) {
            $userId = $this->userMap[$row->user_id] ?? null;
            if (!$userId) {
                continue;
            }

            $apiKey = ApiKey::firstOrCreate(
                ['key_hash' => $row->key_hash],
                [
                    'name' => $row->name,
                    'user_id' => $userId,
                    'key_preview' => $row->key_preview,
                    'last_used_at' => $row->last_used_at,
                    'expires_at' => $row->expires_at,
                    'is_active' => true,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]
            );

            $this->apiKeyMap[$row->id] = $apiKey->id;

            if ($apiKey->wasRecentlyCreated) {
                $this->created++;
            } else {
                $this->skipped++;
            }
        }

        // Import pivot table
        if ($this->v1TableExists('api_key_prompt')) {
            $pivots = DB::connection('v1')->table('api_key_prompt')->get();
            foreach ($pivots as $pivot) {
                $apiKeyId = $this->apiKeyMap[$pivot->api_key_id] ?? null;
                $promptId = $this->promptMap[$pivot->prompt_id] ?? null;

                if ($apiKeyId && $promptId) {
                    DB::table('api_key_prompt')->insertOrIgnore([
                        'api_key_id' => $apiKeyId,
                        'prompt_id' => $promptId,
                    ]);
                }
            }
        }

        $this->line("  API keys: {$rows->count()} processed");
    }

    private function v1TableExists(string $table): bool
    {
        try {
            DB::connection('v1')->table($table)->limit(1)->get();
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
