<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $this->price,
            'max_users' => $this->max_users,
            'max_warehouses' => $this->max_warehouses,
            'max_categories' => $this->max_categories,
            'max_products' => $this->max_products,
            'features' => $this->features,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
