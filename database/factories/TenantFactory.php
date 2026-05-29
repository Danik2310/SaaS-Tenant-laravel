<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'id' => 'test-'.$this->faker->unique()->slug(2),
            'reference_id' => 'TEN-'.now()->format('Ymd').'-'.$this->faker->unique()->numberBetween(1, 9999),
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->companyEmail(),
            'status' => 'Active',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            if (! $tenant->domains()->exists()) {
                $tenant->domains()->create([
                    'domain' => $tenant->id.'.localhost',
                ]);
            }
        });
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Suspended',
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Deleted',
        ]);
    }
}
