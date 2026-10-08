<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => strtoupper($this->faker->unique()->bothify('??-####')),
            'name' => ucfirst($this->faker->words(3, true)),
            'price_pence' => $this->faker->numberBetween(5, 500) * 100 - 1,
            'compare_at_pence' => null,
        ];
    }
}
