<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Tenants\Generators\SequentialIdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Tests\TestCase;

class SequentialIdGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([TenantCreated::class, TenantDeleted::class]);
    }

    public function test_predict_next_starts_at_one_when_no_tenants_exist(): void
    {
        $this->assertSame('TEN-000001', SequentialIdGenerator::predictNext());
    }

    public function test_predict_next_increments_the_highest_existing_id(): void
    {
        Tenant::factory()->create(['id' => 'TEN-000005']);
        Tenant::factory()->create(['id' => 'TEN-000003']);

        $this->assertSame('TEN-000006', SequentialIdGenerator::predictNext());
    }

    public function test_predict_next_counts_soft_deleted_tenants(): void
    {
        $tenant = Tenant::factory()->create(['id' => 'TEN-000010']);
        $tenant->delete();

        $this->assertSame('TEN-000011', SequentialIdGenerator::predictNext());
    }

    public function test_generate_matches_predict_next(): void
    {
        Tenant::factory()->create(['id' => 'TEN-000042']);

        $this->assertSame(
            SequentialIdGenerator::predictNext(),
            SequentialIdGenerator::generate(new Tenant)
        );
        $this->assertSame('TEN-000043', SequentialIdGenerator::generate(new Tenant));
    }
}
