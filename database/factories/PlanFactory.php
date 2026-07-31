<?php

namespace Database\Factories;

use App\Models\Plan;
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
            'status' => 'active',
            'price' => $this->faker->randomFloat(2, 0, 100),
            'duration_months' => $this->faker->numberBetween(1, 12),
            'max_users' => $this->faker->numberBetween(1, 100),
        ];
    }
}
