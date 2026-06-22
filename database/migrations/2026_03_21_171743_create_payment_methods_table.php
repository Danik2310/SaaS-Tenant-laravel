<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // WARNING: api_key and secret_key contain sensitive payment gateway credentials.
        // These columns are encrypted in production by migration
        // 2026_05_26_230926_encrypt_existing_payment_method_keys.php.
        // Any new code inserting into this table must encrypt these values using Crypt::encryptString().
        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('provider', ['stripe', 'paypal', 'other']);
                $table->text('api_key')->nullable();
                $table->text('secret_key')->nullable();
                $table->enum('mode', ['test', 'live'])->default('test');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
