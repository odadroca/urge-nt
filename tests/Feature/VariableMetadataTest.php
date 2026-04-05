<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariableMetadataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_version_created_with_variable_metadata(): void
    {
        $prompt = Prompt::create([
            'name' => 'Metadata Test',
            'created_by' => $this->user->id,
        ]);

        $vs = app(VersioningService::class);
        $version = $vs->createVersion($prompt, [
            'content' => 'Hello {{name}}, welcome to {{city}}',
            'variable_metadata' => [
                'name' => [
                    'type' => 'string',
                    'default' => 'World',
                    'description' => 'User name',
                ],
                'city' => [
                    'type' => 'enum',
                    'default' => 'NYC',
                    'description' => 'Target city',
                    'options' => ['NYC', 'London', 'Tokyo'],
                ],
            ],
        ], $this->user);

        $this->assertNotNull($version);
        $this->assertEquals(['name', 'city'], $version->variables);

        $meta = $version->variable_metadata;
        $this->assertNotNull($meta);
        $this->assertEquals('string', $meta['name']['type']);
        $this->assertEquals('World', $meta['name']['default']);
        $this->assertEquals('User name', $meta['name']['description']);

        $this->assertEquals('enum', $meta['city']['type']);
        $this->assertEquals('NYC', $meta['city']['default']);
        $this->assertEquals(['NYC', 'London', 'Tokyo'], $meta['city']['options']);
    }

    public function test_metadata_only_includes_variables_in_content(): void
    {
        $prompt = Prompt::create([
            'name' => 'Filter Test',
            'created_by' => $this->user->id,
        ]);

        $vs = app(VersioningService::class);
        $version = $vs->createVersion($prompt, [
            'content' => 'Hello {{name}}',
            'variable_metadata' => [
                'name' => ['type' => 'string', 'default' => 'World'],
                'nonexistent' => ['type' => 'string', 'default' => 'should be filtered'],
            ],
        ], $this->user);

        $this->assertArrayHasKey('name', $version->variable_metadata);
        $this->assertArrayNotHasKey('nonexistent', $version->variable_metadata);
    }

    public function test_metadata_round_trip_preserves_data(): void
    {
        $prompt = Prompt::create([
            'name' => 'Round Trip',
            'created_by' => $this->user->id,
        ]);

        $metadata = [
            'name' => [
                'type' => 'enum',
                'default' => 'Alice',
                'description' => 'The user name',
                'options' => ['Alice', 'Bob', 'Charlie'],
            ],
        ];

        $vs = app(VersioningService::class);
        $version = $vs->createVersion($prompt, [
            'content' => 'Hello {{name}}',
            'variable_metadata' => $metadata,
        ], $this->user);

        // Re-fetch from DB
        $version = PromptVersion::find($version->id);
        $meta = $version->variable_metadata;

        $this->assertEquals('enum', $meta['name']['type']);
        $this->assertEquals('Alice', $meta['name']['default']);
        $this->assertEquals('The user name', $meta['name']['description']);
        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $meta['name']['options']);
    }

    public function test_version_without_metadata_stores_null(): void
    {
        $prompt = Prompt::create([
            'name' => 'No Meta',
            'created_by' => $this->user->id,
        ]);

        $vs = app(VersioningService::class);
        $version = $vs->createVersion($prompt, [
            'content' => 'Hello {{name}}',
        ], $this->user);

        $this->assertNull($version->variable_metadata);
    }

    public function test_editor_options_csv_processing(): void
    {
        // Simulate the Editor component's options_csv to options conversion
        $metadata = [
            'tone' => [
                'type' => 'enum',
                'default' => 'formal',
                'description' => 'Response tone',
                'options_csv' => 'formal, casual, technical',
            ],
        ];

        // Process like Editor.php does
        foreach ($metadata as $varName => &$meta) {
            if (!empty($meta['options_csv'])) {
                $meta['options'] = array_values(array_filter(
                    array_map('trim', explode(',', $meta['options_csv']))
                ));
            }
            unset($meta['options_csv']);
        }
        unset($meta);

        $prompt = Prompt::create([
            'name' => 'CSV Test',
            'created_by' => $this->user->id,
        ]);

        $vs = app(VersioningService::class);
        $version = $vs->createVersion($prompt, [
            'content' => 'Hello {{tone}}',
            'variable_metadata' => $metadata,
        ], $this->user);

        $meta = $version->variable_metadata;
        $this->assertEquals(['formal', 'casual', 'technical'], $meta['tone']['options']);
        $this->assertArrayNotHasKey('options_csv', $meta['tone']);
    }
}
