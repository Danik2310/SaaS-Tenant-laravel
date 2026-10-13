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

    public function test_cities_endpoint_returns_the_full_country_scope(): void
    {
        $this->getJson('/register/locations/cities?country=US')
            ->assertOk()
            ->assertJsonPath('data', ['Chicago', 'Los Angeles', 'New Chicago', 'Springfield']);
    }

    public function test_cities_endpoint_narrows_to_a_state(): void
    {
        $this->getJson('/register/locations/cities?country=US&state=Illinois')
            ->assertOk()
            ->assertJsonPath('data', ['Chicago', 'New Chicago', 'Springfield']);

        $this->getJson('/register/locations/cities?country=US&state=California')
            ->assertOk()
            ->assertJsonPath('data', ['Los Angeles']);
    }

    public function test_cities_endpoint_returns_empty_for_an_unknown_state(): void
    {
        $this->getJson('/register/locations/cities?country=US&state=Atlantis')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_cities_endpoint_requires_a_country(): void
    {
        $this->getJson('/register/locations/cities')
            ->assertStatus(422)
            ->assertJsonValidationErrors('country');
    }

    public function test_cities_endpoint_returns_empty_for_unknown_country(): void
    {
        $this->getJson('/register/locations/cities?country=ZZ')
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
