<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canManageTenants = auth('admin')->user()?->can('manage tenants') ?? false;

        if ($canManageTenants) {
            if ($this->tenant === null) {
                $tenantStatus = 'missing';
                $tenantName = 'Missing Tenant';
            } elseif ($this->tenant->trashed()) {
                $tenantStatus = 'deleted';
                $tenantName = 'Deleted Tenant';
            } else {
                $tenantStatus = 'active';
                $tenantName = $this->tenant->name;
            }
        } else {
            $tenantStatus = 'restricted';
            $tenantName = 'Restricted';
        }

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'tenant_name' => $tenantName,
            'tenant_status' => $tenantStatus,
            'plan_name' => $this->plan?->name ?? 'Unknown',
            'plan_slug' => $this->plan?->slug ?? '',
            'starts_at' => $this->starts_at?->format('Y-m-d'),
            'ends_at' => $this->ends_at?->format('Y-m-d'),
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
