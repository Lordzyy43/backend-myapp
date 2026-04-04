<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PublicApiTest extends TestCase
{

    use RefreshDatabase;

    public function test_example(): void
    {
        $response = $this->getJson('/api/v1/sports');

        $response->assertStatus(200);
    }
}
