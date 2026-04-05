<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\User;
use App\Services\TemplateEngine;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateEngineTest extends TestCase
{
    use RefreshDatabase;

    private TemplateEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new TemplateEngine();
    }

    public function test_extract_variables(): void
    {
        $vars = $this->engine->extractVariables('Hello {{name}}, you are {{age}} years old.');
        $this->assertEquals(['name', 'age'], $vars);
    }

    public function test_extract_includes(): void
    {
        $includes = $this->engine->extractIncludes('Start {{>header}} middle {{>footer}}');
        $this->assertEquals(['header', 'footer'], $includes);
    }

    public function test_render_with_variables(): void
    {
        $result = $this->engine->render('Hello {{name}}!', ['name' => 'World']);
        $this->assertEquals('Hello World!', $result['rendered']);
        $this->assertEquals(['name'], $result['variables_used']);
        $this->assertEmpty($result['variables_missing']);
    }

    public function test_render_with_missing_variables(): void
    {
        $result = $this->engine->render('Hello {{name}} {{age}}!', ['name' => 'World']);
        $this->assertEquals('Hello World {{age}}!', $result['rendered']);
        $this->assertEquals(['age'], $result['variables_missing']);
    }

    public function test_render_with_includes(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $fragment = Prompt::create([
            'name' => 'System Context',
            'type' => 'fragment',
            'created_by' => $user->id,
        ]);

        app(VersioningService::class)->createVersion($fragment, [
            'content' => 'You are a helpful assistant.',
        ], $user);

        $result = $this->engine->render('{{>system-context}} Now help with {{topic}}.', ['topic' => 'math']);
        $this->assertEquals('You are a helpful assistant. Now help with math.', $result['rendered']);
        $this->assertContains('system-context', $result['includes_resolved']);
    }

    public function test_circular_include_detection(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $a = Prompt::create(['name' => 'A', 'created_by' => $user->id]);
        $b = Prompt::create(['name' => 'B', 'created_by' => $user->id]);

        $vs = app(VersioningService::class);
        $vs->createVersion($a, ['content' => '{{>b}}'], $user);
        $vs->createVersion($b, ['content' => '{{>a}}'], $user);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular include');
        $this->engine->render('{{>a}}', []);
    }
}
