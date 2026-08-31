<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_returns_200(): void
    {
        $response = $this->get('/central/login');

        $response->assertStatus(200);
    }

    public function test_user_probe_returns_null_user_when_guest(): void
    {
        $response = $this->getJson('/admin/user');

        $response->assertOk()
            ->assertExactJson(['user' => null]);
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/central/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertCookie('token');

        $this->assertAuthenticated('admin');
    }

    public function test_admin_login_fails_with_invalid_credentials(): void
    {
        AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/central/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest('admin');
    }

    public function test_blocked_admin_receives_distinct_blocked_message_on_login(): void
    {
        AdminUser::factory()->inactive()->create([
            'email' => 'blocked@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/central/login', [
            'email' => 'blocked@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonFragment([
                'email' => ['Your account has been blocked. Please contact the system administrator for assistance.'],
            ]);

        $this->assertGuest('admin');
    }

    public function test_unknown_email_does_not_reveal_blocked_message(): void
    {
        $response = $this->postJson('/central/login', [
            'email' => 'does-not-exist@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissing([
                'email' => ['Your account has been blocked. Please contact the system administrator for assistance.'],
            ]);

        $this->assertGuest('admin');
    }

    public function test_guest_cannot_access_admin_routes(): void
    {
        $response = $this->getJson('/admin/api/tenants');

        $response->assertStatus(401);
    }

    public function test_admin_can_logout(): void
    {
        $admin = AdminUser::factory()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->postJson('/central/logout');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Logged out']);
        $this->assertGuest('admin');
    }
}
