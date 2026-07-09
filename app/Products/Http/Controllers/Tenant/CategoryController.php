<?php

namespace App\Products\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCategoryRequest;
use App\Http\Requests\Tenant\UpdateCategoryRequest;
use App\Models\Category;
use App\Tenants\States\TenantStateManager;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')
            ->with('parent:id,name')
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('Tenant/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['name']);

        Category::create($validated);

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return redirect()->route('tenant.categories.index')
                ->with('error', 'Cannot delete category with associated products.');
        }

        $category->delete();

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
