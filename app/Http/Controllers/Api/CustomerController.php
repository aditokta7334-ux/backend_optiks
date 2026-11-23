<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    /**
     * Daftar pelanggan/pasien optik dengan histori resep & transaksi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::withCount('orders')->with(['orders' => function ($q) {
            $q->latest('order_date')->with('prescription')->take(3);
        }]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Tambah pelanggan baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'age' => ['nullable', 'integer', 'min:1', 'max:130'],
            'address' => ['nullable', 'string'],
            'medical_notes' => ['nullable', 'string'],
        ]);

        $validated['customer_code'] = 'CUST-' . strtoupper(Str::random(6));

        $customer = Customer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data pasien berhasil disimpan.',
            'data' => $customer,
        ], 201);
    }

    /**
     * Detail pelanggan beserta riwayat resep kacamata.
     */
    public function show(int $id): JsonResponse
    {
        $customer = Customer::with(['orders' => function ($q) {
            $q->latest('order_date')->with(['items', 'prescription']);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    /**
     * Update data pelanggan.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'age' => ['nullable', 'integer', 'min:1', 'max:130'],
            'address' => ['nullable', 'string'],
            'medical_notes' => ['nullable', 'string'],
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data pasien berhasil diperbarui.',
            'data' => $customer,
        ]);
    }
}
