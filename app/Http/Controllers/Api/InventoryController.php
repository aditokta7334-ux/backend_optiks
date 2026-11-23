<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    /**
     * Riwayat Penerimaan Barang Masuk (IN).
     */
    public function goodsReceipts(Request $request): JsonResponse
    {
        $receipts = GoodsReceipt::with(['supplier', 'receiver', 'items.product'])
            ->latest('receipt_date')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $receipts,
        ]);
    }

    /**
     * Simpan Penerimaan Barang Masuk (IN) & Tambah Stok Otomatis.
     */
    public function createGoodsReceipt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'receipt_date' => ['required', 'date'],
            'reference_invoice' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.buy_price' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $totalCost = 0;
            foreach ($validated['items'] as $item) {
                $totalCost += ($item['qty'] * $item['buy_price']);
            }

            $lastReceipt = GoodsReceipt::latest('id')->first();
            $nextNum = $lastReceipt ? ($lastReceipt->id + 1) : 1;
            $receiptNumber = 'GR-' . date('Ymd') . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $receipt = GoodsReceipt::create([
                'receipt_number' => $receiptNumber,
                'supplier_id' => $validated['supplier_id'],
                'receipt_date' => $validated['receipt_date'],
                'reference_invoice' => $validated['reference_invoice'] ?? null,
                'total_cost' => $totalCost,
                'received_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $product = Product::lockForUpdate()->find($itemData['product_id']);
                $prevStock = $product->current_stock;
                $newStock = $prevStock + $itemData['qty'];

                // Update stok dan perbarui HPP dengan harga beli terbaru
                $product->update([
                    'current_stock' => $newStock,
                    'cost_price' => $itemData['buy_price'],
                ]);

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $product->id,
                    'qty' => $itemData['qty'],
                    'buy_price' => $itemData['buy_price'],
                    'subtotal' => $itemData['qty'] * $itemData['buy_price'],
                ]);

                // Catat Mutasi Stok Masuk (IN_PURCHASE)
                StockMutation::create([
                    'product_id' => $product->id,
                    'mutation_date' => Carbon::parse($validated['receipt_date']),
                    'mutation_type' => 'IN_PURCHASE',
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'qty_in' => $itemData['qty'],
                    'qty_out' => 0,
                    'balance_stock' => $newStock,
                    'notes' => "Penerimaan Barang {$receipt->receipt_number} (" . ($validated['reference_invoice'] ?? 'Restock') . ")",
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Penerimaan barang {$receipt->receipt_number} berhasil disimpan.",
                'data' => $receipt->load(['supplier', 'items.product']),
            ], 201);
        });
    }

    /**
     * Riwayat Kartu Stok (Mutasi IN / OUT / STO).
     */
    public function stockCard(Request $request, ?int $productId = null): JsonResponse
    {
        $query = StockMutation::with('product.category')->latest('mutation_date');

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($request->filled('type')) {
            $query->where('mutation_type', $request->type);
        }

        $mutations = $query->paginate($request->integer('per_page', 30));

        return response()->json([
            'success' => true,
            'data' => $mutations,
        ]);
    }

    /**
     * Daftar Riwayat Sesi Stock Opname (STO).
     */
    public function stockOpnames(Request $request): JsonResponse
    {
        $opnames = StockOpname::with(['conductor', 'items.product.category'])
            ->latest('opname_date')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $opnames,
        ]);
    }

    /**
     * Buat Sesi Stock Opname (Draft).
     */
    public function createStockOpname(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opname_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.physical_stock' => ['required', 'integer', 'min:0'],
            'items.*.adjustment_reason' => ['nullable', 'string', 'max:150'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $lastOpname = StockOpname::latest('id')->first();
            $nextNum = $lastOpname ? ($lastOpname->id + 1) : 1;
            $opnameNumber = 'STO-' . date('Ymd') . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $opname = StockOpname::create([
                'opname_number' => $opnameNumber,
                'opname_date' => $validated['opname_date'],
                'conducted_by' => $request->user()?->id,
                'status' => 'draft',
                'total_items_checked' => count($validated['items']),
                'total_difference_items' => 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            $diffCount = 0;
            foreach ($validated['items'] as $itemData) {
                $product = Product::find($itemData['product_id']);
                $systemStock = $product ? $product->current_stock : 0;
                $difference = $itemData['physical_stock'] - $systemStock;

                if ($difference != 0) {
                    $diffCount++;
                }

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $itemData['product_id'],
                    'system_stock' => $systemStock,
                    'physical_stock' => $itemData['physical_stock'],
                    'difference' => $difference,
                    'adjustment_reason' => $itemData['adjustment_reason'] ?? null,
                ]);
            }

            $opname->update(['total_difference_items' => $diffCount]);

            return response()->json([
                'success' => true,
                'message' => "Draft Stock Opname {$opname->opname_number} berhasil dibuat.",
                'data' => $opname->load('items.product'),
            ], 201);
        });
    }

    /**
     * Eksekusi & Approve Stock Opname (Otomatis Menyesuaikan Stok Sistem).
     */
    public function approveStockOpname(int $id): JsonResponse
    {
        $opname = StockOpname::with('items.product')->findOrFail($id);

        if ($opname->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Stock opname ini sudah pernah disetujui sebelumnya.',
            ], 422);
        }

        return DB::transaction(function () use ($opname) {
            foreach ($opname->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                if ($product && $item->difference != 0) {
                    $newStock = $item->physical_stock;

                    $product->update(['current_stock' => $newStock]);

                    StockMutation::create([
                        'product_id' => $product->id,
                        'mutation_date' => now(),
                        'mutation_type' => 'ADJUSTMENT_OPNAME',
                        'reference_type' => StockOpname::class,
                        'reference_id' => $opname->id,
                        'qty_in' => $item->difference > 0 ? $item->difference : 0,
                        'qty_out' => $item->difference < 0 ? abs($item->difference) : 0,
                        'balance_stock' => $newStock,
                        'notes' => "Penyesuaian STO {$opname->opname_number}: " . ($item->adjustment_reason ?? 'Hasil audit fisik'),
                    ]);
                }
            }

            $opname->update(['status' => 'approved']);

            return response()->json([
                'success' => true,
                'message' => "Stock Opname {$opname->opname_number} berhasil disetujui dan stok telah disesuaikan.",
                'data' => $opname->fresh('items.product'),
            ]);
        });
    }
}
