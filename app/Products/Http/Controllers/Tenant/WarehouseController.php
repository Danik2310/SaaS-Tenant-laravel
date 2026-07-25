<?php

namespace App\Products\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreWarehouseRequest;
use App\Http\Requests\Tenant\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Tenants\States\TenantStateManager;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::orderBy('name')->paginate(5);

        return Inertia::render('Tenant/Warehouses/Index', [
            'warehouses' => $warehouses,
        ]);
    }

    public function store(StoreWarehouseRequest $request)
    {
        Warehouse::create($request->validated());

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.warehouses.index')
            ->with('success', 'Warehouse created successfully.');
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse->update($request->validated());

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.warehouses.index')
            ->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        TenantStateManager::flushTenantCache(tenancy()->tenant);

        return redirect()->route('tenant.warehouses.index')
            ->with('success', 'Warehouse deleted successfully.');
    }
}
