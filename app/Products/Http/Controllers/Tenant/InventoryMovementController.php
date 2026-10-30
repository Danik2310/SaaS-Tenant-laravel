<?php

namespace App\Products\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreInventoryMovementRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Tenants\States\TenantStateManager;
use Inertia\Inertia;

class InventoryMovementController extends Controller
{
    public function index()
    {
        $movements = InventoryMovement::query()
            ->select(['id', 'product_id', 'warehouse_id', 'type', 'quantity', 'reason', 'created_at', 'updated_at'])
            ->with('product:id,name,sku', 'warehouse:id,name')
            ->latest()
            ->paginate(5);

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

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.inventory.index')
            ->with('success', 'Inventory movement recorded successfully.');
    }
}
