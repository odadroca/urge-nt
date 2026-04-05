<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $service = app(ApiKeyService::class);
        $result = $service->generateKey($this->user, 'Test Key');
        $this->apiKey = $result['key'];
    }

    public function test_valid_token_authenticates(): void
    {
        $response = $this->getJson('/api/v1/prompts', [
            'Authorization' => "Bearer {$this->apiKey}",
        ]);

        $response->assertStatus(200);
    }

    public function test_missing_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/prompts');

        $response->assertStatus(401)
            ->assertJson(['error' => 'Missing API key. Provide a Bearer token in the Authorization header.']);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/prompts', [
            'Authorization' => 'Bearer urge_invalid',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid or expired API key.']);
    }

    public function test_expired_key_returns_401(): void
    {
        $service = app(ApiKeyService::class);
        $result = $service->generateKey($this->user, 'Expired Key');
        $result['model']->update(['expires_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/prompts', [
            'Authorization' => "Bearer {$result['key']}",
        ]);

        $response->assertStatus(401);
    }

    public function test_rate_limiting_returns_429(): void
    {
        config(['urge.api_rate_limit' => 2, 'urge.api_rate_window' => 60]);
        Cache::flush();

        $headers = ['Authorization' => "Bearer {$this->apiKey}"];

        $this->getJson('/api/v1/prompts', $headers)->assertStatus(200);
        $this->getJson('/api/v1/prompts', $headers)->assertStatus(200);
        $this->getJson('/api/v1/prompts', $headers)->assertStatus(429);
    }

    public function test_last_used_at_is_updated(): void
    {
        $this->getJson('/api/v1/prompts', [
            'Authorization' => "Bearer {$this->apiKey}",
        ]);

        $service = app(ApiKeyService::class);
        $key = $service->findByToken($this->apiKey);
        $this->assertNotNull($key->last_used_at);
    }
}
