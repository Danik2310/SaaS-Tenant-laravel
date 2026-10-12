<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Billing\Contracts\PaymentGatewayInterface;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\Jobs\CreateTenantAdminUser;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
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

    /**
     * Payload shaped as it exists in the pending session after store(): the
     * password is already a bcrypt hash, never the plaintext.
     */
    private function hashedPayload(array $overrides = []): array
    {
        $data = $this->payload($overrides);
        $data['password'] = Hash::make($data['password']);
        $data['password_confirmation'] = $data['password'];

        return $data;
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
                    ->has('limits')
                    ->has('summary')
                    ->has('footnote'))
                ->where('selected_plan', null)
                ->where('tenant_domain_suffix', 'sasapp')
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

    public function test_guest_can_choose_a_custom_subdomain(): void
    {
        $response = $this->post('/register', $this->payload(['subdomain' => 'my-workspace']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('domain', 'my-workspace.sasapp')
                ->where('email', 'jane@acme.test'));

        $tenant = Tenant::with(['plan', 'activeSubscription'])->where('email', 'jane@acme.test')->firstOrFail();

        $this->assertSame('Trial', $tenant->status);
        $this->assertSame('trial', $tenant->plan->slug);

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'my-workspace.sasapp',
        ]);

        $this->createdTenantDbNames[] = $tenant->database()->getName();
    }

    public function test_taken_subdomain_is_rejected(): void
    {
        $existing = Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'TEN-000200',
            'name' => 'Existing Workspace',
            'email' => 'owner@acme.test',
            'status' => 'Active',
        ]));

        $existing->domains()->create(['domain' => 'taken.sasapp']);

        $this->post('/register', $this->payload(['subdomain' => 'taken']))
            ->assertSessionHasErrors('subdomain');

        $this->assertSame(1, Tenant::count());
    }

    public function test_invalid_subdomain_formats_are_rejected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach (['Acme Corp', '-acme', 'acme-', 'ac__me', 'a'.str_repeat('x', 70)] as $subdomain) {
            $this->post('/register', $this->payload(['subdomain' => $subdomain]))
                ->assertSessionHasErrors('subdomain');
        }

        $this->assertSame(0, Tenant::count());
    }

    public function test_paid_plan_selection_redirects_to_checkout(): void
    {
        $this->mock(PaymentGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn([
                    'id' => 'cs_test_123',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
                    'payment_status' => 'unpaid',
                    'currency' => 'usd',
                    'amount_total' => 1500,
                ]);
        });

        $response = $this->post('/register', $this->payload(['plan' => 'growth', 'subdomain' => 'acme-corp']));

        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

        $this->assertSame(0, Tenant::count());

        $pending = session('pending_tenant_registration');
        $this->assertNotFalse($pending);
        $this->assertSame('growth', $pending['plan'] ?? null);
        $this->assertSame('jane@acme.test', $pending['payload']['email'] ?? null);
        $this->assertSame('acme-corp', $pending['payload']['subdomain'] ?? null);

        // C1: only the hash is stored; the raw password never touches the session.
        $this->assertNotSame('StrongPass1!', $pending['payload']['password'] ?? null);
        $this->assertTrue(Hash::check('StrongPass1!', $pending['payload']['password'] ?? ''));
        $this->assertStringNotContainsString('StrongPass1!', serialize(session()->all()));

        // H1: a pending signup carries an expiry and is fresh right after store().
        $this->assertIsInt($pending['expires_at'] ?? null);
        $this->assertGreaterThan(time(), $pending['expires_at']);
    }

    public function test_checkout_success_provisions_paid_tenant(): void
    {
        $token = 'checkout-token';

        $this->withSession([
            'pending_tenant_registration' => [
                'token' => $token,
                'plan' => 'growth',
                'payload' => $this->hashedPayload(['plan' => 'growth']),
                'expires_at' => now()->addMinutes(30)->getTimestamp(),
            ],
        ]);

        $this->mock(PaymentGatewayInterface::class, function ($mock) use ($token) {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with('cs_test_123')
                ->andReturn([
                    'id' => 'cs_test_123',
                    'payment_status' => 'paid',
                    'customer_email' => 'jane@acme.test',
                    'metadata' => [
                        'pending_token' => $token,
                    ],
                    'url' => '',
                    'currency' => 'usd',
                    'amount_total' => 1500,
                ]);
        });

        $this->get('/register/payment/success?session_id=cs_test_123')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('domain', 'acme-corp.sasapp')
                ->where('plan', 'Growth'));

        $tenant = Tenant::with(['plan', 'activeSubscription'])->where('email', 'jane@acme.test')->firstOrFail();

        $this->assertSame('Active', $tenant->status);
        $this->assertNull($tenant->trial_ends_at);
        $this->assertSame('growth', $tenant->plan->slug);
        $this->assertSame('active', $tenant->activeSubscription->status);
        $this->assertNotNull($tenant->activeSubscription->ends_at);
        $this->assertTrue($tenant->activeSubscription->ends_at->isFuture());

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'acme-corp.sasapp',
        ]);

        $this->assertFalse(session()->has('pending_tenant_registration'));

        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $this->initializeTenant($tenant);

        $user = User::where('email', 'jane@acme.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('tenant-admin'));
        $this->assertTrue(Hash::check('StrongPass1!', $user->password));

        $this->forgetTenant();

        // C1: neither the session nor the persisted user ever held plaintext.
        $this->assertStringNotContainsString('StrongPass1!', serialize(session()->all()));
    }

    public function test_checkout_success_provisions_paid_tenant_with_chosen_subdomain(): void
    {
        $token = 'checkout-token';

        $this->withSession([
            'pending_tenant_registration' => [
                'token' => $token,
                'plan' => 'growth',
                'payload' => $this->hashedPayload(['plan' => 'growth', 'subdomain' => 'my-workspace']),
                'expires_at' => now()->addMinutes(30)->getTimestamp(),
            ],
        ]);

        $this->mock(PaymentGatewayInterface::class, function ($mock) use ($token) {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with('cs_test_123')
                ->andReturn([
                    'id' => 'cs_test_123',
                    'payment_status' => 'paid',
                    'customer_email' => 'jane@acme.test',
                    'metadata' => [
                        'pending_token' => $token,
                    ],
                    'url' => '',
                    'currency' => 'usd',
                    'amount_total' => 1500,
                ]);
        });

        $this->get('/register/payment/success?session_id=cs_test_123')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('domain', 'my-workspace.sasapp'));

        $tenant = Tenant::with(['plan', 'activeSubscription'])->where('email', 'jane@acme.test')->firstOrFail();

        $this->assertSame('Active', $tenant->status);
        $this->assertSame('growth', $tenant->plan->slug);

        $this->assertDatabaseHas('domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'my-workspace.sasapp',
        ]);

        $this->createdTenantDbNames[] = $tenant->database()->getName();
    }

    public function test_checkout_success_rejects_unpaid_session(): void
    {
        $this->withSession([
            'pending_tenant_registration' => [
                'token' => 'checkout-token',
                'plan' => 'growth',
                'payload' => $this->hashedPayload(['plan' => 'growth']),
                'expires_at' => now()->addMinutes(30)->getTimestamp(),
            ],
        ]);

        $this->mock(PaymentGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with('cs_test_123')
                ->andReturn([
                    'id' => 'cs_test_123',
                    'payment_status' => 'unpaid',
                    'customer_email' => '',
                    'metadata' => [],
                    'url' => '',
                    'currency' => 'usd',
                    'amount_total' => 1500,
                ]);
        });

        $this->get('/register/payment/success?session_id=cs_test_123')->assertRedirect();

        $this->assertSame(0, Tenant::count());
    }

    public function test_checkout_cancel_clears_pending_and_returns_to_register(): void
    {
        $this->withSession([
            'pending_tenant_registration' => [
                'token' => 'checkout-token',
                'plan' => 'growth',
                'payload' => $this->hashedPayload(['plan' => 'growth']),
                'expires_at' => now()->addMinutes(30)->getTimestamp(),
            ],
        ]);

        $this->get('/register/payment/cancel?plan=growth')
            ->assertRedirect(route('register.tenant', ['plan' => 'growth', 'cancelled' => 1]));

        // H1: the pending signup (PII + hashed credential) is purged on cancel.
        $this->assertFalse(session()->has('pending_tenant_registration'));
        $this->assertSame(0, Tenant::count());
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

        // H2: the conflict message is generic and never echoes the submitted
        // address, so a guest cannot use it to probe registered emails.
        $message = session('errors')->get('email')[0] ?? '';
        $this->assertSame('An account already exists for that email.', $message);
        $this->assertStringNotContainsString('jane@acme.test', $message);

        $this->assertSame(1, Tenant::count());
    }

    public function test_soft_deleted_tenant_email_can_be_reused(): void
    {
        $old = Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'TEN-000100',
            'name' => 'Former Workspace',
            'email' => 'jane@acme.test',
            'status' => 'Inactive',
        ]));

        $old->delete();

        $this->post('/register', $this->payload())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegisterSuccess')
                ->where('domain', 'acme-corp.sasapp'));

        // The trashed row is invisible to the conflict check, so the address
        // can be claimed again. The DB only enforces uniqueness on domains;
        // the two rows (trashed + active) may share an email.
        $this->assertSame(1, Tenant::count());
        $this->assertSame(2, Tenant::withTrashed()->count());

        $tenant = Tenant::where('email', 'jane@acme.test')->firstOrFail();
        $this->assertNull($tenant->deleted_at);

        $this->createdTenantDbNames[] = $tenant->database()->getName();
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

    public function test_password_is_hashed_before_session_or_queue(): void
    {
        // C1 on the no-payment path: the queued job must never serialize the
        // plaintext password — only the already-computed hash. Faking the
        // whole queue would also swallow the tenant DB-creation jobs inside
        // provisioning, so only the admin-user job is intercepted.
        Queue::fake([CreateTenantAdminUser::class]);

        $this->post('/register', $this->payload(['plan' => 'trial']))
            ->assertOk();

        Queue::assertPushed(CreateTenantAdminUser::class, function (CreateTenantAdminUser $job) {
            return ! str_contains(serialize($job), 'StrongPass1!');
        });

        $this->assertStringNotContainsString('StrongPass1!', serialize(session()->all()));
    }

    public function test_checkout_success_rejects_expired_pending_signup(): void
    {
        $this->withSession([
            'pending_tenant_registration' => [
                'token' => 'old-token',
                'plan' => 'growth',
                'payload' => $this->hashedPayload(['plan' => 'growth']),
                'expires_at' => now()->subMinutes(5)->getTimestamp(),
            ],
        ]);

        $this->mock(PaymentGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with('cs_test_123')
                ->andReturn([
                    'id' => 'cs_test_123',
                    'payment_status' => 'paid',
                    'customer_email' => 'jane@acme.test',
                    'metadata' => ['pending_token' => 'old-token'],
                    'url' => '',
                    'currency' => 'usd',
                    'amount_total' => 1500,
                ]);
        });

        $this->get('/register/payment/success?session_id=cs_test_123')
            ->assertRedirect(route('register.tenant'));

        $this->assertSame(0, Tenant::count());
        $this->assertFalse(session()->has('pending_tenant_registration'));
    }

    public function test_checkout_success_rejects_amount_mismatch(): void
    {
        // L2: a completed $15 Growth checkout reporting a different charged
        // total must not provision the tenant.
        $this->withSession([
            'pending_tenant_registration' => [
                'token' => 'checkout-token',
                'plan' => 'growth',
                'payload' => $this->hashedPayload(['plan' => 'growth']),
                'expires_at' => now()->addMinutes(30)->getTimestamp(),
            ],
        ]);

        $this->mock(PaymentGatewayInterface::class, function ($mock) {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with('cs_test_123')
                ->andReturn([
                    'id' => 'cs_test_123',
                    'payment_status' => 'paid',
                    'customer_email' => 'jane@acme.test',
                    'metadata' => ['pending_token' => 'checkout-token'],
                    'url' => '',
                    'currency' => 'usd',
                    'amount_total' => 1499,
                ]);
        });

        $this->get('/register/payment/success?session_id=cs_test_123')
            ->assertRedirect(route('register.tenant'));

        $this->assertSame(0, Tenant::count());
        $this->assertFalse(session()->has('pending_tenant_registration'));
    }

    public function test_canceled_signup_prefill_excludes_credentials(): void
    {
        // C2/M3: revisiting the form with a live pending signup restores only
        // the non-sensitive fields — never the hashed credential or the full
        // business payload.
        $this->withSession([
            'pending_tenant_registration' => [
                'token' => 'checkout-token',
                'plan' => 'growth',
                'payload' => $this->hashedPayload([
                    'plan' => 'growth',
                    'subdomain' => 'acme-corp',
                    'company_name' => 'Acme Corp',
                    'address_line1' => '123 Main St',
                ]),
                'expires_at' => now()->addMinutes(30)->getTimestamp(),
            ],
        ]);

        $this->get('/register?cancelled=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegister')
                ->where('cancelled', true)
                ->has('prefill', 4)
                ->where('prefill.plan', 'growth')
                ->where('prefill.email', 'jane@acme.test')
                ->where('prefill.name', 'Jane Doe')
                ->where('prefill.subdomain', 'acme-corp')
                ->missing('prefill.password')
                ->missing('prefill.password_confirmation')
                ->missing('prefill.company_name')
                ->missing('prefill.address_line1'));
    }

    public function test_expired_pending_signup_is_not_prefilled(): void
    {
        // H1: a stale pending signup yields no prefill at all.
        $this->withSession([
            'pending_tenant_registration' => [
                'token' => 'old-token',
                'plan' => 'growth',
                'payload' => $this->hashedPayload(['plan' => 'growth']),
                'expires_at' => now()->subMinutes(5)->getTimestamp(),
            ],
        ]);

        $this->get('/register?cancelled=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TenantRegister')
                ->where('cancelled', true)
                ->where('prefill', null));
    }

    public function test_registration_throttled_per_email_across_ips(): void
    {
        // H3/M4: rotating IP addresses cannot bypass the per-address cap, because
        // the registration limiter is keyed on the hashed submission email. Each
        // request fails validation cheaply but still consumes the email budget.
        // Throttling stays enabled on purpose; withThrottling is the default.
        $response = null;

        foreach (range(1, 6) as $i) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
                ->post('/register', $this->payload([
                    'email' => 'probe@acme.test',
                    'password' => 'short',
                ]));
        }

        $response->assertStatus(429);
        $this->assertSame(0, Tenant::count());
    }
}
