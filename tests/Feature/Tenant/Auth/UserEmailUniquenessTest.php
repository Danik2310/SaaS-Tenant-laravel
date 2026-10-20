<?php

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserEmailUniquenessTest extends TestCase
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

    public function test_registration_normalizes_email_to_lowercase(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => '  MixedCase@Example.COM ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'mixedcase@example.com']);
    }

    public function test_a_live_email_cannot_be_reused(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_soft_deleted_users_email_can_be_reused(): void
    {
        $user = User::factory()->create(['email' => 'reuse@example.com']);
        $user->delete();

        $this->assertSoftDeleted('users', ['email' => 'reuse@example.com']);

        $this->post('/register', [
            'name' => 'New Owner',
            'email' => 'reuse@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertSame(2, User::withTrashed()->where('email', 'reuse@example.com')->count());
        $this->assertSame(1, User::where('email', 'reuse@example.com')->count());
    }

    public function test_profile_update_ignores_a_soft_deleted_duplicate(): void
    {
        $mine = User::factory()->create(['email' => 'mine@example.com']);
        $gone = User::factory()->create(['email' => 'gone@example.com']);
        $gone->delete();

        $response = $this->actingAs($mine)->patch('/profile', [
            'name' => $mine->name,
            'email' => 'gone@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $mine->id, 'email' => 'gone@example.com']);
    }
}
