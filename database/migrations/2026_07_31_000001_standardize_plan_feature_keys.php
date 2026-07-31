<?php

use App\Plans\Support\PlanFeatureNormalizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Normalize existing plan_features rows to the canonical feature flag registry.
     */
    public function up(): void
    {
        PlanFeatureNormalizer::normalize();
    }

    public function down(): void
    {
        // Data normalization is not reversed automatically.
    }
};
