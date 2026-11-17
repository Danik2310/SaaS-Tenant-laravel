<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Seeds the minimal world reference data (countries / states / cities) the
 * registration flow and its location endpoint rely on. The real dataset is the
 * `nnjeim/world` package's, which is far too large to load per-test; the
 * fixtures cover the exact rows the suites assert against.
 */
trait SeedsWorldFixture
{
    protected function seedWorldFixture(): void
    {
        if (DB::table('countries')->where('iso2', 'US')->exists()) {
            return;
        }

        DB::table('countries')->insert([
            [
                'id' => 1,
                'name' => 'United States',
                'iso2' => 'US',
                'iso3' => 'USA',
                'phone_code' => '1',
                'region' => 'Americas',
                'subregion' => 'North America',
                'status' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Italy',
                'iso2' => 'IT',
                'iso3' => 'ITA',
                'phone_code' => '39',
                'region' => 'Europe',
                'subregion' => 'Southern Europe',
                'status' => 1,
            ],
            [
                'id' => 3,
                'name' => 'Canada',
                'iso2' => 'CA',
                'iso3' => 'CAN',
                'phone_code' => '1',
                'region' => 'Americas',
                'subregion' => 'North America',
                'status' => 1,
            ],
        ]);

        DB::table('states')->insert([
            ['id' => 100, 'country_id' => 1, 'country_code' => 'US', 'name' => 'Illinois'],
            ['id' => 101, 'country_id' => 1, 'country_code' => 'US', 'name' => 'California'],
            ['id' => 200, 'country_id' => 2, 'country_code' => 'IT', 'name' => 'Latina'],
            ['id' => 300, 'country_id' => 3, 'country_code' => 'CA', 'name' => 'Ontario'],
        ]);

        DB::table('cities')->insert([
            ['id' => 1000, 'country_id' => 1, 'state_id' => 100, 'country_code' => 'US', 'name' => 'Springfield'],
            ['id' => 1001, 'country_id' => 1, 'state_id' => 100, 'country_code' => 'US', 'name' => 'Chicago'],
            ['id' => 1002, 'country_id' => 1, 'state_id' => 101, 'country_code' => 'US', 'name' => 'Los Angeles'],
            ['id' => 1003, 'country_id' => 1, 'state_id' => 100, 'country_code' => 'US', 'name' => 'New Chicago'],
            ['id' => 2000, 'country_id' => 2, 'state_id' => 200, 'country_code' => 'IT', 'name' => 'Latina'],
            ['id' => 3000, 'country_id' => 3, 'state_id' => 300, 'country_code' => 'CA', 'name' => 'Toronto'],
        ]);
    }
}
