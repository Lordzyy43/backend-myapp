<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Basic API Health Checks
 *
 * This test class verifies that the application is running
 * and major endpoints are accessible
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_is_responsive()
    {
        $response = $this->getJson('/api/v1/sports');

        $response->assertStatus(200);
    }

    #[Test]
    public function application_responds_with_json()
    {
        $response = $this->getJson('/api/v1/sports');

        $response->assertHeader('content-type', 'application/json');
    }

    #[Test]
    public function invalid_endpoint_returns_404()
    {
        $response = $this->getJson('/api/v1/nonexistent');

        $response->assertStatus(404);
    }

    #[Test]
    public function response_is_valid_json()
    {
        $response = $this->getJson('/api/v1/sports');

        $response->assertStatus(200);
        // Should be valid JSON
        $this->assertIsArray($response->json());
    }

    #[Test]
    public function api_version_is_correct()
    {
        $response = $this->getJson('/api/v1/sports');

        $response->assertStatus(200);
        // V1 endpoint should be accessible
    }
}
