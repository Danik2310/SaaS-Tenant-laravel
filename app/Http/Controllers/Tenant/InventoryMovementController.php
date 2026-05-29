<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreInventoryMovementRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use Inertia\Inertia;

class InventoryMovementController extends Controller
{
    public function index()
    {
        $movements = InventoryMovement::with('product', 'warehouse')
            ->latest()
            ->paginate(20);

        return Inertia::render('Tenant/Inventory/Index', [
            'movements' => $movements,
        ]);
    }

    public function create()
    {
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sku']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Tenant/Inventory/Form', [
            'movement' => null,
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(StoreInventoryMovementRequest $request)
    {
        InventoryMovement::create($request->validated());

        $this->flushTenantCache();

        return redirect()->route('tenant.inventory.index')
            ->with('success', 'Inventory movement recorded successfully.');
    }
}
