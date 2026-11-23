<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MasterController extends Controller
{
    /**
     * Mengambil daftar master kategori, satuan, dan supplier.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => Category::withCount('products')->get(),
                'units' => Unit::all(),
                'suppliers' => Supplier::all(),
            ],
        ]);
    }

    /**
     * Tambah Supplier / Distributor Baru.
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30', 'unique:suppliers,code'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = 'SUP-' . strtoupper(Str::random(5));
        }

        $supplier = Supplier::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier baru berhasil ditambahkan.',
            'data' => $supplier,
        ], 201);
    }

    /**
     * Tambah Kategori Produk Baru.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:30', 'unique:categories,code'],
            'default_unit' => ['nullable', 'string', 'max:30'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = strtoupper(Str::slug($validated['name'], '_'));
        }

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori baru berhasil ditambahkan.',
            'data' => $category,
        ], 201);
    }

    /**
     * Mengambil opsi standar ukuran visus & jenis lensa (sesuai data Excel Optik Arjuna & May Way).
     */
    public function lensVisusOptions(): JsonResponse
    {
        // Standar Spheris: Plano, -0.25 s/d -10.00, +0.25 s/d +10.00 (step 0.25)
        $spheris = ['PLANO'];
        for ($v = 0.25; $v <= 10.00; $v += 0.25) {
            $formatted = sprintf('%.2f', $v);
            $spheris[] = '-' . $formatted;
            $spheris[] = '+' . $formatted;
        }

        // Standar Cylindris: 0.00, -0.25 s/d -6.00, +0.25 s/d +6.00
        $cylindris = ['0.00'];
        for ($v = 0.25; $v <= 6.00; $v += 0.25) {
            $formatted = sprintf('%.2f', $v);
            $cylindris[] = '-' . $formatted;
            $cylindris[] = '+' . $formatted;
        }

        // Standar ADD: +0.75 s/d +3.50
        $adds = ['0.00'];
        for ($v = 0.75; $v <= 3.50; $v += 0.25) {
            $adds[] = '+' . sprintf('%.2f', $v);
        }

        // Jenis Lensa dari Dokumen Excel
        $lensTypes = [
            'Single Vision CRMC / Anti Radiasi',
            'Single Vision Blueray (Blue Cut UV)',
            'Single Vision Photocromic Gray',
            'Single Vision Bluecromic (Blueray + Photocromic)',
            'Kriptok CRMC / Anti Radiasi',
            'Kriptok Blueray',
            'Kriptok Photocromic',
            'Kriptok Bluecromic',
            'Progresif CRMC / Anti Radiasi',
            'Progresif Blueray',
            'Progresif Photocromic',
            'Progresif Bluecromic',
            '(Gosok) CRMC / Anti Radiasi Custom',
            '(Gosok) Blueray Custom',
            '(Gosok) Photocromic Custom',
            '(Gosok) Bluecromic Custom',
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'spheris' => $spheris,
                'cylindris' => $cylindris,
                'adds' => $adds,
                'lens_types' => $lensTypes,
            ],
        ]);
    }
}
