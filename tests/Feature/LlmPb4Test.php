<?php

namespace Tests\Feature;

use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\Result;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\LlmDispatchService;
use App\Services\UrlSafetyService;
use App\Services\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase B Sprint 4 regression tests — closes LLM-01..09 plus the
 * encrypted-at-rest piece of LLM-05. LLM-11 is exercised by the
 * vite.config.js production-build setting (not testable from PHP).
 */
class LlmPb4Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $alice;
    private array $aliceHeaders;
    private array $adminHeaders;

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
        $adminKey = app(ApiKeyService::class)->generateKey($this->admin, 'Admin Key');
        $aliceKey = app(ApiKeyService::class)->generateKey($this->alice, 'Alice Key');
        $this->adminHeaders = ['Authorization' => "Bearer {$adminKey['key']}"];
        $this->aliceHeaders = ['Authorization' => "Bearer {$aliceKey['key']}"];
    }

    // ---------- LLM-01: SSRF guard on provider endpoint ----------

    public function test_url_safety_blocks_loopback_for_public_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UrlSafetyService::assertSafe('http://127.0.0.1:8080/v1', ['allow_http' => true]);
    }

    public function test_url_safety_blocks_aws_metadata(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UrlSafetyService::assertSafe('http://169.254.169.254/latest/meta-data/', ['allow_http' => true]);
    }

    public function test_url_safety_blocks_rfc1918(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UrlSafetyService::assertSafe('https://10.0.0.5/v1');
    }

    public function test_url_safety_allows_public_https(): void
    {
        // No exception
        UrlSafetyService::assertSafe('https://api.openai.com/v1/chat/completions');
        $this->assertTrue(true);
    }

    public function test_provider_endpoint_rejected_on_create_if_private(): void
    {
        $response = $this->postJson('/api/v1/providers', [
            'name' => 'Bad',
            'driver' => 'openai',
            'model' => 'gpt-4',
            'api_key' => 'sk-test',
            'endpoint' => 'https://10.0.0.1/v1',
        ], $this->adminHeaders);

        $response->assertStatus(422);
    }

    public function test_dispatch_refuses_openai_provider_with_loopback_endpoint(): void
    {
        // Bypass the controller validation: insert directly with a
        // loopback endpoint, then attempt dispatch.
        $provider = LlmProvider::create([
            'name' => 'Sneaky',
            'driver' => 'openai',
            'api_key' => 'sk-test',
            'model' => 'gpt-4',
            'endpoint' => 'http://127.0.0.1:9999',
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(LlmDispatchService::class)->dispatch($provider, 'hi');
    }

    // ---------- LLM-08: Ollama requires explicit endpoint ----------

    public function test_provider_create_ollama_requires_endpoint(): void
    {
        $response = $this->postJson('/api/v1/providers', [
            'name' => 'Local',
            'driver' => 'ollama',
            'model' => 'llama3',
        ], $this->adminHeaders);

        $response->assertStatus(422);
    }

    public function test_dispatch_refuses_ollama_without_endpoint(): void
    {
        $provider = LlmProvider::create([
            'name' => 'Sneaky Ollama',
            'driver' => 'ollama',
            'model' => 'llama3',
            'endpoint' => null,
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('explicit endpoint');
        app(LlmDispatchService::class)->dispatch($provider, 'hi');
    }

    // ---------- LLM-02: Gemini key in header, not URL ----------

    public function test_gemini_driver_sends_key_in_header_not_url(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
                'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
            ], 200),
        ]);

        $provider = LlmProvider::create([
            'name' => 'Gemini',
            'driver' => 'gemini',
            'api_key' => 'AIzaSyTEST_FAKE_KEY_NOT_REAL',
            'model' => 'gemini-pro',
            'is_active' => true,
        ]);

        app(LlmDispatchService::class)->dispatch($provider, 'hi');

        Http::assertSent(function ($request) {
            // URL must NOT carry ?key=…
            $this->assertStringNotContainsString('key=', $request->url());
            $this->assertStringNotContainsString('AIza', $request->url());
            // Header must
            $this->assertEquals('AIzaSyTEST_FAKE_KEY_NOT_REAL', $request->header('x-goog-api-key')[0] ?? null);
            return true;
        });
    }

    // ---------- LLM-03 / LLM-04: error sanitization ----------

    public function test_driver_error_does_not_echo_upstream_body_verbatim(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('<html><body>This contains Bearer secret_token_xyz_should_be_redacted</body></html>', 502),
        ]);

        $provider = LlmProvider::create([
            'name' => 'OpenAI',
            'driver' => 'openai',
            'api_key' => 'sk-test',
            'model' => 'gpt-4',
            'is_active' => true,
        ]);

        $result = app(LlmDispatchService::class)->dispatch($provider, 'hi');

        $this->assertFalse($result->success);
        $this->assertStringNotContainsString('secret_token_xyz_should_be_redacted', $result->error ?? '');
    }

    public function test_driver_error_from_throwable_does_not_include_url(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => fn () => throw new \Exception('cURL error: tried https://api.openai.com/v1/x with bearer sk-leak123456')]);

        $provider = LlmProvider::create([
            'name' => 'OpenAI',
            'driver' => 'openai',
            'api_key' => 'sk-leak123456',
            'model' => 'gpt-4',
            'is_active' => true,
        ]);

        $result = app(LlmDispatchService::class)->dispatch($provider, 'hi');

        $this->assertFalse($result->success);
        $this->assertStringNotContainsString('sk-leak123456', $result->error ?? '');
        $this->assertStringNotContainsString('https://', $result->error ?? '');
    }

    // ---------- LLM-05: encrypted at rest ----------

    public function test_response_text_is_encrypted_at_rest(): void
    {
        $prompt = Prompt::create(['name' => 'P', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($prompt, ['content' => 'x'], $this->alice);
        $version = $prompt->versions()->first();

        $result = Result::create([
            'prompt_id' => $prompt->id,
            'prompt_version_id' => $version->id,
            'source' => 'api',
            'response_text' => 'PLAINTEXT_SECRET_SENTINEL',
            'created_by' => $this->alice->id,
        ]);

        // Raw DB row should not contain the plaintext
        $raw = DB::table('results')->where('id', $result->id)->first();
        $this->assertStringNotContainsString('PLAINTEXT_SECRET_SENTINEL', (string) $raw->response_text);

        // Model decrypts transparently
        $this->assertEquals('PLAINTEXT_SECRET_SENTINEL', $result->fresh()->response_text);

        // Hash column populated
        $this->assertEquals(hash('sha256', 'PLAINTEXT_SECRET_SENTINEL'), $raw->response_hash);

        // Confirm the ciphertext is decryptable (sanity check)
        $decoded = Crypt::decryptString($raw->response_text);
        $this->assertEquals('PLAINTEXT_SECRET_SENTINEL', $decoded);
    }

    // ---------- LLM-06: per-user throttle on LLM-dispatching routes ----------

    public function test_run_endpoint_is_throttled(): void
    {
        $prompt = Prompt::create(['name' => 'P', 'type' => 'prompt', 'created_by' => $this->alice->id]);
        app(VersioningService::class)->createVersion($prompt, ['content' => 'hi'], $this->alice);

        $last = null;
        for ($i = 0; $i < 31; $i++) {
            $last = $this->postJson(
                "/api/v1/prompts/{$this->alice->slug}/{$prompt->slug}/run",
                [],
                $this->aliceHeaders,
            );
        }
        $this->assertEquals(429, $last->status());
    }

    // ---------- LLM-07: pre-dispatch prompt-size cap ----------

    public function test_dispatch_rejects_oversized_prompt(): void
    {
        config(['urge.max_prompt_bytes' => 1024]);

        $provider = LlmProvider::create([
            'name' => 'OpenAI',
            'driver' => 'openai',
            'api_key' => 'sk-test',
            'model' => 'gpt-4',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Prompt size exceeds');
        app(LlmDispatchService::class)->dispatch($provider, str_repeat('x', 2048));
    }
}
