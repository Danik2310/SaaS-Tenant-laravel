<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Plan;
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
            'plan' => 'trial',
            'terms' => true,
        ], $overrides);
    }

    public function test_register_page_renders_active_plans(): void
    {
        Plan::create([
            'name' => 'Legacy',
            'slug' => 'legacy',
            'status' => 'inactive',
            'price' => 0,
            'max_users' => 1,
        ]);

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegister')
                ->has('plans', 5)
                ->has('plans.0', fn (Assert $plan) => $plan
                    ->where('status', 'active')
                    ->has('name')
                    ->has('slug')
                    ->has('price')
                    ->has('currency')
                    ->has('duration_months')
                    ->has('can_signup')
                    ->has('features')
                    ->has('limits'))
                ->where('selected_plan', null)
                ->where('plans.0.slug', 'trial')
                ->where('plans.1.slug', 'free')
                ->where('plans.2.slug', 'growth'));
    }

    public function test_register_page_does_not_list_or_preselect_tampered_plan(): void
    {
        $this->get('/register?plan=nonexistent')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegister')
                ->where('selected_plan', null));
    }

    public function test_guest_can_register_a_workspace_with_trial_plan(): void
    {
        $response = $this->post('/register', $this->payload(['plan' => 'trial']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('domain', 'acme-corp.sasapp')
                ->where('email', 'jane@acme.test')
                ->where('plan', 'Trial')
                ->where('trialDays', (int) config('tenancy.trial_days', 14)));

        $tenant = Tenant::with(['plan', 'activeSubscription'])->where('email', 'jane@acme.test')->firstOrFail();

        $this->assertSame('Jane Doe', $tenant->name);
        $this->assertSame('Acme Corp', $tenant->company_name);
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
        $this->assertSame('Acme Corp', $user->name);

        $this->forgetTenant();
    }

    public function test_guest_can_register_with_full_business_and_contact_details(): void
    {
        $this->post('/register', $this->payload([
            'name' => 'Acme Corp LLC',
            'company_name' => 'Acme Corp',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+1 555 0100',
            'address_line1' => '123 Main St',
            'address_line2' => 'Suite 400',
            'city' => 'Springfield',
            'state' => 'IL',
            'postal_code' => '62701',
            'country' => 'United States',
        ]))->assertOk();

        $tenant = Tenant::where('email', 'jane@acme.test')->firstOrFail();

        $this->assertSame('Acme Corp LLC', $tenant->name);
        $this->assertSame('Acme Corp', $tenant->company_name);
        $this->assertSame('+1 555 0100', $tenant->phone);
        $this->assertSame('123 Main St', $tenant->address_line1);
        $this->assertSame('Suite 400', $tenant->address_line2);
        $this->assertSame('Springfield', $tenant->city);
        $this->assertSame('IL', $tenant->state);
        $this->assertSame('62701', $tenant->postal_code);
        $this->assertSame('United States', $tenant->country);

        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $this->initializeTenant($tenant);

        $user = User::where('email', 'jane@acme.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Jane Doe', $user->name);

        $this->forgetTenant();
    }

    public function test_guest_can_register_a_workspace_with_free_plan(): void
    {
        $response = $this->post('/register', $this->payload(['plan' => 'free']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('plan', 'Free')
                ->where('trialDays', null));

        $tenant = Tenant::with(['plan', 'activeSubscription'])->where('email', 'jane@acme.test')->firstOrFail();

        $this->assertSame('Active', $tenant->status);
        $this->assertNull($tenant->trial_ends_at);
        $this->assertSame('free', $tenant->plan->slug);
        $this->assertSame('active', $tenant->activeSubscription->status);
        $this->assertNull($tenant->activeSubscription->ends_at);

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'acme-corp.sasapp',
        ]);

        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $this->initializeTenant($tenant);

        $user = User::where('email', 'jane@acme.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('tenant-admin'));

        $this->forgetTenant();
    }

    public function test_paid_plan_selection_falls_back_to_trial(): void
    {
        $response = $this->post('/register', $this->payload(['plan' => 'growth']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('plan', 'Trial'));

        $tenant = Tenant::with(['plan', 'activeSubscription'])->where('email', 'jane@acme.test')->firstOrFail();

        $this->assertSame('Trial', $tenant->status);
        $this->assertSame('trial', $tenant->plan->slug);
        $this->assertSame('active', $tenant->activeSubscription->status);

        $this->createdTenantDbNames[] = $tenant->database()->getName();
    }

    public function test_inactive_plan_slug_is_rejected(): void
    {
        Plan::create([
            'name' => 'Legacy',
            'slug' => 'legacy',
            'status' => 'inactive',
            'price' => 0,
            'max_users' => 1,
        ]);

        $this->post('/register', $this->payload(['plan' => 'legacy']))
            ->assertSessionHasErrors('plan');

        $this->assertSame(0, Tenant::count());
    }

    public function test_unknown_plan_slug_is_rejected(): void
    {
        $this->post('/register', $this->payload(['plan' => 'enterprise-plus']))
            ->assertSessionHasErrors('plan');

        $this->assertSame(0, Tenant::count());
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
