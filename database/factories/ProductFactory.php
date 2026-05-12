<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = $this->faker->numberBetween(100, 1000);
        $price = $cost * $this->faker->randomFloat(2, 1.2, 2.5);

        return [
            'name' => $this->faker->word().' '.$this->faker->word(),
            'description' => $this->faker->optional()->sentence(),
            'sku' => $this->faker->unique()->numerify('SKU-####-##'),
            'category_id' => Category::factory(),
            'price' => $price,
            'cost' => $cost,
            'active' => true,
        ];
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Create a product with specific category.
     */
    public function forCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }
}
