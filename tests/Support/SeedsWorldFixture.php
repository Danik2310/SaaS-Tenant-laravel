<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Seeds the minimal world reference data (countries / states / cities) the
 * registration flow and its location endpoint rely on. The real dataset is the
 * `altwaireb/laravel-world` package's, which is far too large to load per-test;
 * the fixtures cover the exact rows the suites assert against.
 */
trait SeedsWorldFixture
{
    protected function seedWorldFixture(): void
    {
        if (DB::table('countries')->where('iso2', 'US')->exists()) {
            return;
        }

        DB::table('countries')->insert([
            ['id' => 1, 'name' => 'United States', 'iso2' => 'US', 'iso3' => 'USA', 'is_active' => 1],
            ['id' => 2, 'name' => 'Italy', 'iso2' => 'IT', 'iso3' => 'ITA', 'is_active' => 1],
            ['id' => 3, 'name' => 'Canada', 'iso2' => 'CA', 'iso3' => 'CAN', 'is_active' => 1],
        ]);

        DB::table('states')->insert([
            ['id' => 100, 'country_id' => 1, 'name' => 'Illinois', 'is_active' => 1],
            ['id' => 101, 'country_id' => 1, 'name' => 'California', 'is_active' => 1],
            ['id' => 200, 'country_id' => 2, 'name' => 'Latina', 'is_active' => 1],
            ['id' => 300, 'country_id' => 3, 'name' => 'Ontario', 'is_active' => 1],
        ]);

        DB::table('cities')->insert([
            ['id' => 1000, 'country_id' => 1, 'state_id' => 100, 'name' => 'Springfield', 'is_active' => 1],
            ['id' => 1001, 'country_id' => 1, 'state_id' => 100, 'name' => 'Chicago', 'is_active' => 1],
            ['id' => 1002, 'country_id' => 1, 'state_id' => 101, 'name' => 'Los Angeles', 'is_active' => 1],
            ['id' => 1003, 'country_id' => 1, 'state_id' => 100, 'name' => 'New Chicago', 'is_active' => 1],
            ['id' => 2000, 'country_id' => 2, 'state_id' => 200, 'name' => 'Latina', 'is_active' => 1],
            ['id' => 3000, 'country_id' => 3, 'state_id' => 300, 'name' => 'Toronto', 'is_active' => 1],
        ]);
    }
}
