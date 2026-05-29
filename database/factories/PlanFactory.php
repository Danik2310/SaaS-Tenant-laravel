<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->slug,
            'price' => $this->faker->randomFloat(2, 0, 100),
            'max_users' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Plan $plan) {
            foreach ($this->faker->words(3) as $featureKey) {
                PlanFeature::firstOrCreate([
                    'plan_id' => $plan->id,
                    'feature_key' => $featureKey,
                ], [
                    'is_enabled' => true,
                ]);
            }
        });
    }
}
