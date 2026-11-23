<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPrescription;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Pengguna Toko
        $admin = User::updateOrCreate(
            ['email' => 'admin@arjunamayway.id'],
            [
                'name' => 'Arjuna Pratama (Admin)',
                'phone' => '0812-3456-7890',
                'password' => 'password123',
                'role' => 'admin',
                'membership_tier' => 'VIP',
                'prescription_od' => 'Plano (0.00)',
                'prescription_os' => 'Plano (0.00)',
                'last_exam_date' => '2026-09-15',
            ]
        );

        $dokter = User::updateOrCreate(
            ['email' => 'dokter@arjunamayway.id'],
            [
                'name' => 'dr. May Way, Sp.M',
                'phone' => '0811-2233-4455',
                'password' => 'password123',
                'role' => 'optometrist',
                'membership_tier' => 'VIP',
                'prescription_od' => '-0.50',
                'prescription_os' => '-0.50',
                'last_exam_date' => '2026-10-02',
            ]
        );

        $pelangganBudi = User::updateOrCreate(
            ['email' => 'budi@arjunamayway.id'],
            [
                'name' => 'Budi Santoso',
                'phone' => '0813-9876-5432',
                'password' => 'password123',
                'role' => 'customer',
                'membership_tier' => 'Gold',
                'prescription_od' => '-1.25 / Cyl -0.50',
                'prescription_os' => '-1.50 / Cyl -0.25',
                'last_exam_date' => '2026-08-20',
            ]
        );

        // Alias Lumina agar backward-compatible
        User::updateOrCreate(
            ['email' => 'admin@luminaoptics.id'],
            ['name' => 'Arjuna Pratama (Admin)', 'password' => 'password123', 'role' => 'admin']
        );

        // 2. Satuan Barang
        $pcs = Unit::firstOrCreate(['name' => 'Pcs']);
        $pasang = Unit::firstOrCreate(['name' => 'Pasang']);
        $box = Unit::firstOrCreate(['name' => 'Box']);

        // 3. Kategori Sesuai Master Data Excel
        $catData = [
            ['code' => 'LENSA', 'name' => 'Lensa', 'default_unit' => 'Pcs', 'icon' => 'visibility'],
            ['code' => 'FRAME', 'name' => 'Frame', 'default_unit' => 'Pcs', 'icon' => 'crop_square'],
            ['code' => 'SOFTLENS', 'name' => 'Softlens', 'default_unit' => 'Pcs', 'icon' => 'album'],
            ['code' => 'CAIRAN_TETES', 'name' => 'Cairan Tetes', 'default_unit' => 'Pcs', 'icon' => 'water_drop'],
            ['code' => 'CAIRAN_SOFTLENS', 'name' => 'Cairan Softlens', 'default_unit' => 'Pcs', 'icon' => 'opacity'],
            ['code' => 'CLEANER', 'name' => 'Cleaner', 'default_unit' => 'Pcs', 'icon' => 'sanitizer'],
            ['code' => 'BOX', 'name' => 'Box', 'default_unit' => 'Pcs', 'icon' => 'inventory_2'],
            ['code' => 'LAP_MICROFIBER', 'name' => 'Lap Microfiber', 'default_unit' => 'Box', 'icon' => 'dry_cleaning'],
        ];

        $categories = [];
        foreach ($catData as $c) {
            $categories[$c['code']] = Category::updateOrCreate(['code' => $c['code']], $c);
        }

        // 4. Supplier
        $supEssilor = Supplier::firstOrCreate(['code' => 'SUP-001'], [
            'name' => 'PT Essilor Indonesia',
            'contact_person' => 'Irfan Hakim',
            'phone' => '021-5551234',
            'address' => 'Jakarta Selatan',
        ]);

        $supHoya = Supplier::firstOrCreate(['code' => 'SUP-002'], [
            'name' => 'PT Hoya Lens Indonesia',
            'contact_person' => 'Dewi Sartika',
            'phone' => '021-7778899',
            'address' => 'Tangerang',
        ]);

        $supFrame = Supplier::firstOrCreate(['code' => 'SUP-003'], [
            'name' => 'Distributor Frame Mandiri',
            'contact_person' => 'Hendra',
            'phone' => '0812-9988-7766',
            'address' => 'Bandung',
        ]);

        // 5. Produk Master (Frame, Lensa, Aksesoris)
        $productsData = [
            // Frame
            [
                'category_id' => $categories['FRAME']->id,
                'unit_id' => $pcs->id,
                'sku' => 'FRM-RIMLESS-01',
                'barcode' => '8991001001',
                'name' => 'Rimless Titanium Ultra-Light Frame',
                'brand' => 'Arjuna Exclusive',
                'cost_price' => 320000,
                'selling_price' => 650000,
                'current_stock' => 15,
                'min_stock_alert' => 3,
                'item_type' => 'frame',
                'description' => 'Frame frameless titanium ringan anti patah dan elegan.',
            ],
            [
                'category_id' => $categories['FRAME']->id,
                'unit_id' => $pcs->id,
                'sku' => 'FRM-AVIATOR-BLK',
                'barcode' => '8991001002',
                'name' => 'Aviator Classic Matt Black',
                'brand' => 'May Way Series',
                'cost_price' => 210000,
                'selling_price' => 450000,
                'current_stock' => 8,
                'min_stock_alert' => 2,
                'item_type' => 'frame',
                'description' => 'Frame aviator bahan metal kokoh warna hitam doff.',
            ],
            [
                'category_id' => $categories['FRAME']->id,
                'unit_id' => $pcs->id,
                'sku' => 'FRM-CAT-ACETATE',
                'barcode' => '8991001003',
                'name' => 'Cat Eye Korean Acetate Fashion',
                'brand' => 'Lumina Style',
                'cost_price' => 180000,
                'selling_price' => 380000,
                'current_stock' => 2, // Kritis (alert)
                'min_stock_alert' => 4,
                'item_type' => 'frame',
                'description' => 'Frame gaya korea model cat-eye bahan acetate glossy.',
            ],

            // Lensa Sesuai Dokumen Excel
            [
                'category_id' => $categories['LENSA']->id,
                'unit_id' => $pcs->id,
                'sku' => 'LNS-SV-CRMC',
                'barcode' => '8992001001',
                'name' => 'Single Vision CRMC / Anti Radiasi',
                'brand' => 'Optik Arjuna Standard',
                'cost_price' => 60000,
                'selling_price' => 150000,
                'current_stock' => 40,
                'min_stock_alert' => 10,
                'item_type' => 'lens',
                'description' => 'Lensa bening lapisan anti radiasi elektromagnetik standar.',
            ],
            [
                'category_id' => $categories['LENSA']->id,
                'unit_id' => $pcs->id,
                'sku' => 'LNS-SV-BLUERAY',
                'barcode' => '8992001002',
                'name' => 'Single Vision Blueray (Blue Cut UV)',
                'brand' => 'Arjuna Shield',
                'cost_price' => 95000,
                'selling_price' => 250000,
                'current_stock' => 35,
                'min_stock_alert' => 10,
                'item_type' => 'lens',
                'description' => 'Lensa pemblokir sinar biru gadget dan sinar UV 420nm.',
            ],
            [
                'category_id' => $categories['LENSA']->id,
                'unit_id' => $pcs->id,
                'sku' => 'LNS-SV-PHOTO',
                'barcode' => '8992001003',
                'name' => 'Single Vision Photocromic Gray',
                'brand' => 'SunShift Optical',
                'cost_price' => 125000,
                'selling_price' => 300000,
                'current_stock' => 25,
                'min_stock_alert' => 8,
                'item_type' => 'lens',
                'description' => 'Lensa otomatis menggelap di bawah terik matahari.',
            ],
            [
                'category_id' => $categories['LENSA']->id,
                'unit_id' => $pcs->id,
                'sku' => 'LNS-SV-BLUECROMIC',
                'barcode' => '8992001004',
                'name' => 'Single Vision Bluecromic (Blueray + Photocromic)',
                'brand' => 'Arjuna Max Dual',
                'cost_price' => 170000,
                'selling_price' => 400000,
                'current_stock' => 20,
                'min_stock_alert' => 5,
                'item_type' => 'lens',
                'description' => 'Kombinasi anti radiasi gadget blueray sekaligus photocromic.',
            ],
            [
                'category_id' => $categories['LENSA']->id,
                'unit_id' => $pcs->id,
                'sku' => 'LNS-PROG-BLUERAY',
                'barcode' => '8992001005',
                'name' => 'Progresif Blueray Digital Freeform',
                'brand' => 'May Way Advance',
                'cost_price' => 280000,
                'selling_price' => 650000,
                'current_stock' => 12,
                'min_stock_alert' => 4,
                'item_type' => 'lens',
                'description' => 'Lensa progresif tanpa batas untuk jauh, menengah, dan baca dekat.',
            ],
            [
                'category_id' => $categories['LENSA']->id,
                'unit_id' => $pcs->id,
                'sku' => 'LNS-GOSOK-CUSTOM',
                'barcode' => '8992001006',
                'name' => 'Lensa Gosok Custom Lab (Single/Progresif)',
                'brand' => 'Custom Lab Order',
                'cost_price' => 350000,
                'selling_price' => 800000,
                'current_stock' => 50,
                'min_stock_alert' => 5,
                'item_type' => 'lens',
                'description' => 'Lensa gosok rx khusus custom perorangan dengan silinder/visus tinggi.',
            ],

            // Aksesoris & Perawatan
            [
                'category_id' => $categories['CLEANER']->id,
                'unit_id' => $pcs->id,
                'sku' => 'ACC-CLN-60ML',
                'barcode' => '8993001001',
                'name' => 'Lens Cleaner Spray 60ml Anti-Fog',
                'brand' => 'Optik Care',
                'cost_price' => 10000,
                'selling_price' => 25000,
                'current_stock' => 48,
                'min_stock_alert' => 10,
                'item_type' => 'accessory',
                'description' => 'Cairan pembersih lensa kacamata anti debu dan embun.',
            ],
            [
                'category_id' => $categories['CAIRAN_SOFTLENS']->id,
                'unit_id' => $pcs->id,
                'sku' => 'ACC-SL-RENU120',
                'barcode' => '8993001002',
                'name' => 'Cairan Softlens ReNu MultiPlus 120ml',
                'brand' => 'Bausch & Lomb',
                'cost_price' => 28000,
                'selling_price' => 48000,
                'current_stock' => 18,
                'min_stock_alert' => 5,
                'item_type' => 'accessory',
                'description' => 'Larutan serbaguna pembersih dan perendam lensa kontak.',
            ],
            [
                'category_id' => $categories['BOX']->id,
                'unit_id' => $pcs->id,
                'sku' => 'ACC-BOX-MAG',
                'barcode' => '8993001003',
                'name' => 'Hardcase Kotak Kacamata Magnetik Premium',
                'brand' => 'Arjuna Gear',
                'cost_price' => 15000,
                'selling_price' => 35000,
                'current_stock' => 30,
                'min_stock_alert' => 5,
                'item_type' => 'accessory',
                'description' => 'Tempat kacamata kokoh dengan penutup magnet.',
            ],
            [
                'category_id' => $categories['LAP_MICROFIBER']->id,
                'unit_id' => $box->id,
                'sku' => 'ACC-LAP-BOX',
                'barcode' => '8993001004',
                'name' => 'Kain Lap Microfiber Halus (Isi 10 Pcs/Box)',
                'brand' => 'Optik Care',
                'cost_price' => 20000,
                'selling_price' => 45000,
                'current_stock' => 22,
                'min_stock_alert' => 5,
                'item_type' => 'accessory',
                'description' => 'Lap pembersih super lembut tidak menggores lensa.',
            ],
        ];

        $createdProducts = [];
        foreach ($productsData as $p) {
            $prod = Product::updateOrCreate(['sku' => $p['sku']], $p);
            $createdProducts[$p['sku']] = $prod;

            // Catat mutasi awal stok
            StockMutation::firstOrCreate(
                [
                    'product_id' => $prod->id,
                    'mutation_type' => 'IN_PURCHASE',
                    'notes' => 'Stok awal toko Optik Arjuna & May Way',
                ],
                [
                    'mutation_date' => now()->subDays(10),
                    'qty_in' => $prod->current_stock,
                    'qty_out' => 0,
                    'balance_stock' => $prod->current_stock,
                ]
            );
        }

        // 6. Data Pelanggan / Pasien
        $custFitria = Customer::updateOrCreate(
            ['customer_code' => 'CUST-0001'],
            [
                'name' => 'FITRIA',
                'phone' => '087713731989',
                'age' => 24,
                'address' => 'Karawang Barat, Jawa Barat',
                'medical_notes' => 'Mata cepat lelah saat menatap layar laptop',
            ]
        );

        $custAlda = Customer::updateOrCreate(
            ['customer_code' => 'CUST-0002'],
            [
                'name' => 'ALDA NOFA SYAFIRA',
                'phone' => '08123456789',
                'age' => 19,
                'address' => 'Rawa Gempol, Karawang',
                'medical_notes' => 'Keluhan buram melihat jarak jauh',
            ]
        );

        // 7. Contoh Transaksi #0002 (Persis Sesuai Foto Struk Pengguna)
        $order2 = Order::updateOrCreate(
            ['invoice_number' => '#0002'],
            [
                'customer_id' => $custFitria->id,
                'customer_name' => 'FITRIA',
                'customer_phone' => '087713731989',
                'user_id' => $admin->id,
                'order_date' => now()->subDay()->setTime(14, 54),
                'delivery_date' => now()->setTime(14, 52),
                'subtotal' => 1000000,
                'discount_type' => 'percentage',
                'discount_rate' => 20,
                'discount_amount' => 200000,
                'net_total' => 800000,
                'advance_payment' => 0, // Advance CASH 0.00
                'payment_method' => 'cash',
                'balance_due' => 800000, // Balance Due 800,000 IDR
                'payment_status' => 'unpaid',
                'order_status' => 'ready_for_pickup',
                'notes' => 'Pesanan kacamata frame rimless + lensa blue cut UV',
            ]
        );

        // Rincian Item Order #0002
        OrderItem::firstOrCreate(
            ['order_id' => $order2->id, 'item_name' => 'Rimless frame'],
            [
                'product_id' => $createdProducts['FRM-RIMLESS-01']->id,
                'qty' => 1,
                'cost_price' => 320000,
                'unit_price' => 600000,
                'subtotal' => 600000,
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order2->id, 'item_name' => 'Lensa Blue Cut UV (Pasang)'],
            [
                'product_id' => $createdProducts['LNS-SV-BLUERAY']->id,
                'qty' => 1,
                'cost_price' => 190000,
                'unit_price' => 400000,
                'subtotal' => 400000,
            ]
        );

        // Resep Visus Order #0002 (Sesuai Foto Struk)
        OrderPrescription::updateOrCreate(
            ['order_id' => $order2->id],
            [
                'right_sph' => '-0.75',
                'right_cyl' => '0.00',
                'right_axis' => '-',
                'right_va' => '-',
                'right_pd' => '35',
                'left_sph' => '-0.75',
                'left_cyl' => '0.00',
                'left_axis' => '-',
                'left_va' => '-',
                'left_pd' => '35',
                'add_right' => '0.00',
                'add_left' => '0.00',
                'ipd' => '70',
                'frame_details' => 'Rimless frame',
                'lens_details' => 'Right: Blue Cut UV, Left: Blue Cut UV',
            ]
        );

        // Contoh Transaksi #0001 (Alda Nofa Syafira - Lensa Silinder & Add Sesuai Excel)
        $order1 = Order::updateOrCreate(
            ['invoice_number' => '#0001'],
            [
                'customer_id' => $custAlda->id,
                'customer_name' => 'ALDA NOFA SYAFIRA',
                'customer_phone' => '08123456789',
                'user_id' => $admin->id,
                'order_date' => now()->subDays(2)->setTime(11, 20),
                'delivery_date' => now()->subDay()->setTime(16, 00),
                'subtotal' => 850000,
                'discount_type' => 'nominal',
                'discount_rate' => 0,
                'discount_amount' => 50000,
                'net_total' => 800000,
                'advance_payment' => 500000, // DP 500rb
                'payment_method' => 'qris',
                'balance_due' => 300000, // Sisa 300rb
                'payment_status' => 'partial_dp',
                'order_status' => 'in_process',
                'notes' => 'Kacamata resep silinder + add baca dekat',
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order1->id, 'item_name' => 'Cat Eye Korean Acetate Fashion'],
            [
                'product_id' => $createdProducts['FRM-CAT-ACETATE']->id,
                'qty' => 1,
                'cost_price' => 180000,
                'unit_price' => 380000,
                'subtotal' => 380000,
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order1->id, 'item_name' => 'Lensa Progresif Blueray Digital'],
            [
                'product_id' => $createdProducts['LNS-PROG-BLUERAY']->id,
                'qty' => 1,
                'cost_price' => 280000,
                'unit_price' => 470000,
                'subtotal' => 470000,
            ]
        );

        OrderPrescription::updateOrCreate(
            ['order_id' => $order1->id],
            [
                'right_sph' => '-1.00',
                'right_cyl' => '-0.75',
                'right_axis' => '89°',
                'right_va' => '6/6',
                'right_pd' => '32',
                'left_sph' => '-1.00',
                'left_cyl' => '-0.75',
                'left_axis' => '150°',
                'left_va' => '6/6',
                'left_pd' => '32',
                'add_right' => '+1.50',
                'add_left' => '+1.50',
                'ipd' => '64',
                'frame_details' => 'Cat Eye Korean Acetate Frame',
                'lens_details' => 'Progresif Blueray Custom',
            ]
        );
    }
}
