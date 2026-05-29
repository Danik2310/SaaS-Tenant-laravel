<?php

namespace Database\Seeders\Tenant;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(int $count): void
    {
        $existing = Product::count();

        if ($existing >= $count) {
            return;
        }

        $needed = $count - $existing;
        $categoryIds = Category::pluck('id')->toArray();

        if (empty($categoryIds)) {
            return;
        }

        $products = [
            ['name' => 'iPhone 15 Pro', 'sku' => 'SKU-001', 'price' => 29999, 'cost' => 22000],
            ['name' => 'Samsung Galaxy S24', 'sku' => 'SKU-002', 'price' => 24999, 'cost' => 18000],
            ['name' => 'MacBook Air M3', 'sku' => 'SKU-003', 'price' => 35999, 'cost' => 28000],
            ['name' => 'Auriculares Sony WH-1000XM5', 'sku' => 'SKU-004', 'price' => 5499, 'cost' => 3800],
            ['name' => 'Camiseta Algodón Premium', 'sku' => 'SKU-005', 'price' => 499, 'cost' => 200],
            ['name' => 'Zapatos Deportivos Nike', 'sku' => 'SKU-006', 'price' => 2499, 'cost' => 1200],
            ['name' => 'Chocolate Artesanal', 'sku' => 'SKU-007', 'price' => 149, 'cost' => 60],
            ['name' => 'Agua Mineral 1L', 'sku' => 'SKU-008', 'price' => 25, 'cost' => 10],
            ['name' => 'Sofá 3 Plazas', 'sku' => 'SKU-009', 'price' => 15999, 'cost' => 9000],
            ['name' => 'Mesa de Centro', 'sku' => 'SKU-010', 'price' => 4999, 'cost' => 2500],
            ['name' => 'Pesa Rusa 20kg', 'sku' => 'SKU-011', 'price' => 1299, 'cost' => 600],
            ['name' => 'Proteína Whey 2kg', 'sku' => 'SKU-012', 'price' => 1899, 'cost' => 900],
            ['name' => 'iPad Air', 'sku' => 'SKU-013', 'price' => 18999, 'cost' => 14000],
            ['name' => 'Teclado Mecánico', 'sku' => 'SKU-014', 'price' => 2499, 'cost' => 1200],
            ['name' => 'Monitor 27" 4K', 'sku' => 'SKU-015', 'price' => 12999, 'cost' => 8500],
            ['name' => 'Vestido Verano', 'sku' => 'SKU-016', 'price' => 1299, 'cost' => 500],
            ['name' => 'Chaqueta Cuero', 'sku' => 'SKU-017', 'price' => 4999, 'cost' => 2500],
            ['name' => 'Café Premium 500g', 'sku' => 'SKU-018', 'price' => 399, 'cost' => 180],
            ['name' => 'Aceite Oliva Extra 1L', 'sku' => 'SKU-019', 'price' => 299, 'cost' => 140],
            ['name' => 'Lámpara LED Escritorio', 'sku' => 'SKU-020', 'price' => 899, 'cost' => 350],
        ];

        $needed = min($needed, count($products));

        for ($i = 0; $i < $needed; $i++) {
            $p = $products[$i];
            Product::updateOrCreate(
                ['sku' => $p['sku']],
                [
                    'name' => $p['name'],
                    'category_id' => $categoryIds[array_rand($categoryIds)],
                    'price' => $p['price'],
                    'cost' => $p['cost'],
                    'description' => 'Descripción de '.$p['name'],
                    'active' => true,
                ],
            );
        }
    }
}
