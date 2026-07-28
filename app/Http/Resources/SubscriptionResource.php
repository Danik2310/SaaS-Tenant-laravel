<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canManageTenants = auth('admin')->user()?->can('manage tenants') ?? false;
        $tenantStatus = 'restricted';
        $tenantName = 'Restricted';

        if ($canManageTenants && $this->relationLoaded('tenant')) {
            if ($this->tenant === null) {
                $tenantStatus = 'missing';
                $tenantName = 'Missing Tenant';
            } elseif ($this->tenant->trashed()) {
                $tenantStatus = 'deleted';
                $tenantName = $this->tenant->name;
            } else {
                $tenantStatus = 'active';
                $tenantName = $this->tenant->name;
            }
        }

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'tenant_name' => $tenantName,
            'tenant_status' => $tenantStatus,
            'tenant_actual_status' => $this->relationLoaded('tenant') ? ($this->tenant?->status ?? null) : null,
            'plan_name' => $this->relationLoaded('plan') ? ($this->plan?->name ?? 'Unknown') : 'Unknown',
            'plan_slug' => $this->relationLoaded('plan') ? ($this->plan?->slug ?? '') : '',
            'plan_price' => $this->relationLoaded('plan') ? ($this->plan?->price ?? '0.00') : '0.00',
            'plan_duration_months' => $this->relationLoaded('plan') ? ($this->plan?->duration_months ?? null) : null,
            'starts_at' => $this->starts_at?->format('Y-m-d'),
            'ends_at' => $this->ends_at?->format('Y-m-d'),
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
