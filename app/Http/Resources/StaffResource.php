<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray($request): array
    {
        $roleNames = $this->roles->pluck('name')->toArray();
        $directPerms = $this->permissions;
        $rolePerms = $this->roles->flatMap(fn($role) => $role->permissions);
        $allPerms = $directPerms->merge($rolePerms)->unique('id');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $roleNames,
            'permissions_count' => $allPerms->count(),
            'permissions' => $allPerms->pluck('name')->toArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
