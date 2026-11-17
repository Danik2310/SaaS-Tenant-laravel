<?php

use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\Currency;
use Nnjeim\World\Models\Language;
use Nnjeim\World\Models\State;
use Nnjeim\World\Models\Timezone;

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed countries to be loaded.
    | Leave it empty to load all countries else include the country iso2
    | value in the allowed_countries array.
    |--------------------------------------------------------------------------
    */

    'allowed_countries' => [],

    /*
    |--------------------------------------------------------------------------
    | Disallowed countries to not be loaded.
    | Leave it empty to allow all countries to be loaded else include the
    | country iso2 value in the disallowed_countries array.
    |--------------------------------------------------------------------------
    */

    'disallowed_countries' => [],

    /*
    |--------------------------------------------------------------------------
    | Supported locales.
    |--------------------------------------------------------------------------
    */

    'accepted_locales' => [
        'ar',
        'az',
        'bn',
        'br',
        'de',
        'en',
        'es',
        'fa',
        'fr',
        'hr',
        'hu',
        'hy',
        'it',
        'ja',
        'kr',
        'ne',
        'nl',
        'pl',
        'pt',
        'ro',
        'ru',
        'sw',
        'tr',
        'zh',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled modules. The cities module depends on the states module.
    | Only countries, states and cities back the tenant registration form.
    |--------------------------------------------------------------------------
    */

    'modules' => [
        'states' => true,
        'cities' => true,
        'timezones' => false,
        'currencies' => false,
        'languages' => false,
        'geolocate' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Package API routes. The app serves location data through LocationCatalog.
    |--------------------------------------------------------------------------
    */

    'routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Connection.
    |--------------------------------------------------------------------------
    */

    'connection' => env('WORLD_DB_CONNECTION', env('DB_CONNECTION')),

    /*
    |--------------------------------------------------------------------------
    | Cache.
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('WORLD_CACHE_ENABLED', true),
        'ttl' => env('WORLD_CACHE_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Migrations.
    |--------------------------------------------------------------------------
    */

    'migrations' => [
        'data_records' => [
            'table_name' => 'world_data_records',
        ],
        'countries' => [
            'table_name' => 'countries',
            'optional_fields' => [
                'phone_code' => [
                    'required' => true,
                    'type' => 'string',
                    'length' => 5,
                ],
                'iso3' => [
                    'required' => true,
                    'type' => 'string',
                    'length' => 3,
                ],
                'native' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'region' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'subregion' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'latitude' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'longitude' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'emoji' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'emojiU' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ],
        'states' => [
            'table_name' => 'states',
            'optional_fields' => [
                'country_code' => [
                    'required' => true,
                    'type' => 'string',
                    'length' => 3,
                ],
                'state_code' => [
                    'required' => false,
                    'type' => 'string',
                    'length' => 5,
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'latitude' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'longitude' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ],
        'cities' => [
            'table_name' => 'cities',
            'optional_fields' => [
                'country_code' => [
                    'required' => true,
                    'type' => 'string',
                    'length' => 3,
                ],
                'state_code' => [
                    'required' => false,
                    'type' => 'string',
                    'length' => 5,
                ],
                'latitude' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'longitude' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ],
        'timezones' => [
            'table_name' => 'timezones',
        ],
        'currencies' => [
            'table_name' => 'currencies',
        ],
        'languages' => [
            'table_name' => 'languages',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fully qualified class names for package models.
    |--------------------------------------------------------------------------
    */

    'models' => [
        'cities' => City::class,
        'countries' => Country::class,
        'currencies' => Currency::class,
        'languages' => Language::class,
        'states' => State::class,
        'timezones' => Timezone::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocation settings. Kept for config parity; the module is disabled.
    |--------------------------------------------------------------------------
    */

    'geolocate' => [
        'database_path' => storage_path('app/geoip/GeoLite2-City.mmdb'),
        'cache_ttl' => env('WORLD_GEOLOCATE_CACHE_TTL', 86400),
        'maxmind_license_key' => env('MAXMIND_LICENSE_KEY'),
        'fallback_api' => env('WORLD_GEOLOCATE_FALLBACK_API', true),
    ],
];
