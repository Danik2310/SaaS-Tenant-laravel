<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_seeded_plan_has_summary_and_footnote(): void
    {
        $this->seed();

        $copy = (array) config('plan_copy');

        $plans = Plan::query()
            ->whereIn('slug', ['trial', 'free', 'growth', 'pro', 'enterprise'])
            ->get(['slug']);

        $slugs = $plans->pluck('slug')->sort()->values()->all();
        $configSlugs = collect(array_keys($copy))
            ->sort()
            ->values()
            ->all();

        $this->assertSame($slugs, $configSlugs);

        foreach ($slugs as $slug) {
            $this->assertArrayHasKey($slug, $copy);
            $this->assertIsString($copy[$slug]['summary'] ?? null);
            $this->assertIsString($copy[$slug]['footnote'] ?? null);
            $this->assertNotSame('', trim($copy[$slug]['summary']));
            $this->assertNotSame('', trim($copy[$slug]['footnote']));
        }
    }
}
