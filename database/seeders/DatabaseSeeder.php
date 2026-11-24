<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\VatCode;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['sku' => 'LAB-IT', 'name' => 'IT support', 'type' => ProductType::Labour, 'unit' => 'hour', 'unit_price_cents' => 65000],
            ['sku' => 'LAB-WEB', 'name' => 'Web development', 'type' => ProductType::Labour, 'unit' => 'hour', 'unit_price_cents' => 85000],
            ['sku' => 'LAB-WA', 'name' => 'WhatsApp development', 'type' => ProductType::Labour, 'unit' => 'hour', 'unit_price_cents' => 85000],
            ['sku' => 'LAB-SW', 'name' => 'Software development', 'type' => ProductType::Labour, 'unit' => 'hour', 'unit_price_cents' => 95000],
            ['sku' => 'LAB-NET', 'name' => 'Networking', 'type' => ProductType::Service, 'unit' => 'hour', 'unit_price_cents' => 75000],
            ['sku' => 'SUB-VPS', 'name' => 'Cloud / VPS hosting', 'type' => ProductType::Subscription, 'unit' => 'month', 'unit_price_cents' => 45000],
            ['sku' => 'LAB-CCTV', 'name' => 'CCTV installation labour', 'type' => ProductType::Labour, 'unit' => 'hour', 'unit_price_cents' => 65000],
            ['sku' => 'LAB-TEL', 'name' => 'Telephone support', 'type' => ProductType::Service, 'unit' => 'hour', 'unit_price_cents' => 55000],
            ['sku' => 'PRT-PAGE', 'name' => 'Printing', 'type' => ProductType::Print, 'unit' => 'page', 'unit_price_cents' => 250],
            ['sku' => 'HW-CAM-4MP', 'name' => '4MP IP camera', 'type' => ProductType::Stock, 'unit' => 'each', 'unit_price_cents' => 145000],
        ];

        foreach ($items as $item) {
            Product::query()->firstOrCreate(['sku' => $item['sku']], $item + [
                'price_includes_vat' => false,
                'vat_code' => VatCode::Standard,
                'is_active' => true,
            ]);
        }
    }
}
