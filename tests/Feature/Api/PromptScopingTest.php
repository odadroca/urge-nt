<?php

namespace Tests\Feature\Api;

use App\Models\Prompt;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_scoped_key_can_access_allowed_prompt(): void
    {
        $allowed = Prompt::create(['name' => 'Allowed', 'type' => 'prompt', 'created_by' => $this->user->id]);
        Prompt::create(['name' => 'Denied', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $result = app(ApiKeyService::class)->generateKey($this->user, 'Scoped', [$allowed->id]);
        $headers = ['Authorization' => "Bearer {$result['key']}"];

        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$allowed->slug}", $headers);
        $response->assertStatus(200);
    }

    public function test_scoped_key_cannot_access_disallowed_prompt(): void
    {
        $allowed = Prompt::create(['name' => 'Allowed', 'type' => 'prompt', 'created_by' => $this->user->id]);
        $denied = Prompt::create(['name' => 'Denied', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $result = app(ApiKeyService::class)->generateKey($this->user, 'Scoped', [$allowed->id]);
        $headers = ['Authorization' => "Bearer {$result['key']}"];

        $response = $this->getJson("/api/v1/prompts/{$this->user->slug}/{$denied->slug}", $headers);
        $response->assertStatus(403);
    }

    public function test_scoped_key_list_only_shows_allowed(): void
    {
        $allowed = Prompt::create(['name' => 'Allowed', 'type' => 'prompt', 'created_by' => $this->user->id]);
        Prompt::create(['name' => 'Denied', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $result = app(ApiKeyService::class)->generateKey($this->user, 'Scoped', [$allowed->id]);
        $headers = ['Authorization' => "Bearer {$result['key']}"];

        $response = $this->getJson('/api/v1/prompts', $headers);
        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Allowed');
    }

    public function test_unscoped_key_accesses_all_prompts(): void
    {
        Prompt::create(['name' => 'Prompt A', 'type' => 'prompt', 'created_by' => $this->user->id]);
        Prompt::create(['name' => 'Prompt B', 'type' => 'prompt', 'created_by' => $this->user->id]);

        $result = app(ApiKeyService::class)->generateKey($this->user, 'Unscoped');
        $headers = ['Authorization' => "Bearer {$result['key']}"];

        $response = $this->getJson('/api/v1/prompts', $headers);
        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }
}
