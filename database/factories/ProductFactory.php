<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\VatCode;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'name' => fake()->words(3, true),
            'type' => ProductType::Service,
            'unit' => 'each',
            'unit_price_cents' => 10000,
            'price_includes_vat' => false,
            'vat_code' => VatCode::Standard,
            'is_active' => true,
        ];
    }
}
