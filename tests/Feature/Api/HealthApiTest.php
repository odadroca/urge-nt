<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version', '2.0')
            ->assertJsonStructure(['status', 'version', 'timestamp']);
    }

    public function test_health_endpoint_requires_no_auth(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
    }
}
