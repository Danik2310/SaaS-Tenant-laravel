<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference_id' => $this->reference_id,
            'name' => $this->name ?? 'N/A',
            'email' => $this->email ?? 'N/A',
            'domain' => $this->whenLoaded('domains', fn () => $this->domains->first()?->domain ?? 'N/A'),
            'all_domains' => $this->whenLoaded('domains', fn () => $this->domains->map(fn ($d) => [
                'domain' => $d->domain,
                'is_primary' => $d->domain === $this->domains->first()?->domain,
            ])->values()),
            'status' => $this->status,
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name),
            'plan_slug' => $this->whenLoaded('plan', fn () => $this->plan?->slug),
            'subscription_ends_at' => $this->whenLoaded('activeSubscription', fn () => $this->resolveSubscriptionEndsAt($this->activeSubscription)),
            'subscription_plan_name' => $this->whenLoaded('activeSubscription', fn () => $this->activeSubscription?->plan?->name),
            'subscription_duration_months' => $this->whenLoaded('activeSubscription', fn () => $this->activeSubscription?->plan?->duration_months),
            'subscription_starts_at' => $this->whenLoaded('activeSubscription', fn () => $this->activeSubscription?->starts_at?->format('Y-m-d H:i')),
            'has_active_subscription' => $this->whenLoaded('activeSubscription', fn () => $this->activeSubscription !== null),
            'trial_ends_at' => $this->trial_ends_at,
            'is_on_trial' => $this->status === 'Trial' && $this->trial_ends_at !== null && $this->trial_ends_at->isFuture(),
            'trial_has_expired' => $this->status === 'Trial' && $this->trial_ends_at !== null && $this->trial_ends_at->isPast(),
            'deleted_at' => $this->deleted_at,
            'is_deleted' => $this->trashed(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Resolve the subscription end date from the plan's allocated duration.
     *
     * The duration is read live from the plans table (Infrastructure & Plans),
     * so editing a plan's duration reflects immediately. Falls back to the
     * stored ends_at for Trial plans, null durations, or legacy rows.
     */
    private function resolveSubscriptionEndsAt(?Subscription $subscription): ?string
    {
        if ($subscription === null) {
            return null;
        }

        $durationMonths = $subscription->plan?->duration_months;

        if ($durationMonths !== null && $subscription->starts_at !== null) {
            return $subscription->starts_at->addMonths($durationMonths)->format('Y-m-d H:i');
        }

        return $subscription->ends_at?->format('Y-m-d H:i');
    }
}
