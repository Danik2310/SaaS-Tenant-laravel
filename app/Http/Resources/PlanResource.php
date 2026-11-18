<?php

namespace App\Http\Resources;

use App\Shared\Contracts\CurrencySettingsInterface;
use App\Shared\Contracts\PlanPriceResolverInterface;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'price' => $this->price,
            'base_currency' => config('currency.base_currency'),
            'prices' => $this->whenLoaded('prices'),
            'price_display' => $this->when(
                $this->relationLoaded('prices'),
                fn () => app(PlanPriceResolverInterface::class)
                    ->resolve($this->resource, app(CurrencySettingsInterface::class)->adminCurrency())
                    ->formatted
            ),
            'price_currency' => $this->when(
                $this->relationLoaded('prices'),
                fn () => app(CurrencySettingsInterface::class)->adminCurrency()
            ),
            'price_source' => $this->when(
                $this->relationLoaded('prices'),
                fn () => app(PlanPriceResolverInterface::class)
                    ->resolve($this->resource, app(CurrencySettingsInterface::class)->adminCurrency())
                    ->source
            ),
            'duration_months' => $this->duration_months,
            'max_users' => $this->max_users,
            'max_storage' => $this->max_storage,
            'max_warehouses' => $this->max_warehouses,
            'max_categories' => $this->max_categories,
            'max_products' => $this->max_products,
            'features' => $this->when($this->relationLoaded('featureGates'), fn () => $this->features, fn () => $this->features),
            'feature_gates' => $this->whenLoaded('featureGates', fn () => $this->featureGates),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
