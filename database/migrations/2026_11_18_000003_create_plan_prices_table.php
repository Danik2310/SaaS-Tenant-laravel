<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_prices')) {
            Schema::create('plan_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
                $table->char('currency_code', 3);
                $table->decimal('amount', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['plan_id', 'currency_code']);
                $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnUpdate()->restrictOnDelete();
                $table->index(['currency_code', 'amount']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
