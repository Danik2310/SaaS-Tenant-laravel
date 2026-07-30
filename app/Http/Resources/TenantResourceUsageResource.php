<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResourceUsageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'email' => $this->tenant->email,
                'status' => $this->tenant->status,
                'plan_name' => $this->tenant->relationLoaded('plan') ? $this->tenant->plan?->name : null,
                'plan_slug' => $this->tenant->relationLoaded('plan') ? $this->tenant->plan?->slug : null,
                'subscription_status' => $this->tenant->relationLoaded('activeSubscription') ? $this->tenant->activeSubscription?->status : null,
                'is_on_trial' => $this->tenant->isOnTrial(),
            ]),
            'limits' => $this->whenLoaded('tenant', function () {
                $plan = $this->tenant->relationLoaded('plan') ? $this->tenant->plan : null;

                return [
                    'max_users' => $plan?->max_users,
                    'max_storage' => $plan?->max_storage,
                    'max_products' => $plan?->max_products,
                    'max_warehouses' => $plan?->max_warehouses,
                    'max_categories' => $plan?->max_categories,
                ];
            }),
            'users_count' => $this->users_count,
            'products_count' => $this->products_count,
            'orders_count' => $this->orders_count,
            'warehouses_count' => $this->warehouses_count ?? 0,
            'categories_count' => $this->categories_count ?? 0,
            'storage_kb' => (int) $this->storage_kb,
            'db_size_kb' => (int) $this->db_size_kb,
            'collected_at' => $this->collected_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
