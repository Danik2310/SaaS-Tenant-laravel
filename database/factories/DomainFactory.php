<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Stancl\Tenancy\Database\Models\Domain;

class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'domain' => $this->faker->unique()->domainName(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
