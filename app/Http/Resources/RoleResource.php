<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'permissions_count' => $this->whenLoaded('permissions', fn () => $this->permissions->count(), 0),
            'permission_ids' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('id'), []),
        ];
    }
}
