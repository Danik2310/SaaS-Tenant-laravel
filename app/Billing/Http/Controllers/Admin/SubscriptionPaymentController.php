<?php

namespace App\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionPaymentRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPaymentRequest;
use App\Http\Resources\SubscriptionPaymentResource;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

class SubscriptionPaymentController extends Controller
{
    public function index(Request $request, string $subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);

        $payments = SubscriptionPayment::where('subscription_id', $subscription->id)
            ->orderBy('paid_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'payments' => SubscriptionPaymentResource::collection($payments),
            'subscription' => [
                'id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'tenant_name' => $subscription->tenant?->name ?? 'Unknown',
                'plan_name' => $subscription->plan?->name ?? 'Unknown',
                'plan_price' => $subscription->plan?->price ?? '0.00',
            ],
        ]);
    }

    public function store(StoreSubscriptionPaymentRequest $request, string $subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);

        $payment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'amount' => $request->validated('amount'),
            'method' => $request->validated('method'),
            'reference' => $request->validated('reference'),
            'status' => $request->validated('status'),
            'paid_at' => $request->validated('paid_at'),
            'notes' => $request->validated('notes'),
        ]);

        return response()->json([
            'message' => 'Payment recorded successfully',
            'payment' => new SubscriptionPaymentResource($payment),
        ], 201);
    }

    public function update(UpdateSubscriptionPaymentRequest $request, string $subscriptionId, string $paymentId)
    {
        $payment = SubscriptionPayment::where('subscription_id', $subscriptionId)
            ->findOrFail($paymentId);

        $payment->update($request->validated());

        return response()->json([
            'message' => 'Payment updated successfully',
            'payment' => new SubscriptionPaymentResource($payment),
        ]);
    }
}
