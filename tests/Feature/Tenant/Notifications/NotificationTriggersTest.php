<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Notifications;

use App\Models\AdminUser;
use App\Models\InventoryMovement;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Shared\Constants\NotificationTypes;
use App\Shared\Jobs\ScanTenantNotifications;
use App\Shared\Support\StockLevels;
use Database\Seeders\TenantUserRolePermissionSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Event-driven and scheduled notification triggers. The important invariant is
 * that all three paths — movement observer, order observer and sweep job — use
 * the same dispatcher, so permissions, dedupe and broadcasts stay consistent.
 */
class NotificationTriggersTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTestTenant();
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();
        if (isset($this->tenant)) {
            DB::statement("DROP DATABASE IF EXISTS `{$this->tenant->database()->getName()}`");
        }
        parent::tearDown();
    }

    private function seedRoles(): void
    {
        $this->seed(TenantUserRolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function movementFor(Product $product, string $type, int $quantity): InventoryMovement
    {
        return InventoryMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'type' => $type,
            'quantity' => $quantity,
        ]);
    }

    public function test_outbound_movement_below_threshold_alerts_inventory_managers(): void
    {
        $this->initializeTenant($this->tenant);
        $this->seedRoles();
        $manager = $this->userWithRole('manager');
        $cashier = $this->userWithRole('cashier');

        $product = Product::factory()->create(['active' => true, 'low_stock_threshold' => 5]);
        $this->movementFor($product, 'in', 10);
        $this->movementFor($product, 'out', 7);

        $this->assertSame(1, Notification::where('type', NotificationTypes::LOW_STOCK)->count());
        $this->assertTrue(Notification::where('user_id', $manager->id)->exists());
        $this->assertFalse(Notification::where('user_id', $cashier->id)->exists());
        $this->assertSame(3, StockLevels::quantityFor($product));
    }

    public function test_tenant_threshold_is_used_when_product_has_no_override(): void
    {
        $this->initializeTenant($this->tenant);
        $this->seedRoles();
        $this->userWithRole('manager');

        Setting::create(['key' => StockLevels::TENANT_THRESHOLD_KEY, 'value' => 25]);
        $product = Product::factory()->create(['active' => true]);
        $this->movementFor($product, 'in', 30);

        $this->assertSame(25, StockLevels::effectiveThreshold($product));
        $this->assertSame(0, Notification::where('type', NotificationTypes::LOW_STOCK)->count());

        $this->movementFor($product, 'out', 10);

        $this->assertSame(1, Notification::where('type', NotificationTypes::LOW_STOCK)->count());
    }

    public function test_large_order_alerts_order_managers(): void
    {
        $this->initializeTenant($this->tenant);
        $this->seedRoles();
        $manager = $this->userWithRole('manager');
        $cashier = $this->userWithRole('cashier');

        Setting::create(['key' => 'notifications.large_order_threshold', 'value' => 1000]);
        Order::factory()->create(['total' => 1250, 'created_at' => now()]);

        $this->assertSame(2, Notification::where('type', NotificationTypes::LARGE_ORDER)->count());
        $this->assertTrue(Notification::where('user_id', $manager->id)->exists());
        $this->assertTrue(Notification::where('user_id', $cashier->id)->exists());
    }

    public function test_ordinary_and_backfilled_orders_do_not_alert(): void
    {
        $this->initializeTenant($this->tenant);
        $this->seedRoles();
        $this->userWithRole('manager');

        Setting::create(['key' => 'notifications.large_order_threshold', 'value' => 1000]);
        Order::factory()->create(['total' => 40, 'created_at' => now()]);
        Order::factory()->create(['total' => 5000, 'created_at' => now()->subDay()]);

        $this->assertSame(0, Notification::where('type', NotificationTypes::LARGE_ORDER)->count());
    }

    public function test_scheduled_scan_is_idempotent_for_passwordless_users_and_low_stock(): void
    {
        $this->initializeTenant($this->tenant);
        $this->seedRoles();
        $this->userWithRole('manager');
        $passwordless = User::factory()->create(['password' => null]);

        Setting::create(['key' => StockLevels::TENANT_THRESHOLD_KEY, 'value' => 10]);
        $product = Product::factory()->create(['active' => true]);
        $this->movementFor($product, 'in', 4);
        $this->forgetTenant();

        (new ScanTenantNotifications($this->tenant))->handle();
        $this->initializeTenant($this->tenant);
        $this->assertSame(1, Notification::where('type', NotificationTypes::PASSWORD_NOT_SET)
            ->where('user_id', $passwordless->id)->count());
        $this->assertSame(1, Notification::where('type', NotificationTypes::LOW_STOCK)->count());
        $this->forgetTenant();

        (new ScanTenantNotifications($this->tenant))->handle();
        $this->initializeTenant($this->tenant);
        $this->assertSame(1, Notification::where('type', NotificationTypes::PASSWORD_NOT_SET)
            ->where('user_id', $passwordless->id)->count());
        $this->assertSame(1, Notification::where('type', NotificationTypes::LOW_STOCK)->count());
    }

    public function test_passwordless_login_prompts_once_and_shares_the_sweep_dedupe_key(): void
    {
        $this->initializeTenant($this->tenant);
        $passwordless = User::factory()->create(['password' => null]);
        $withPassword = User::factory()->create();

        Auth::guard('web')->login($passwordless);

        $this->assertSame(1, Notification::where('type', NotificationTypes::PASSWORD_NOT_SET)
            ->where('user_id', $passwordless->id)->count());
        $this->assertSame(0, Notification::where('type', NotificationTypes::PASSWORD_NOT_SET)
            ->where('user_id', $withPassword->id)->count());

        Auth::guard('web')->login($passwordless);

        $this->forgetTenant();
        (new ScanTenantNotifications($this->tenant))->handle();
        $this->initializeTenant($this->tenant);

        $this->assertSame(1, Notification::where('type', NotificationTypes::PASSWORD_NOT_SET)
            ->where('user_id', $passwordless->id)->count());
    }

    public function test_admin_login_does_not_prompt(): void
    {
        $this->initializeTenant($this->tenant);

        event(new Login('admin', new AdminUser, false));

        $this->assertSame(0, Notification::where('type', NotificationTypes::PASSWORD_NOT_SET)->count());
    }
}
