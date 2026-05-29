<?php

namespace Database\Seeders\Central;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Stripe Test',
                'provider' => 'stripe',
                'api_key' => 'sk_test_placeholder_stripe',
                'secret_key' => 'pk_test_placeholder_stripe',
                'mode' => 'test',
                'active' => true,
            ],
            [
                'name' => 'Stripe Live',
                'provider' => 'stripe',
                'api_key' => 'sk_live_placeholder_stripe',
                'secret_key' => 'pk_live_placeholder_stripe',
                'mode' => 'live',
                'active' => false,
            ],
            [
                'name' => 'PayPal Test',
                'provider' => 'paypal',
                'api_key' => 'sb_test_placeholder_paypal',
                'secret_key' => 'sb_test_secret_placeholder_paypal',
                'mode' => 'test',
                'active' => true,
            ],
            [
                'name' => 'Transferencia Bancaria',
                'provider' => 'other',
                'api_key' => null,
                'secret_key' => null,
                'mode' => 'test',
                'active' => true,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['name' => $method['name']],
                $method,
            );
        }
    }
}
