<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company.' Payment',
            'provider' => $this->faker->randomElement(['stripe', 'paypal', 'other']),
            'api_key' => $this->faker->password(20, 20), // Long enough
            'secret_key' => $this->faker->password(20, 20),
            'mode' => $this->faker->randomElement(['test', 'live']),
            'active' => $this->faker->boolean,
        ];
    }
}
