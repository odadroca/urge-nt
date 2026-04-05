<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
use App\Services\ImportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ImportExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->service = app(ImportExportService::class);
    }

    public function test_export_prompt_version_produces_valid_markdown(): void
    {
        $prompt = Prompt::create([
            'name' => 'Export Test',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello {{name}}, welcome!',
            'variables' => ['name'],
            'commit_message' => 'Initial version',
            'created_by' => $this->user->id,
        ]);

        $md = $this->service->exportPromptVersion($version);

        $this->assertStringContainsString('---', $md);
        $this->assertStringContainsString('prompt: export-test', $md);
        $this->assertStringContainsString('version: 1', $md);
        $this->assertStringContainsString('variables: name', $md);
        $this->assertStringContainsString('commit_message: Initial version', $md);
        $this->assertStringContainsString('Hello {{name}}, welcome!', $md);
    }

    public function test_export_result_produces_valid_markdown(): void
    {
        $prompt = Prompt::create([
            'name' => 'Result Export',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $result = Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'manual',
            'provider_name' => 'GPT-4',
            'model_name' => 'gpt-4-turbo',
            'response_text' => 'This is the response text.',
            'rating' => 4,
            'starred' => true,
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        $md = $this->service->exportResult($result);

        $this->assertStringContainsString('---', $md);
        $this->assertStringContainsString('prompt: result-export', $md);
        $this->assertStringContainsString('provider: GPT-4', $md);
        $this->assertStringContainsString('model: gpt-4-turbo', $md);
        $this->assertStringContainsString('rating: 4', $md);
        $this->assertStringContainsString('starred: true', $md);
        $this->assertStringContainsString('This is the response text.', $md);
    }

    public function test_parse_frontmatter_round_trip(): void
    {
        $prompt = Prompt::create([
            'name' => 'Round Trip',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Test content',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $result = Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'manual',
            'provider_name' => 'Claude',
            'response_text' => 'Round trip response.',
            'rating' => 5,
            'starred' => false,
            'status' => 'success',
            'created_by' => $this->user->id,
        ]);

        $exported = $this->service->exportResult($result);
        $parsed = $this->service->parseMarkdownWithFrontmatter($exported);

        $this->assertEquals('Claude', $parsed['meta']['provider']);
        $this->assertEquals('5', $parsed['meta']['rating']);
        $this->assertEquals('false', $parsed['meta']['starred']);
        $this->assertStringContainsString('Round trip response.', $parsed['body']);
    }

    public function test_import_result_creates_record(): void
    {
        $prompt = Prompt::create([
            'name' => 'Import Target',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $markdown = "---\nprovider: GPT-4\nmodel: gpt-4\nrating: 3\nstarred: true\n---\n\nImported response content.";

        $result = $this->service->importResult($markdown, $version, $this->user, 'test-import.md');

        $this->assertNotNull($result->id);
        $this->assertEquals('import', $result->source);
        $this->assertEquals('GPT-4', $result->provider_name);
        $this->assertEquals('gpt-4', $result->model_name);
        $this->assertEquals(3, $result->rating);
        $this->assertTrue($result->starred);
        $this->assertEquals('test-import.md', $result->import_filename);
        $this->assertStringContainsString('Imported response content.', $result->response_text);
        $this->assertEquals($version->id, $result->prompt_version_id);
    }

    public function test_import_with_missing_fields_handles_gracefully(): void
    {
        $prompt = Prompt::create([
            'name' => 'Graceful Import',
            'created_by' => $this->user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'content' => 'Hello',
            'variables' => [],
            'created_by' => $this->user->id,
        ]);

        $markdown = "---\nprovider: Unknown\n---\n\nSimple body.";

        $result = $this->service->importResult($markdown, $version, $this->user);

        $this->assertEquals('import', $result->source);
        $this->assertEquals('Unknown', $result->provider_name);
        $this->assertNull($result->model_name);
        $this->assertNull($result->rating);
        $this->assertFalse($result->starred);
        $this->assertStringContainsString('Simple body.', $result->response_text);
    }

    public function test_parse_content_without_frontmatter(): void
    {
        $parsed = $this->service->parseMarkdownWithFrontmatter('Just plain text content.');

        $this->assertEmpty($parsed['meta']);
        $this->assertEquals('Just plain text content.', $parsed['body']);
    }
}
