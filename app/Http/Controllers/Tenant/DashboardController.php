<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private function cachedWithFallback(string $tag, string $key, int $ttl, callable $callback): mixed
    {
        try {
            return Cache::tags([$tag])->remember($key, $ttl, $callback);
        } catch (\BadMethodCallException $e) {
            return $callback();
        }
    }

    public function index()
    {
        $tag = 'tenant_' . tenant('id');

        $totalProducts = $this->cachedWithFallback($tag, 'dash_total_products', 300, fn () => Product::count());
        $activeProducts = $this->cachedWithFallback($tag, 'dash_active_products', 300, fn () => Product::where('active', true)->count());
        $totalOrders = $this->cachedWithFallback($tag, 'dash_total_orders', 300, fn () => Order::count());
        $totalCustomers = $this->cachedWithFallback($tag, 'dash_total_customers', 300, fn () => Customer::count());
        $lowStockCount = $this->cachedWithFallback($tag, 'dash_low_stock', 300, fn () =>
            Product::where('active', true)
                ->whereRaw('(
                    SELECT COALESCE(SUM(CASE WHEN type = \'out\' THEN -quantity ELSE quantity END), 0)
                    FROM inventory_movements WHERE product_id = products.id
                ) < 10')
                ->count()
        );

        $previousActiveProducts = $this->cachedWithFallback($tag, 'dashboard_prev_active_products', 3600, function () {
            return Product::where('active', true)
                ->where('created_at', '<', now()->subMonth())
                ->count();
        });

        $previousOrders = $this->cachedWithFallback($tag, 'dashboard_prev_orders', 3600, function () {
            return Order::where('created_at', '<', now()->subMonth())->count();
        });

        $previousCustomers = $this->cachedWithFallback($tag, 'dashboard_prev_customers', 3600, function () {
            return Customer::where('created_at', '<', now()->subMonth())->count();
        });

        return Inertia::render('Tenant/Dashboard', [
            'stats' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'total_orders' => $totalOrders,
                'total_customers' => $totalCustomers,
                'low_stock_count' => $lowStockCount,
            ],
            'trends' => [
                'active_products' => $previousActiveProducts > 0
                    ? round((($activeProducts - $previousActiveProducts) / $previousActiveProducts) * 100, 1)
                    : 0,
                'total_orders' => $previousOrders > 0
                    ? round((($totalOrders - $previousOrders) / $previousOrders) * 100, 1)
                    : 0,
                'total_customers' => $previousCustomers > 0
                    ? round((($totalCustomers - $previousCustomers) / $previousCustomers) * 100, 1)
                    : 0,
            ],
            'recentOrders' => Inertia::lazy(fn () => Order::with('customer')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'customer_name' => $o->customer?->name,
                    'total' => $o->total,
                    'status' => $o->status,
                    'created_at' => $o->created_at->toDateString(),
                ])),
            'recentMovements' => Inertia::lazy(fn () => InventoryMovement::with('product', 'warehouse')
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
                ])),
        ]);
    }
}
