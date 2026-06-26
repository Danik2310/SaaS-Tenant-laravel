<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $tag = 'tenant_'.tenant('id');

        $current = $this->cachedWithFallback($tag, 'dash_current_counts', 300, function () {
            return DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM products) AS total_products,
                    (SELECT COUNT(*) FROM products WHERE active = 1) AS active_products,
                    (SELECT COUNT(*) FROM orders) AS total_orders,
                    (SELECT COUNT(*) FROM customers) AS total_customers,
                    (SELECT COUNT(*) FROM products p WHERE p.active = 1 AND (
                        SELECT COALESCE(SUM(CASE WHEN im.type = 'out' THEN -im.quantity ELSE im.quantity END), 0)
                        FROM inventory_movements im WHERE im.product_id = p.id
                    ) < 10) AS low_stock_count
            ");
        });

        $previous = $this->cachedWithFallback($tag, 'dash_previous_counts', 3600, function () {
            return DB::selectOne('
                SELECT
                    (SELECT COUNT(*) FROM products WHERE active = 1 AND created_at < ?) AS prev_active_products,
                    (SELECT COUNT(*) FROM orders WHERE created_at < ?) AS prev_orders,
                    (SELECT COUNT(*) FROM customers WHERE created_at < ?) AS prev_customers
            ', [now()->subMonth(), now()->subMonth(), now()->subMonth()]);
        });

        $totalProducts = (int) ($current->total_products ?? 0);
        $activeProducts = (int) ($current->active_products ?? 0);
        $totalOrders = (int) ($current->total_orders ?? 0);
        $totalCustomers = (int) ($current->total_customers ?? 0);
        $lowStockCount = (int) ($current->low_stock_count ?? 0);
        $previousActiveProducts = (int) ($previous->prev_active_products ?? 0);
        $previousOrders = (int) ($previous->prev_orders ?? 0);
        $previousCustomers = (int) ($previous->prev_customers ?? 0);

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
            'recentMovements' => Inertia::lazy(function () {
                if (! tenant()->hasFeature('advanced')) {
                    return [];
                }

                return InventoryMovement::with('product', 'warehouse')
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
            }),
        ]);
    }
}
