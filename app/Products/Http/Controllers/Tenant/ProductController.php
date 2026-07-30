<?php

namespace App\Products\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Tenants\States\TenantStateManager;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'images')
            ->latest()
            ->paginate(5);

        return Inertia::render('Tenant/Products/Index', [
            'products' => $products,
        ]);
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Tenant/Products/Form', [
            'product' => null,
            'categories' => $categories,
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        $this->handleImages($request, $product);

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $product->load('category', 'images');
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Tenant/Products/Form', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        $this->handleImages($request, $product);

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->images()->each(fn ($img) => $img->delete());
        $product->delete();

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    private function handleImages($request, Product $product): void
    {
        if ($request->hasFile('image')) {
            $product->images()->each(fn ($img) => $img->delete());

            $file = $request->file('image');
            $path = $file->store('products/'.$product->id, 'local');

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'disk' => 'local',
                'size_bytes' => $file->getSize(),
                'sort_order' => 0,
            ]);
        }
    }
}
