<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Sport;
use App\Models\Venue;
use App\Models\Court;
use PHPUnit\Framework\Attributes\Test;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_list_sports()
    {
        Sport::factory(5)->create();
        $response = $this->getJson('/api/v1/sports');
        $response->assertStatus(200);
    }

    #[Test]
    public function can_list_venues()
    {
        Venue::factory(3)->create();
        $response = $this->getJson('/api/v1/venues');
        $response->assertStatus(200);
    }

    #[Test]
    public function can_get_venue_details()
    {
        $venue = Venue::factory()->create();
        $response = $this->getJson("/api/v1/venues/{$venue->id}");
        $response->assertStatus(200);
    }

    #[Test]
    public function can_get_court_details()
    {
        $court = Court::factory()->create();
        $response = $this->getJson("/api/v1/courts/{$court->id}");
        $response->assertStatus(200);
    }

    #[Test]
    public function can_search_venues()
    {
        Venue::factory()->create(['name' => 'Basketball Arena']);
        Venue::factory(2)->create();
        $response = $this->getJson('/api/v1/venues?search=Basketball');
        $response->assertStatus(200);
    }

    #[Test]
    public function can_filter_venues_by_city()
    {
        Venue::factory()->create(['city' => 'Jakarta']);
        Venue::factory()->create(['city' => 'Surabaya']);
        $response = $this->getJson('/api/v1/venues?city=Jakarta');
        $response->assertStatus(200);
    }

    #[Test]
    public function can_get_availability()
    {
        $court = Court::factory()->create();
        $response = $this->getJson("/api/v1/availability?court_id={$court->id}&date=" . now()->addDays(1)->toDateString());
        $response->assertStatus(200);
    }

    #[Test]
    public function invalid_court_returns_404()
    {
        $response = $this->getJson('/api/v1/courts/99999');
        $response->assertStatus(404);
    }

    #[Test]
    public function invalid_venue_returns_404()
    {
        $response = $this->getJson('/api/v1/venues/99999');
        $response->assertStatus(404);
    }

    #[Test]
    public function public_endpoints_accessible()
    {
        $response = $this->getJson('/api/v1/sports');
        $response->assertStatus(200);
    }
}
