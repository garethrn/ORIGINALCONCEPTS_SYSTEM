<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\VatCode;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'sku', 'name', 'description', 'type', 'unit', 'unit_price_cents',
    'price_includes_vat', 'vat_code', 'is_active', 'track_stock', 'quantity_on_hand',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'vat_code' => VatCode::class,
            'price_includes_vat' => 'boolean',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
            'quantity_on_hand' => 'decimal:3',
        ];
    }
}
