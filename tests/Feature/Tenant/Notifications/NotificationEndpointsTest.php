<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Notifications;

use App\Models\Notification;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Shared\Constants\NotificationTypes;
use App\Shared\Support\StockLevels;
use Database\Seeders\TenantUserRolePermissionSeeder;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * HTTP surface of the notification centre: strict per-user scoping, shared
 * layout props, and permission-gated settings that feed the trigger thresholds.
 */
class NotificationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTestTenant();
        $this->initializeTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        $this->forgetTenant();
        if (isset($this->tenant)) {
            DB::statement("DROP DATABASE IF EXISTS `{$this->tenant->database()->getName()}`");
        }
        parent::tearDown();
    }

    private function userWithRole(string $role): User
    {
        $this->seed(TenantUserRolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_index_only_returns_the_current_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Notification::factory()->create(['user_id' => $user->id, 'type' => NotificationTypes::LOW_STOCK]);
        Notification::factory()->create(['user_id' => $other->id, 'type' => NotificationTypes::LARGE_ORDER]);

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Notifications/Index', false)
                ->where('notifications.total', 1)
                ->where('notifications.data.0.type', NotificationTypes::LOW_STOCK)
                ->where('notification_channel', 'tenant-notifications.'.$this->tenant->id));
    }

    public function test_shared_props_expose_a_scoped_unread_count(): void
    {
        $user = User::factory()->create();
        Notification::factory()->create(['user_id' => $user->id, 'read_at' => null]);
        Notification::factory()->read()->create(['user_id' => $user->id]);
        Notification::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread_count', 1)
                ->has('notifications.recent', 2));
    }

    public function test_marking_a_notification_read_rejects_another_users_row(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->patch("/notifications/{$notification->id}")
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_marking_read_and_mark_all_only_touch_own_rows(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Notification::factory()->create(['user_id' => $user->id]);
        $theirs = Notification::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->patch("/notifications/{$mine->id}")
            ->assertRedirect();

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);

        $another = Notification::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)
            ->post('/notifications/read-all')
            ->assertRedirect();

        $this->assertNotNull($another->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_private_channel_authorizes_only_the_current_tenant(): void
    {
        // The default null broadcaster does not run channel callbacks, so use
        // Pusher's signer to exercise the authorization path end to end.
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['cluster' => 'test', 'useTLS' => true],
        ]);
        app(BroadcastManager::class)->forgetDrivers();
        // Channel callbacks are registered on the resolved driver, so re-run the
        // channel file after swapping drivers.
        require base_path('routes/channels.php');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'tenant-notifications.'.$this->tenant->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'tenant-notifications.some-other-tenant',
            ])
            ->assertForbidden();
    }

    public function test_settings_require_manage_settings_permission(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get('/notifications/settings')->assertForbidden();
        $this->actingAs($manager)->put('/notifications/settings', [
            'low_stock_threshold' => 5,
            'large_order_threshold' => 100,
            'spike_multiplier' => 2,
        ])->assertForbidden();
    }

    public function test_admin_can_update_thresholds_that_drive_the_triggers(): void
    {
        $admin = $this->userWithRole('tenant-admin');

        $this->actingAs($admin)
            ->get('/notifications/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Notifications/Settings', false)
                ->where('settings.low_stock_threshold', 10));

        $this->actingAs($admin)
            ->put('/notifications/settings', [
                'low_stock_threshold' => 7,
                'large_order_threshold' => 500,
                'spike_multiplier' => 4,
            ])
            ->assertRedirect();

        $this->assertSame(7, StockLevels::tenantThreshold());
        $this->assertEquals(500, Setting::query()->where('key', 'notifications.large_order_threshold')->value('value'));
    }

    public function test_settings_validation_rejects_negative_thresholds(): void
    {
        $admin = $this->userWithRole('tenant-admin');

        $this->actingAs($admin)
            ->put('/notifications/settings', [
                'low_stock_threshold' => -1,
                'large_order_threshold' => 500,
                'spike_multiplier' => 4,
            ])
            ->assertSessionHasErrors('low_stock_threshold');
    }
}
