<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? 'N/A',
            'email' => $this->email ?? 'N/A',
            'domain' => $this->domains->first()?->domain ?? 'N/A',
            'status' => $this->status,
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
