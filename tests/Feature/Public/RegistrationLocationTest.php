<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsWorldFixture;
use Tests\TestCase;

class RegistrationLocationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsWorldFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorldFixture();
    }

    public function test_cities_endpoint_returns_matching_names(): void
    {
        $this->getJson('/register/locations/cities?country=US&term=Spring')
            ->assertOk()
            ->assertJsonPath('data', ['Springfield']);
    }

    public function test_cities_endpoint_filters_by_state(): void
    {
        $this->getJson('/register/locations/cities?country=US&state=Illinois&term=Los')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/register/locations/cities?country=US&state=California&term=Los')
            ->assertOk()
            ->assertJsonPath('data', ['Los Angeles']);
    }

    public function test_cities_endpoint_orders_exact_then_prefix_then_alphabetical(): void
    {
        $this->getJson('/register/locations/cities?country=US&state=Illinois&term=Chicago')
            ->assertOk()
            ->assertJsonPath('data', ['Chicago', 'New Chicago']);

        // Both contain "Ch"; the exact match still leads the prefix match.
        $this->getJson('/register/locations/cities?country=US&state=Illinois&term=Ch')
            ->assertOk()
            ->assertJsonPath('data', ['Chicago', 'New Chicago']);
    }

    public function test_cities_endpoint_requires_a_country(): void
    {
        $this->getJson('/register/locations/cities')
            ->assertStatus(422)
            ->assertJsonValidationErrors('country');
    }

    public function test_cities_endpoint_clamps_the_limit(): void
    {
        $this->getJson('/register/locations/cities?country=US&limit=1000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('limit');
    }

    public function test_cities_endpoint_returns_empty_for_unknown_country(): void
    {
        $this->getJson('/register/locations/cities?country=ZZ&term=Spring')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_cities_endpoint_is_rate_limited(): void
    {
        foreach (range(1, 61) as $i) {
            $response = $this->getJson('/register/locations/cities?country=US');
        }

        $response->assertStatus(429);
    }
}
