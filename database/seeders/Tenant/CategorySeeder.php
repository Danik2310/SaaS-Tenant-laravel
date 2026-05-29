<?php

namespace Database\Seeders\Tenant;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(int $count): void
    {
        $existing = Category::count();

        if ($existing >= $count) {
            return;
        }

        $categories = [
            ['name' => 'Electrónicos', 'slug' => 'electronicos', 'parent_id' => null],
            ['name' => 'Ropa y Accesorios', 'slug' => 'ropa-accesorios', 'parent_id' => null],
            ['name' => 'Alimentos y Bebidas', 'slug' => 'alimentos-bebidas', 'parent_id' => null],
            ['name' => 'Hogar y Jardín', 'slug' => 'hogar-jardin', 'parent_id' => null],
            ['name' => 'Deportes', 'slug' => 'deportes', 'parent_id' => null],
            ['name' => 'Celulares', 'slug' => 'celulares', 'parent_id' => null],
            ['name' => 'Laptops', 'slug' => 'laptops', 'parent_id' => null],
            ['name' => 'Audífonos', 'slug' => 'audifonos', 'parent_id' => null],
            ['name' => 'Camisetas', 'slug' => 'camisetas', 'parent_id' => null],
            ['name' => 'Zapatos', 'slug' => 'zapatos', 'parent_id' => null],
            ['name' => 'Snacks', 'slug' => 'snacks', 'parent_id' => null],
            ['name' => 'Bebidas', 'slug' => 'bebidas', 'parent_id' => null],
            ['name' => 'Muebles', 'slug' => 'muebles', 'parent_id' => null],
            ['name' => 'Equipo de Fitness', 'slug' => 'equipo-fitness', 'parent_id' => null],
            ['name' => 'Suplementos', 'slug' => 'suplementos', 'parent_id' => null],
        ];

        $needed = min($count - $existing, count($categories));

        for ($i = 0; $i < $needed; $i++) {
            Category::updateOrCreate(
                ['slug' => $categories[$i]['slug']],
                $categories[$i],
            );
        }

        if ($needed < ($count - $existing)) {
            Category::factory()->count($count - $existing - $needed)->create();
        }
    }
}
