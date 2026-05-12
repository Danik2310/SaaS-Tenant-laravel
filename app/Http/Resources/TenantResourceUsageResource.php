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
            ]),
            'users_count' => $this->users_count,
            'products_count' => $this->products_count,
            'orders_count' => $this->orders_count,
            'storage_kb' => (int) $this->storage_kb,
            'db_size_kb' => (int) $this->db_size_kb,
            'collected_at' => $this->collected_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
