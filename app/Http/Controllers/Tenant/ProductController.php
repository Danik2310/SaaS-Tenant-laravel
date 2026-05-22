<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')
            ->latest()
            ->paginate(15);

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
        Product::create($request->validated());

        return redirect()->route('tenant.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $product->load('category');
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Tenant/Products/Form', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return redirect()->route('tenant.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('tenant.products.index')
            ->with('success', 'Product deleted successfully.');
    }
}
