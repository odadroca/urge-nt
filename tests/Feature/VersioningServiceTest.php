<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private VersioningService $service;
    private User $user;
    private Prompt $prompt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VersioningService::class);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Test Prompt',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_creates_first_version_as_number_one(): void
    {
        $version = $this->service->createVersion($this->prompt, [
            'content' => 'Hello {{name}}!',
        ], $this->user);

        $this->assertEquals(1, $version->version_number);
        $this->assertEquals('Hello {{name}}!', $version->content);
        $this->assertEquals(['name'], $version->variables);
    }

    public function test_auto_increments_version_number(): void
    {
        $this->service->createVersion($this->prompt, ['content' => 'v1'], $this->user);
        $v2 = $this->service->createVersion($this->prompt, ['content' => 'v2'], $this->user);
        $v3 = $this->service->createVersion($this->prompt, ['content' => 'v3'], $this->user);

        $this->assertEquals(2, $v2->version_number);
        $this->assertEquals(3, $v3->version_number);
    }

    public function test_extracts_variables_and_includes(): void
    {
        $version = $this->service->createVersion($this->prompt, [
            'content' => '{{>header}} Hello {{name}}, welcome to {{place}}.',
        ], $this->user);

        $this->assertEquals(['name', 'place'], $version->variables);
        $this->assertEquals(['header'], $version->includes);
    }

    public function test_versions_are_immutable(): void
    {
        $version = $this->service->createVersion($this->prompt, [
            'content' => 'Original content',
        ], $this->user);

        $this->expectException(\LogicException::class);
        $version->update(['content' => 'Modified content']);
    }

    public function test_stores_commit_message(): void
    {
        $version = $this->service->createVersion($this->prompt, [
            'content' => 'Some content',
            'commit_message' => 'Initial version',
        ], $this->user);

        $this->assertEquals('Initial version', $version->commit_message);
    }

    public function test_filters_variable_metadata(): void
    {
        $version = $this->service->createVersion($this->prompt, [
            'content' => 'Hello {{name}}!',
            'variable_metadata' => [
                'name' => ['type' => 'string', 'default' => 'World'],
                'unused' => ['type' => 'string'],
            ],
        ], $this->user);

        $this->assertArrayHasKey('name', $version->variable_metadata);
        $this->assertArrayNotHasKey('unused', $version->variable_metadata);
    }
}
