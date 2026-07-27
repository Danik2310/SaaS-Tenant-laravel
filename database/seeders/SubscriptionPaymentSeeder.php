<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Database\Seeder;

class SubscriptionPaymentSeeder extends Seeder
{
    private const METHODS = ['stripe', 'bank_transfer', 'cash', 'manual'];

    private const REFERENCE_PREFIXES = [
        'stripe' => 'STRIPE-',
        'bank_transfer' => 'BT-',
        'cash' => 'CASH-',
        'manual' => 'MAN-',
    ];

    private const NOTES = [
        'Monthly subscription payment',
        'Quarterly renewal',
        'Annual plan payment',
        'Upgrade payment',
        'Late payment - 5 day delay',
        'Partial payment - balance pending',
        'Refund processed',
        'Payment via bank wire transfer',
        'Cash received at office',
        'Manual adjustment for billing correction',
        'On-time renewal',
        'Auto-renewal via Stripe',
        'Payment for additional users',
        'Promo code applied - 10% discount',
    ];

    public function run(): void
    {
        $subscriptions = Subscription::with('plan', 'tenant')
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->command?->warn('No active subscriptions found. Skipping payment seeding.');

            return;
        }

        $created = 0;

        foreach ($subscriptions as $subscription) {
            $payments = $this->buildPaymentsForSubscription($subscription);

            foreach ($payments as $payment) {
                SubscriptionPayment::updateOrCreate(
                    [
                        'subscription_id' => $payment['subscription_id'],
                        'paid_at' => $payment['paid_at'],
                        'amount' => $payment['amount'],
                    ],
                    $payment,
                );
                $created++;
            }
        }

        $this->command?->info("Seeded {$created} subscription payments across {$subscriptions->count()} subscriptions.");
    }

    private function buildPaymentsForSubscription(Subscription $subscription): array
    {
        $planPrice = (float) ($subscription->plan?->price ?? 0);

        // Free plans: 1-2 small processing fee payments
        if ($planPrice <= 0) {
            return $this->generateFreePlanPayments($subscription);
        }

        // Paid plans: 1-6 monthly payments with realistic patterns
        return $this->generatePaidPlanPayments($subscription, $planPrice);
    }

    private function generateFreePlanPayments(Subscription $subscription): array
    {
        $count = fake()->numberBetween(0, 2);
        $payments = [];

        for ($i = 0; $i < $count; $i++) {
            $payments[] = $this->makePayment($subscription, 1.00, 'manual', 'completed', now()->subMonths($i));
        }

        return $payments;
    }

    private function generatePaidPlanPayments(Subscription $subscription, float $planPrice): array
    {
        // 1-6 months of history
        $monthCount = fake()->numberBetween(1, 6);
        $payments = [];

        for ($i = 0; $i < $monthCount; $i++) {
            $date = now()->subMonths($i)->startOfMonth()->addDays(fake()->numberBetween(1, 27));
            $method = fake()->randomElement(self::METHODS);

            // 70% completed, 15% pending, 10% failed, 5% refunded
            $status = $this->weightedStatus($i);

            $amount = $planPrice;
            // Occasional discount or extra charge
            if (fake()->boolean(15)) {
                $amount = round($planPrice * fake()->randomFloat(2, 0.8, 1.2), 2);
            }

            $payments[] = $this->makePayment($subscription, $amount, $method, $status, $date);
        }

        return $payments;
    }

    private function makePayment(Subscription $subscription, float $amount, string $method, string $status, $date): array
    {
        $prefix = self::REFERENCE_PREFIXES[$method] ?? 'REF-';

        return [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'amount' => $amount,
            'method' => $method,
            'reference' => $prefix.fake()->uuid(),
            'status' => $status,
            'paid_at' => $date->toDateTimeString(),
            'notes' => fake()->optional(0.6)->randomElement(self::NOTES),
        ];
    }

    private function weightedStatus(int $monthOffset): string
    {
        // Most recent month is more likely to be pending/failed
        if ($monthOffset === 0 && fake()->boolean(25)) {
            return fake()->randomElement(['pending', 'failed']);
        }

        // Older months: mostly completed
        $roll = fake()->numberBetween(1, 100);
        if ($roll <= 70) {
            return 'completed';
        }
        if ($roll <= 85) {
            return 'pending';
        }
        if ($roll <= 95) {
            return 'failed';
        }

        return 'refunded';
    }
}
