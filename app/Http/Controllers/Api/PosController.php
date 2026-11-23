<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPrescription;
use App\Models\Product;
use App\Models\StockMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    /**
     * Mengambil daftar riwayat transaksi kasir optik.
     */
    public function orders(Request $request): JsonResponse
    {
        $query = Order::with(['customer', 'cashier', 'items.product', 'prescription'])
            ->latest('order_date');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('date')) {
            $query->whereDate('order_date', $request->date);
        }

        $orders = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Detail transaksi beserta resep visus dan rincian item.
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['customer', 'cashier', 'items.product.category', 'prescription'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Memproses Checkout Transaksi POS Kasir (Pengurangan Stok & Pembuatan Nota Otomatis).
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Customer Info
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'delivery_date' => ['nullable', 'date'],

            // Items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.item_name' => ['required', 'string', 'max:200'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],

            // Financials
            'discount_type' => ['nullable', 'in:percentage,nominal'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'advance_payment' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],

            // Resep Visus (Opsional, jika transaksi kacamata/lensa)
            'has_prescription' => ['nullable', 'boolean'],
            'prescription' => ['nullable', 'array'],
            'prescription.right_sph' => ['nullable', 'string', 'max:20'],
            'prescription.right_cyl' => ['nullable', 'string', 'max:20'],
            'prescription.right_axis' => ['nullable', 'string', 'max:20'],
            'prescription.right_va' => ['nullable', 'string', 'max:20'],
            'prescription.right_pd' => ['nullable', 'string', 'max:20'],
            'prescription.left_sph' => ['nullable', 'string', 'max:20'],
            'prescription.left_cyl' => ['nullable', 'string', 'max:20'],
            'prescription.left_axis' => ['nullable', 'string', 'max:20'],
            'prescription.left_va' => ['nullable', 'string', 'max:20'],
            'prescription.left_pd' => ['nullable', 'string', 'max:20'],
            'prescription.add_right' => ['nullable', 'string', 'max:20'],
            'prescription.add_left' => ['nullable', 'string', 'max:20'],
            'prescription.ipd' => ['nullable', 'string', 'max:20'],
            'prescription.frame_details' => ['nullable', 'string', 'max:200'],
            'prescription.lens_details' => ['nullable', 'string', 'max:250'],
            'prescription.notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // 1. Dapatkan atau Buat Pasien / Customer
            $customerId = $validated['customer_id'] ?? null;
            if (! $customerId) {
                $customer = Customer::create([
                    'customer_code' => 'CUST-' . strtoupper(Str::random(6)),
                    'name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'] ?? null,
                ]);
                $customerId = $customer->id;
            }

            // 2. Hitung Nilai Finansial
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += ($item['qty'] * $item['unit_price']);
            }

            $discountType = $validated['discount_type'] ?? 'nominal';
            $discountRate = (float) ($validated['discount_rate'] ?? 0);
            $discountAmount = (float) ($validated['discount_amount'] ?? 0);

            if ($discountType === 'percentage' && $discountRate > 0) {
                $discountAmount = round(($subtotal * $discountRate) / 100, 2);
            }

            $netTotal = max(0, $subtotal - $discountAmount);
            $advance = (float) ($validated['advance_payment'] ?? 0);
            $balanceDue = max(0, $netTotal - $advance);

            // Tentukan status pembayaran
            $paymentStatus = 'unpaid';
            if ($balanceDue <= 0) {
                $paymentStatus = 'paid';
                $balanceDue = 0;
            } elseif ($advance > 0) {
                $paymentStatus = 'partial_dp';
            }

            // Generate Invoice Number unik berurutan
            $lastOrder = Order::latest('id')->first();
            $nextNumber = $lastOrder ? ($lastOrder->id + 1) : 1;
            $invoiceNumber = '#' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Tentukan status pengerjaan (jika ada resep masuk lab, default pending_lab)
            $hasPrescription = ! empty($validated['has_prescription']) || ! empty($validated['prescription']);
            $orderStatus = $hasPrescription ? 'pending_lab' : 'completed';

            // 3. Simpan Header Order
            $order = Order::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customerId,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'user_id' => $request->user()?->id,
                'order_date' => now(),
                'delivery_date' => ! empty($validated['delivery_date'])
                    ? Carbon::parse($validated['delivery_date'])
                    : now()->addDay(),
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_rate' => $discountRate,
                'discount_amount' => $discountAmount,
                'net_total' => $netTotal,
                'advance_payment' => $advance,
                'payment_method' => $validated['payment_method'],
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
                'order_status' => $orderStatus,
                'notes' => $validated['notes'] ?? null,
            ]);

            // 4. Simpan Order Items & Potong Stok Otomatis (OUT)
            foreach ($validated['items'] as $itemData) {
                $productId = $itemData['product_id'] ?? null;
                $costPrice = 0;

                if ($productId) {
                    $product = Product::lockForUpdate()->find($productId);
                    if ($product) {
                        $costPrice = $product->cost_price;
                        $prevStock = $product->current_stock;
                        $newStock = max(0, $prevStock - $itemData['qty']);

                        $product->update(['current_stock' => $newStock]);

                        // Catat Mutasi Stok Keluar (OUT_SALE)
                        StockMutation::create([
                            'product_id' => $product->id,
                            'mutation_date' => now(),
                            'mutation_type' => 'OUT_SALE',
                            'reference_type' => Order::class,
                            'reference_id' => $order->id,
                            'qty_in' => 0,
                            'qty_out' => $itemData['qty'],
                            'balance_stock' => $newStock,
                            'notes' => "Penjualan POS Invoice {$order->invoice_number} ({$order->customer_name})",
                        ]);
                    }
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'item_name' => $itemData['item_name'],
                    'qty' => $itemData['qty'],
                    'cost_price' => $costPrice,
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['qty'] * $itemData['unit_price'],
                ]);
            }

            // 5. Simpan Resep Visus Jika Ada
            if ($hasPrescription && ! empty($validated['prescription'])) {
                $p = $validated['prescription'];
                OrderPrescription::create([
                    'order_id' => $order->id,
                    'right_sph' => $p['right_sph'] ?? '-',
                    'right_cyl' => $p['right_cyl'] ?? '0.00',
                    'right_axis' => $p['right_axis'] ?? '-',
                    'right_va' => $p['right_va'] ?? '-',
                    'right_pd' => $p['right_pd'] ?? '-',
                    'left_sph' => $p['left_sph'] ?? '-',
                    'left_cyl' => $p['left_cyl'] ?? '0.00',
                    'left_axis' => $p['left_axis'] ?? '-',
                    'left_va' => $p['left_va'] ?? '-',
                    'left_pd' => $p['left_pd'] ?? '-',
                    'add_right' => $p['add_right'] ?? '-',
                    'add_left' => $p['add_left'] ?? '-',
                    'ipd' => $p['ipd'] ?? '-',
                    'frame_details' => $p['frame_details'] ?? null,
                    'lens_details' => $p['lens_details'] ?? null,
                    'notes' => $p['notes'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Transaksi {$order->invoice_number} berhasil diproses.",
                'data' => $order->load(['customer', 'cashier', 'items.product', 'prescription']),
            ], 201);
        });
    }

    /**
     * Pelunasan sisa tagihan (Balance Due) saat pelanggan mengambil kacamata.
     */
    public function settlePayment(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'string', 'max:30'],
        ]);

        $newAdvance = $order->advance_payment + $validated['amount_paid'];
        $newBalance = max(0, $order->net_total - $newAdvance);

        $order->update([
            'advance_payment' => $newAdvance,
            'balance_due' => $newBalance,
            'payment_status' => $newBalance <= 0 ? 'paid' : 'partial_dp',
            'order_status' => $newBalance <= 0 ? 'completed' : $order->order_status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran pelunasan berhasil dicatat.',
            'data' => $order->fresh(['customer', 'items', 'prescription']),
        ]);
    }

    /**
     * Update alur status pengerjaan kacamata (pending_lab -> in_process -> ready_for_pickup -> completed).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'order_status' => ['required', 'in:pending_lab,in_process,ready_for_pickup,completed,cancelled'],
        ]);

        $order->update(['order_status' => $validated['order_status']]);

        return response()->json([
            'success' => true,
            'message' => "Status pesanan diubah menjadi {$validated['order_status']}.",
            'data' => $order,
        ]);
    }
}
