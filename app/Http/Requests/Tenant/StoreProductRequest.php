<?php

namespace App\Http\Requests\Tenant;

use App\Billing\Factories\ResourceEnforcementFactory;
use App\Shared\Exceptions\PlanLimitExceededException;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null || ! tenant()) {
            return false;
        }

        if (! $this->user()->can('manage products')) {
            return false;
        }

        $strategy = ResourceEnforcementFactory::make(tenant());
        $limit = $strategy->maxProducts();

        $currentCount = Product::count();

        if ($limit !== PHP_INT_MAX && $currentCount >= $limit) {
            throw new PlanLimitExceededException('products', $limit);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'active' => 'boolean',
        ];
    }
}
