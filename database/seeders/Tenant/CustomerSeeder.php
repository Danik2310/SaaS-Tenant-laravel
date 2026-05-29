<?php

namespace Database\Seeders\Tenant;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(int $count): void
    {
        $existing = Customer::count();

        if ($existing >= $count) {
            return;
        }

        Customer::factory()->count($count - $existing)->create();
    }
}
