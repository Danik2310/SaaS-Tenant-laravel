<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => $this->faker->numberBetween(1000, 50000),
            'method' => $this->faker->randomElement(['cash', 'card', 'transfer', 'check']),
            'reference' => $this->faker->optional()->numerify('REF-########'),
        ];
    }

    /**
     * Create a payment for a specific order.
     */
    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    /**
     * Create a payment by card.
     */
    public function byCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => 'card',
        ]);
    }

    /**
     * Create a payment by transfer.
     */
    public function byTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => 'transfer',
        ]);
    }
}
