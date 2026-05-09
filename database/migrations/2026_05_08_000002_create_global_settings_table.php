<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('global_settings')->insert([
            ['key' => 'app_name', 'value' => 'SaaS Admin', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_description', 'value' => 'Multi-tenant SaaS Management Console', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'support_email', 'value' => 'support@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'allow_registration', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'maintenance_mode', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'default_plan_id', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'tenant_db_prefix', 'value' => 'tenant', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'currency', 'value' => 'USD', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('global_settings');
    }
};
