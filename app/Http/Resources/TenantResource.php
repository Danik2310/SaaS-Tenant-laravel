<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        $domains = $this->domains ?? collect();

        return [
            'id' => $this->id,
            'name' => $this->name ?? 'N/A',
            'email' => $this->email ?? 'N/A',
            'domain' => $domains->first()?->domain ?? 'N/A',
            'all_domains' => $domains->map(fn ($d) => [
                'domain' => $d->domain,
                'is_primary' => $d->domain === $domains->first()?->domain,
            ])->values(),
            'status' => $this->status,
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name),
            'plan_slug' => $this->whenLoaded('plan', fn () => $this->plan?->slug),
            'trial_ends_at' => $this->trial_ends_at,
            'is_on_trial' => $this->isOnTrial(),
            'trial_has_expired' => $this->trialHasExpired(),
            'deleted_at' => $this->deleted_at,
            'is_deleted' => $this->trashed(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
