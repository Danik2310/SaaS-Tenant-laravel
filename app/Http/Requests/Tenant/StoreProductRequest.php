<?php

namespace App\Http\Requests\Tenant;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! tenant()) {
            return false;
        }

        $currentCount = Product::count();
        $limit = tenant()->getLimit('products');

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
