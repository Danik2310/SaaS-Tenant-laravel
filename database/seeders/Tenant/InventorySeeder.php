<?php

namespace Database\Seeders\Tenant;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        $warehouses = Warehouse::all();

        if ($products->isEmpty() || $warehouses->isEmpty()) {
            return;
        }

        $existingMovements = InventoryMovement::count();

        if ($existingMovements > 0) {
            return;
        }

        $batch = [];
        $now = now();

        foreach ($products as $product) {
            $warehouse = $warehouses->random();
            $batch[] = [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'in',
                'quantity' => rand(10, 100),
                'reason' => 'Initial stock',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        InventoryMovement::insert($batch);
    }
}
