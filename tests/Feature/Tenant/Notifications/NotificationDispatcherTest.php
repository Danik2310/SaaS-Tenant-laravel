<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Notifications;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Shared\Constants\NotificationTypes;
use App\Shared\Contracts\NotificationDispatcherInterface;
use App\Shared\Events\NotificationCreated;
use Database\Seeders\TenantUserRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The dispatcher is the only write path for in-app notifications: it resolves
 * recipients from the type catalog, deduplicates repeated events and broadcasts
 * identifiers without breaking the triggering business action.
 */
class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private NotificationDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTestTenant();
        $this->initializeTenant($this->tenant);
        $this->dispatcher = app(NotificationDispatcherInterface::class);
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

    public function test_password_not_set_is_targeted_at_one_user(): void
    {
        $this->seedRoles();
        $passwordless = User::factory()->create(['password' => null]);
        $other = User::factory()->create(['password' => null]);

        $created = $this->dispatcher->dispatch(
            NotificationTypes::PASSWORD_NOT_SET,
            ['body' => 'Add a password to your account.'],
            $passwordless->id,
        );

        $this->assertSame(1, $created);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $passwordless->id,
            'type' => NotificationTypes::PASSWORD_NOT_SET,
            'level' => NotificationTypes::LEVEL_WARNING,
            'body' => 'Add a password to your account.',
        ]);
        $this->assertSame(0, Notification::where('user_id', $other->id)->count());
    }

    public function test_low_stock_is_sent_only_to_inventory_managers(): void
    {
        $this->seedRoles();

        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $unprivileged = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $created = $this->dispatcher->dispatch(NotificationTypes::LOW_STOCK);

        $this->assertSame(1, $created);
        $this->assertTrue(Notification::where('user_id', $manager->id)->exists());
        $this->assertFalse(Notification::where('user_id', $cashier->id)->exists());
        $this->assertFalse(Notification::where('user_id', $unprivileged->id)->exists());
    }

    public function test_dedupe_key_suppresses_repeat_events(): void
    {
        $this->seedRoles();
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $payload = ['subject_id' => 7];

        $this->assertSame(1, $this->dispatcher->dispatch(
            NotificationTypes::LOW_STOCK, $payload, null, 'product:7',
        ));
        $this->assertSame(0, $this->dispatcher->dispatch(
            NotificationTypes::LOW_STOCK, $payload, null, 'product:7',
        ));
        $this->assertSame(1, $this->dispatcher->dispatch(
            NotificationTypes::LOW_STOCK, $payload, null, 'product:8',
        ));
    }

    public function test_broadcast_carries_identifiers_only(): void
    {
        Event::fake([NotificationCreated::class]);
        $this->seedRoles();
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->dispatcher->dispatch(NotificationTypes::LARGE_ORDER, ['subject_id' => 12]);

        Event::assertDispatched(function (NotificationCreated $event) {
            return $event->tenantKey === $this->tenant->id
                && $event->type === NotificationTypes::LARGE_ORDER
                && $event->level === NotificationTypes::LEVEL_CRITICAL
                && $event->broadcastAs() === 'notification.created'
                && array_keys($event->broadcastWith()) === ['id', 'type', 'level'];
        });
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->dispatcher->dispatch('does_not_exist');
    }
}
