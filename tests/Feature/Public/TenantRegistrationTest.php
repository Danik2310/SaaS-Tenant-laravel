<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Contracts\TenantManagerInterface;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected array $createdTenantDbNames = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($this->createdTenantDbNames as $name) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `$name`");
            } catch (\Exception $e) {
                // already dropped
            }
        }

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Acme Corp',
            'name' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'phone' => '+1 555 0100',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'terms' => true,
        ], $overrides);
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/TenantRegister'));
    }

    public function test_guest_can_register_a_workspace_with_trial_plan(): void
    {
        $response = $this->post('/register', $this->payload());

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('domain', 'acme-corp.sasapp')
                ->where('email', 'jane@acme.test'));

        $tenant = Tenant::with(['plan', 'activeSubscription'])->where('email', 'jane@acme.test')->firstOrFail();

        $this->assertSame('Acme Corp', $tenant->name);
        $this->assertSame('Trial', $tenant->status);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertTrue($tenant->trial_ends_at->isFuture());
        $this->assertSame('trial', $tenant->plan->slug);
        $this->assertSame('active', $tenant->activeSubscription->status);

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'acme-corp.sasapp',
        ]);

        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $this->initializeTenant($tenant);

        $user = User::where('email', 'jane@acme.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('tenant-admin'));
        $this->assertTrue(Hash::check('StrongPass1!', $user->password));

        $this->forgetTenant();
    }

    public function test_email_conflicting_with_existing_tenant_is_rejected(): void
    {
        Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'TEN-000100',
            'name' => 'Existing Workspace',
            'email' => 'jane@acme.test',
            'status' => 'Active',
        ]));

        $this->post('/register', $this->payload())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Tenant::count());
    }

    public function test_honeypot_field_rejects_bots(): void
    {
        $this->post('/register', $this->payload(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');
    }

    public function test_provisioning_failure_returns_friendly_error(): void
    {
        $this->mock(TenantManagerInterface::class, function ($mock) {
            $mock->shouldReceive('provision')
                ->once()
                ->andThrow(new InvalidArgumentException('Database conflict'));
        });

        $this->post('/register', $this->payload())
            ->assertSessionHasErrors('provisioning')
            ->assertSessionDoesntHaveErrors('email');
    }
}
