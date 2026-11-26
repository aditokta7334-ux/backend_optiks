<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $dbStatus = 'ok';
    $dbError = null;
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        $dbStatus = 'error';
        $dbError = $e->getMessage();
    }

    return response()->json([
        'success' => true,
        'app' => 'Optik Arjuna & May Way API',
        'status' => 'ok',
        'db_connection' => config('database.default'),
        'db_host' => config('database.connections.' . config('database.default') . '.host'),
        'db_database' => config('database.connections.' . config('database.default') . '.database'),
        'db_status' => $dbStatus,
        'db_error' => $dbError,
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Autentikasi Pengguna
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Rute yang membutuhkan Bearer Token
Route::middleware('api.token')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // 1. Dashboard Monitoring & Analitik
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // 2. Master Data & Opsi Visus
    Route::get('/master/all', [MasterController::class, 'index']);
    Route::post('/master/suppliers', [MasterController::class, 'storeSupplier']);
    Route::post('/master/categories', [MasterController::class, 'storeCategory']);
    Route::get('/master/lens-visus-options', [MasterController::class, 'lensVisusOptions']);

    // 3. Manajemen Produk & Inventori
    Route::apiResource('products', ProductController::class);

    // 4. Data Pasien / Pelanggan
    Route::apiResource('customers', CustomerController::class);

    // 5. Kasir POS & Transaksi
    Route::get('/pos/orders', [PosController::class, 'orders']);
    Route::get('/pos/orders/{id}', [PosController::class, 'show']);
    Route::post('/pos/checkout', [PosController::class, 'checkout']);
    Route::post('/pos/orders/{id}/settle', [PosController::class, 'settlePayment']);
    Route::put('/pos/orders/{id}/status', [PosController::class, 'updateStatus']);

    // 6. Inventori (IN Penerimaan Barang, Kartu Stok, dan STO)
    Route::get('/inventory/goods-receipts', [InventoryController::class, 'goodsReceipts']);
    Route::post('/inventory/goods-receipts', [InventoryController::class, 'createGoodsReceipt']);
    Route::get('/inventory/stock-cards/{product_id?}', [InventoryController::class, 'stockCard']);
    Route::get('/inventory/stock-opnames', [InventoryController::class, 'stockOpnames']);
    Route::post('/inventory/stock-opnames', [InventoryController::class, 'createStockOpname']);
    Route::post('/inventory/stock-opnames/{id}/approve', [InventoryController::class, 'approveStockOpname']);
});
