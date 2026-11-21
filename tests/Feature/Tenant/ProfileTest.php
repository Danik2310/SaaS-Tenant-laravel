<?php

namespace Tests\Feature\Tenant;

use App\Models\OAuthAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Shared\Constants\OAuthProvider;
use App\Shared\Support\OAuthConnectResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
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

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_settings_hub_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tenant/Settings/Index'));
    }

    public function test_security_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings/security')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Settings/Security')
                ->has('connectedAccounts', 0)
                ->where('hasPassword', true)
                ->missing('password'));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_page_exposes_only_safe_connected_account_fields(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);

        OAuthAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GOOGLE,
            'provider_user_id' => 'google-123',
            'provider_email' => 'member-google@example.com',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->has('connectedAccounts', 1)
                ->where('connectedAccounts.0.label', 'Google')
                ->where('connectedAccounts.0.email', 'member-google@example.com')
                ->missing('connectedAccounts.0.provider_user_id')
                ->where('hasPassword', true)
                ->missing('password'));
    }

    public function test_google_account_can_be_unlinked_without_ending_the_session(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);

        $account = OAuthAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GOOGLE,
            'provider_user_id' => 'google-123',
            'provider_email' => 'member-google@example.com',
        ]);

        $this->actingAs($user)
            ->from('/settings/security')
            ->delete('/settings/security/connected-accounts/google')
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/security');

        $this->assertNotNull(
            DB::connection('tenant')->table('oauth_accounts')->where('id', $account->id)->value('unlinked_at')
        );
        $this->assertSame(0, $user->fresh()->connectedAccounts()->count());
        $this->assertAuthenticatedAs($user);

        // The tombstone stays, so a second unlink is a no-op rather than a
        // fresh deletion that could be replayed.
        $this->actingAs($user)
            ->delete('/settings/security/connected-accounts/google')
            ->assertNotFound();
    }

    public function test_passwordless_user_cannot_unlink_google(): void
    {
        $user = User::factory()->create(['email' => 'google-only@example.com', 'password' => null]);

        $account = OAuthAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GOOGLE,
            'provider_user_id' => 'google-456',
            'provider_email' => 'google-only@example.com',
        ]);

        $this->actingAs($user)
            ->delete('/settings/security/connected-accounts/google')
            ->assertForbidden();

        $this->assertNull(
            DB::connection('tenant')->table('oauth_accounts')->where('id', $account->id)->value('unlinked_at')
        );
    }

    public function test_user_cannot_unlink_another_users_google_account(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);

        $account = OAuthAccount::query()->create([
            'user_id' => $owner->id,
            'provider' => OAuthProvider::GOOGLE,
            'provider_user_id' => 'google-789',
            'provider_email' => 'owner-google@example.com',
        ]);

        $this->actingAs($other)
            ->delete('/settings/security/connected-accounts/google')
            ->assertNotFound();

        $this->assertNull(
            DB::connection('tenant')->table('oauth_accounts')->where('id', $account->id)->value('unlinked_at')
        );
    }

    public function test_google_connect_returns_a_signed_central_url(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->from('/settings/security')
            ->post('/settings/security/connected-accounts/google/connect');

        $response->assertStatus(409);

        $location = (string) $response->headers->get('X-Inertia-Location');
        $this->assertStringContainsString('/auth/google/connect', $location);
        $this->assertStringContainsString('signature=', $location);
        $this->assertStringContainsString('tenant='.$this->tenant->id, $location);
        $this->assertStringContainsString('user='.$user->id, $location);
        $this->assertAuthenticatedAs($user);
    }

    public function test_passwordless_user_cannot_connect_google(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->post('/settings/security/connected-accounts/google/connect')
            ->assertForbidden();
    }

    public function test_signed_connect_result_sets_status_or_error_on_the_security_page(): void
    {
        $user = User::factory()->create();

        $success = URL::temporarySignedRoute('tenant.settings.connected-accounts.connect-result', now()->addMinute(), [
            'user' => $user->id,
            'code' => OAuthConnectResult::CONNECTED,
        ]);

        $this->actingAs($user)
            ->get($success)
            ->assertRedirect('/settings/security')
            ->assertSessionHas('status', 'Google account connected.');

        $failure = URL::temporarySignedRoute('tenant.settings.connected-accounts.connect-result', now()->addMinute(), [
            'user' => $user->id,
            'code' => OAuthConnectResult::ALREADY_LINKED,
        ]);

        $this->actingAs($user)
            ->get($failure)
            ->assertRedirect('/settings/security')
            ->assertSessionHas('error', 'That Google account is already connected to a different user.');
    }

    public function test_changing_email_keeps_the_google_link_and_clears_verification(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        OAuthAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GOOGLE,
            'provider_user_id' => 'google-123',
            'provider_email' => 'google-old@example.com',
        ]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'new@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('oauth_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::GOOGLE,
            'provider_user_id' => 'google-123',
            'provider_email' => 'google-old@example.com',
        ], 'tenant');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        // Account deletion is a soft delete: the row persists (so its email can
        // be released and the account restored) but is hidden from live queries.
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
