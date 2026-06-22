<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_features')) {
            Schema::create('plan_features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
                $table->string('feature_key', 100);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->unique(['plan_id', 'feature_key']);
                $table->index('feature_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
