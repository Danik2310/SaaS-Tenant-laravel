<?php

namespace Tests\Feature;

use App\Models\GlobalSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Shared\Contracts\ExchangeRateServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\AdminAuthSetup;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use AdminAuthSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminAuth();
        $this->grantManageSettings();
        DB::connection('mysql_central')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('mysql_central')->rollBack();
        parent::tearDown();
    }

    private function grantManageSettings(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'manage settings',
            'guard_name' => 'admin',
        ]);
        Role::findByName('super-admin', 'admin')->givePermissionTo($permission);
    }

    private function fakeLiveApi(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'time_last_update_unix' => 1780500000,
                'rates' => [
                    'USD' => 1,
                    'EUR' => 0.9,
                    'MXN' => 18.5,
                    'JPY' => 150,
                    'ARS' => 950,
                ],
            ], 200),
        ]);
    }

    /**
     * 🧪 Test: Exchange rates endpoint returns live API data.
     */
    public function test_exchange_rates_endpoint_returns_live_data(): void
    {
        $this->fakeLiveApi();

        $this->getJson('/admin/api/exchange-rates')
            ->assertStatus(200)
            ->assertJsonPath('base', 'USD')
            ->assertJsonPath('display_currency', 'USD')
            ->assertJsonPath('is_live', true)
            ->assertJsonPath('updated_at', date('c', 1780500000))
            ->assertJsonPath('rates.EUR', 0.9)
            ->assertJsonStructure([
                'currencies' => [
                    'EUR' => ['name', 'symbol'],
                ],
            ]);
    }

    /**
     * 🧪 Test: Exchange rates fall back to the offline table when the API is down.
     */
    public function test_exchange_rates_fall_back_when_api_unavailable(): void
    {
        Http::fake(['open.er-api.com/*' => Http::response([], 500)]);

        $this->getJson('/admin/api/exchange-rates')
            ->assertStatus(200)
            ->assertJsonPath('is_live', false)
            ->assertJsonPath('updated_at', null)
            ->assertJsonPath('rates.EUR', config('currency.fallback_rates.EUR'))
            ->assertJsonPath('rates.USD', 1);
    }

    /**
     * 🧪 Test: Display currency follows the global currency setting.
     */
    public function test_display_currency_reads_global_setting(): void
    {
        $this->fakeLiveApi();
        GlobalSetting::set('currency', 'MXN');

        $this->getJson('/admin/api/exchange-rates')
            ->assertStatus(200)
            ->assertJsonPath('display_currency', 'MXN');
    }

    /**
     * 🧪 Test: Convert uses brick/money with proper rounding.
     */
    public function test_convert_uses_brick_money_with_rounding(): void
    {
        $this->fakeLiveApi();
        $service = app(ExchangeRateServiceInterface::class);

        $this->assertSame('€90.00', $service->convert(100, 'EUR')->formatTo('en_US'));
        $this->assertSame('¥15,000', $service->convert(100, 'JPY')->formatTo('en_US'));
        $this->assertSame('MX$1,850.00', $service->convert(100, 'MXN')->formatTo('en_US'));
    }

    /**
     * 🧪 Test: Settings update accepts the currency and the previously rejected keys.
     */
    public function test_settings_update_accepts_currency_and_ui_keys(): void
    {
        $this->putJson('/admin/api/settings', [
            'settings' => [
                ['key' => 'currency', 'value' => 'EUR'],
                ['key' => 'app_description', 'value' => 'SaaS platform'],
                ['key' => 'tenant_db_prefix', 'value' => 'tenant_'],
                ['key' => 'allow_registration', 'value' => 'true'],
                ['key' => 'default_plan_id', 'value' => '1'],
            ],
        ])->assertStatus(200);

        $this->assertSame('EUR', GlobalSetting::get('currency'));
        $this->assertSame('SaaS platform', GlobalSetting::get('app_description'));
        $this->assertSame('tenant_', GlobalSetting::get('tenant_db_prefix'));
        $this->assertSame('true', GlobalSetting::get('allow_registration'));
        $this->assertSame('1', GlobalSetting::get('default_plan_id'));
    }

    /**
     * 🧪 Test: Settings update rejects an unsupported currency.
     */
    public function test_settings_update_rejects_invalid_currency(): void
    {
        $this->putJson('/admin/api/settings', [
            'settings' => [
                ['key' => 'currency', 'value' => 'XYZ'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);

        $this->assertSame('USD', GlobalSetting::get('currency'));
    }

    /**
     * 🧪 Test: Settings update still rejects keys outside the allowed list.
     */
    public function test_settings_update_rejects_disallowed_key(): void
    {
        $this->putJson('/admin/api/settings', [
            'settings' => [
                ['key' => 'hack_me', 'value' => '1'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.key']);
    }

    /**
     * 🧪 Test: Admins without 'manage settings' cannot access exchange rates.
     */
    public function test_unauthorized_cannot_access_exchange_rates(): void
    {
        Role::findByName('super-admin', 'admin')->revokePermissionTo('manage settings');

        $this->getJson('/admin/api/exchange-rates')->assertStatus(403);
    }

    /**
     * 🧪 Test: Guests are redirected to login.
     */
    public function test_guest_cannot_access_exchange_rates(): void
    {
        auth('admin')->logout();

        $this->getJson('/admin/api/exchange-rates')->assertStatus(401);
    }
}
