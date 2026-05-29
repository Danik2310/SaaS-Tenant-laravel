<?php

namespace Database\Seeders\Tenant;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(int $count): void
    {
        $existing = Warehouse::count();

        if ($existing >= $count) {
            return;
        }

        $warehouses = [
            ['name' => 'Almacén Principal', 'location' => 'Ciudad de México'],
            ['name' => 'Almacén Secundario', 'location' => 'Guadalajara'],
            ['name' => 'Almacén Norte', 'location' => 'Monterrey'],
        ];

        $needed = min($count - $existing, count($warehouses));

        for ($i = 0; $i < $needed; $i++) {
            Warehouse::updateOrCreate(
                ['name' => $warehouses[$i]['name']],
                $warehouses[$i],
            );
        }

        if ($needed < ($count - $existing)) {
            Warehouse::factory()->count($count - $existing - $needed)->create();
        }
    }
}
