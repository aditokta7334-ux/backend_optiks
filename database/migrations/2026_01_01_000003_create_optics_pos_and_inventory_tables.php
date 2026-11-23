<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Kategori & Satuan
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('default_unit', 30)->default('Pcs');
            $table->string('icon', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->timestamps();
        });

        // 2. Supplier
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        // 3. Produk (Frame, Lensa, Aksesoris, Cairan, Softlens)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('sku', 50)->unique();
            $table->string('barcode', 60)->nullable()->index();
            $table->string('name', 200);
            $table->string('brand', 100)->nullable();
            $table->decimal('cost_price', 14, 2)->default(0); // HPP
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->integer('current_stock')->default(0);
            $table->integer('min_stock_alert')->default(5);
            $table->string('item_type', 30)->default('general'); // frame, lens, accessory
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Data Pasien / Pelanggan
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 30)->unique();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable()->index();
            $table->integer('age')->nullable();
            $table->text('address')->nullable();
            $table->text('medical_notes')->nullable(); // Riwayat sakit/keluhan
            $table->timestamps();
        });

        // 5. Transaksi POS (Orders / Invoices)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique(); // misal #0002
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Kasir
            $table->dateTime('order_date');
            $table->dateTime('delivery_date')->nullable(); // Tanggal estimasi kacamata selesai
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->string('discount_type', 20)->default('nominal'); // percentage / nominal
            $table->decimal('discount_rate', 6, 2)->default(0); // misal 20%
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->decimal('advance_payment', 14, 2)->default(0); // Uang Muka / DP
            $table->string('payment_method', 30)->default('cash'); // cash, transfer, qris, debit
            $table->decimal('balance_due', 14, 2)->default(0); // Sisa Tagihan
            $table->string('payment_status', 30)->default('unpaid'); // unpaid, partial_dp, paid
            $table->string('order_status', 30)->default('completed'); // pending_lab, in_process, ready_for_pickup, completed, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_name', 200);
            $table->integer('qty')->default(1);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->timestamps();
        });

        // 6. Resep Visus Khusus Transaksi Optik (Sesuai Foto Nota & Excel)
        Schema::create('order_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('right_sph', 20)->nullable(); // e.g. -0.75
            $table->string('right_cyl', 20)->nullable(); // e.g. 0.00 / -0.50
            $table->string('right_axis', 20)->nullable(); // e.g. 89°
            $table->string('right_va', 20)->nullable();
            $table->string('right_pd', 20)->nullable(); // e.g. 35
            $table->string('left_sph', 20)->nullable();
            $table->string('left_cyl', 20)->nullable();
            $table->string('left_axis', 20)->nullable();
            $table->string('left_va', 20)->nullable();
            $table->string('left_pd', 20)->nullable(); // e.g. 35
            $table->string('add_right', 20)->nullable(); // e.g. +1.50
            $table->string('add_left', 20)->nullable();
            $table->string('ipd', 20)->nullable(); // e.g. 70
            $table->string('frame_details', 200)->nullable(); // e.g. Rimless frame
            $table->string('lens_details', 250)->nullable(); // e.g. Right: Blue Cut UV, Left: Blue Cut UV
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 7. Penerimaan Barang Masuk (Goods Receipt / IN)
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('receipt_date');
            $table->string('reference_invoice', 60)->nullable(); // No surat jalan/faktur supplier
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('qty')->default(1);
            $table->decimal('buy_price', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->timestamps();
        });

        // 8. Kartu Mutasi Stok (Stock History / Log)
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->dateTime('mutation_date');
            $table->string('mutation_type', 30); // IN_PURCHASE, OUT_SALE, ADJUSTMENT_OPNAME, DAMAGE_LOSS, RETURN
            $table->string('reference_type', 40)->nullable(); // App\Models\Order, App\Models\GoodsReceipt, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->integer('qty_in')->default(0);
            $table->integer('qty_out')->default(0);
            $table->integer('balance_stock')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Stock Opname (STO)
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number', 40)->unique();
            $table->date('opname_date');
            $table->foreignId('conducted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('draft'); // draft, approved
            $table->integer('total_items_checked')->default(0);
            $table->integer('total_difference_items')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('system_stock')->default(0);
            $table->integer('physical_stock')->default(0);
            $table->integer('difference')->default(0); // physical - system
            $table->string('adjustment_reason', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_mutations');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('order_prescriptions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
    }
};
