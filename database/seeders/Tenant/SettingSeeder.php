<?php

namespace Database\Seeders\Tenant;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'locale', 'value' => 'es'],
            ['key' => 'currency', 'value' => 'USD'],
            ['key' => 'timezone', 'value' => 'America/Mexico_City'],
            ['key' => 'invoice_prefix', 'value' => 'INV-'],
            ['key' => 'business_name', 'value' => 'Mi Empresa'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']],
            );
        }
    }
}
