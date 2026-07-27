<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'tenant_id' => $this->tenant_id,
            'amount' => $this->amount,
            'method' => $this->method,
            'reference' => $this->reference,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->format('Y-m-d H:i'),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
