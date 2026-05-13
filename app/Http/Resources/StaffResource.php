<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray($request): array
    {
        $roleNames = $this->roles->pluck('name')->toArray();
        $rolePerms = $this->roles->flatMap(fn ($role) => $role->permissions)->unique('id');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $roleNames,
            'permissions_count' => $rolePerms->count(),
            'permissions' => $rolePerms->pluck('name')->toArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
