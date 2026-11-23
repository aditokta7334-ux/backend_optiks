<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Ringkasan KPI Dashboard Optik (Omset, Total Order, Piutang, Stok Menipis, Status Lab).
     */
    public function stats(): JsonResponse
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // 1. Omset & Order Hari Ini
        $todayOrders = Order::whereDate('order_date', $today)->get();
        $todaySales = $todayOrders->sum('net_total');
        $todayOrdersCount = $todayOrders->count();

        // 2. Omset Bulan Ini
        $monthOrders = Order::where('order_date', '>=', $thisMonth)->get();
        $monthSales = $monthOrders->sum('net_total');

        // 3. Total Piutang Pelanggan (Sisa Tagihan / Balance Due belum lunas)
        $totalBalanceDue = Order::where('payment_status', '!=', 'paid')
            ->where('balance_due', '>', 0)
            ->sum('balance_due');

        // 4. Kacamata di Lab / Menunggu Diambil
        $pendingLabCount = Order::whereIn('order_status', ['pending_lab', 'in_process'])->count();
        $readyForPickupCount = Order::where('order_status', 'ready_for_pickup')->count();

        // 5. Jumlah Produk dengan Stok Kritis (Stok <= Batas Minimal)
        $lowStockProducts = Product::whereColumn('current_stock', '<=', 'min_stock_alert')
            ->where('is_active', true)
            ->with('category')
            ->take(8)
            ->get();
        $lowStockCount = Product::whereColumn('current_stock', '<=', 'min_stock_alert')
            ->where('is_active', true)
            ->count();

        // 6. Grafik Penjualan 7 Hari Terakhir
        $chartSales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayTotal = Order::whereDate('order_date', $date)->sum('net_total');
            $dayCount = Order::whereDate('order_date', $date)->count();
            $chartSales[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->translatedFormat('D'),
                'total' => (float) $dayTotal,
                'count' => $dayCount,
            ];
        }

        // 7. Pipeline Status Order Kacamata
        $pipeline = [
            'pending_lab' => Order::where('order_status', 'pending_lab')->count(),
            'in_process' => Order::where('order_status', 'in_process')->count(),
            'ready_for_pickup' => $readyForPickupCount,
            'completed' => Order::where('order_status', 'completed')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'today_sales' => (float) $todaySales,
                'today_orders_count' => $todayOrdersCount,
                'month_sales' => (float) $monthSales,
                'total_balance_due' => (float) $totalBalanceDue,
                'pending_lab_count' => $pendingLabCount,
                'ready_for_pickup_count' => $readyForPickupCount,
                'low_stock_count' => $lowStockCount,
                'low_stock_products' => $lowStockProducts,
                'chart_sales' => $chartSales,
                'pipeline' => $pipeline,
            ],
        ]);
    }
}
