<?php

namespace Database\Seeders\Central;

use App\Models\GlobalSetting;
use Illuminate\Database\Seeder;

class GlobalSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'app_name', 'value' => 'SaaS App'],
            ['key' => 'app_description', 'value' => 'Multi-tenant SaaS application built with Laravel and React'],
            ['key' => 'support_email', 'value' => 'support@saas-app.com'],
            ['key' => 'currency', 'value' => 'USD'],
            ['key' => 'tenant_db_prefix', 'value' => 'tenant_'],
            ['key' => 'allow_registration', 'value' => 'true'],
            ['key' => 'maintenance_mode', 'value' => 'false'],
            ['key' => 'default_plan_id', 'value' => '1'],
        ];

        foreach ($settings as $setting) {
            GlobalSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']],
            );
        }
    }
}
