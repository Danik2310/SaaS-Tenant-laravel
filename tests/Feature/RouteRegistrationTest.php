<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as RouteObject;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards route registration invariants.
 *
 * Route names must be globally unique: `php artisan route:cache` (run by
 * `php artisan optimize`) refuses to serialize a collection that contains two
 * routes with the same name. Central-domain-only routes are registered once per
 * entry in `tenancy.central_domains`, so a named route inside that loop would
 * collide as soon as more than one central domain is configured.
 *
 * Flow: boot the application, collect every named route and assert the set of
 * duplicate names is empty.
 */
class RouteRegistrationTest extends TestCase
{
    public function test_no_two_routes_share_a_name(): void
    {
        $duplicates = collect(Route::getRoutes()->getRoutes())
            ->map(static fn (RouteObject $route): ?string => $route->getName())
            ->filter()
            ->duplicates()
            ->unique()
            ->values();

        $this->assertTrue(
            $duplicates->isEmpty(),
            'Duplicate route names would break route:cache: '.$duplicates->implode(', '),
        );
    }
}
