<?php

namespace Database\Seeders\Tenant;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(int $count): void
    {
        $existing = Order::count();

        if ($existing >= $count) {
            return;
        }

        $customers = Customer::all();
        $products = Product::all();

        if ($customers->isEmpty() || $products->isEmpty()) {
            return;
        }

        $needed = $count - $existing;

        for ($i = 0; $i < $needed; $i++) {
            $customer = $customers->random();
            $status = $this->randomWeightedStatus();

            $order = Order::create([
                'customer_id' => $customer->id,
                'status' => $status,
                'total' => 0,
            ]);

            $itemCount = rand(1, 5);
            $total = 0;

            $selectedProducts = $products->count() >= $itemCount
                ? $products->random($itemCount)
                : $products;

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 5);
                $price = $product->price;
                $subtotal = round($price * $quantity, 2);
                $total += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);
            }

            $order->update(['total' => $total]);

            if ($status === 'paid') {
                Payment::create([
                    'order_id' => $order->id,
                    'amount' => $total,
                    'method' => collect(['cash', 'card', 'transfer'])->random(),
                    'reference' => 'PAY-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                ]);
            }
        }
    }

    private function randomWeightedStatus(): string
    {
        $rand = rand(1, 100);

        if ($rand <= 50) {
            return 'paid';
        }
        if ($rand <= 75) {
            return 'pending';
        }

        return 'cancelled';
    }
}
