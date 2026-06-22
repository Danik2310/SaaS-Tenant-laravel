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

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/central/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertAuthenticated('admin');
    }

    public function test_admin_login_fails_with_invalid_credentials(): void
    {
        AdminUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/central/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid credentials']);

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

        $response = $this->post('/central/logout');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Logged out']);
        $this->assertGuest('admin');
    }
}
