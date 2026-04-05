<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Prompt;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ApiKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->service = app(ApiKeyService::class);
    }

    public function test_generate_key_returns_key_and_model(): void
    {
        $result = $this->service->generateKey($this->user, 'Test Key');

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertInstanceOf(ApiKey::class, $result['model']);
        $this->assertStringStartsWith('urge_', $result['key']);
    }

    public function test_generated_key_is_stored_as_hash(): void
    {
        $result = $this->service->generateKey($this->user, 'Test Key');

        $apiKey = $result['model'];
        $this->assertNotEquals($result['key'], $apiKey->key_hash);
        $this->assertEquals(hash('sha256', $result['key']), $apiKey->key_hash);
    }

    public function test_key_preview_is_stored(): void
    {
        $result = $this->service->generateKey($this->user, 'Test Key');

        $apiKey = $result['model'];
        $this->assertEquals(substr($result['key'], 0, 8), $apiKey->key_preview);
    }

    public function test_find_by_token_returns_key(): void
    {
        $result = $this->service->generateKey($this->user, 'Test Key');

        $found = $this->service->findByToken($result['key']);
        $this->assertNotNull($found);
        $this->assertEquals($result['model']->id, $found->id);
    }

    public function test_find_by_token_returns_null_for_invalid_token(): void
    {
        $found = $this->service->findByToken('urge_invalid_token');
        $this->assertNull($found);
    }

    public function test_find_by_token_returns_null_for_inactive_key(): void
    {
        $result = $this->service->generateKey($this->user, 'Test Key');
        $result['model']->update(['is_active' => false]);

        $found = $this->service->findByToken($result['key']);
        $this->assertNull($found);
    }

    public function test_find_by_token_returns_null_for_expired_key(): void
    {
        $result = $this->service->generateKey($this->user, 'Test Key');
        $result['model']->update(['expires_at' => now()->subDay()]);

        $found = $this->service->findByToken($result['key']);
        $this->assertNull($found);
    }

    public function test_generate_key_with_prompt_scoping(): void
    {
        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'type' => 'prompt',
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->generateKey($this->user, 'Scoped Key', [$prompt->id]);

        $this->assertEquals(1, $result['model']->prompts()->count());
        $this->assertEquals($prompt->id, $result['model']->prompts()->first()->id);
    }
}
