<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Daftar produk optik dengan pencarian, filter kategori, dan pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'unit'])->where('is_active', true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->item_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock_only')) {
            $query->whereColumn('current_stock', '<=', 'min_stock_alert');
        }

        $products = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Tambah produk baru ke master data inventori.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:200'],
            'brand' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'current_stock' => ['nullable', 'integer', 'min:0'],
            'min_stock_alert' => ['nullable', 'integer', 'min:0'],
            'item_type' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
        ]);

        if (empty($validated['sku'])) {
            $validated['sku'] = 'PRD-' . strtoupper(Str::random(8));
        }

        $initialStock = $validated['current_stock'] ?? 0;
        $product = Product::create($validated);

        if ($initialStock > 0) {
            StockMutation::create([
                'product_id' => $product->id,
                'mutation_date' => now(),
                'mutation_type' => 'IN_PURCHASE',
                'qty_in' => $initialStock,
                'qty_out' => 0,
                'balance_stock' => $initialStock,
                'notes' => 'Input stok awal produk baru',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan.',
            'data' => $product->load(['category', 'unit']),
        ], 201);
    }

    /**
     * Detail produk.
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'unit', 'stockMutations' => function ($q) {
            $q->latest('mutation_date')->take(20);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Update data produk.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku,' . $product->id],
            'barcode' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:200'],
            'brand' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'min_stock_alert' => ['nullable', 'integer', 'min:0'],
            'item_type' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data produk berhasil diperbarui.',
            'data' => $product->load(['category', 'unit']),
        ]);
    }

    /**
     * Nonaktifkan produk.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dinonaktifkan.',
        ]);
    }
}
