<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = Product::count();
        $activeProducts = Product::where('active', true)->count();
        $totalOrders = Order::count();
        $totalCustomers = Customer::count();
        $lowStockCount = Product::where('active', true)
            ->whereRaw('(
                SELECT COALESCE(SUM(CASE WHEN type = \'out\' THEN -quantity ELSE quantity END), 0)
                FROM inventory_movements WHERE product_id = products.id
            ) < 10')
            ->count();

        $recentOrders = Order::with('customer')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'customer_name' => $o->customer?->name,
                'total' => $o->total,
                'status' => $o->status,
                'created_at' => $o->created_at->toDateString(),
            ]);

        $recentMovements = InventoryMovement::with('product', 'warehouse')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'product_name' => $m->product?->name,
                'warehouse_name' => $m->warehouse?->name,
                'type' => $m->type,
                'quantity' => $m->quantity,
                'created_at' => $m->created_at->toDateString(),
            ]);

        return Inertia::render('Tenant/Dashboard', [
            'stats' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'total_orders' => $totalOrders,
                'total_customers' => $totalCustomers,
                'low_stock_count' => $lowStockCount,
            ],
            'recentOrders' => $recentOrders,
            'recentMovements' => $recentMovements,
        ]);
    }
}
