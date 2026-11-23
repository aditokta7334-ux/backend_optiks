<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpticsSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Category $category;
    private Unit $unit;
    private Product $product;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Arjuna Kasir',
            'email' => 'kasir@arjunamayway.id',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $this->token = $this->user->createApiToken();

        $this->category = Category::create([
            'code' => 'FRAME',
            'name' => 'Frame Kacamata',
        ]);

        $this->unit = Unit::create(['name' => 'Pcs']);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sku' => 'TEST-FRM-01',
            'name' => 'Rimless Titanium Test',
            'cost_price' => 200000,
            'selling_price' => 500000,
            'current_stock' => 10,
            'min_stock_alert' => 2,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-TEST',
            'name' => 'Supplier Test',
        ]);
    }

    public function test_can_fetch_lens_visus_options(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/master/lens-visus-options');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['spheris', 'cylindris', 'adds', 'lens_types']]);
    }

    public function test_can_process_pos_checkout_with_prescription_and_deduct_stock(): void
    {
        $checkoutData = [
            'customer_name' => 'Fitria Test',
            'customer_phone' => '087713731989',
            'delivery_date' => now()->addDay()->toIso8601String(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'item_name' => 'Rimless Titanium Test',
                    'qty' => 2,
                    'unit_price' => 500000,
                ],
            ],
            'discount_type' => 'nominal',
            'discount_amount' => 100000,
            'advance_payment' => 400000, // Subtotal 1jt - diskon 100k = net 900k, DP 400k, sisa 500k
            'payment_method' => 'cash',
            'prescription' => [
                'right_sph' => '-0.75',
                'right_cyl' => '0.00',
                'right_pd' => '35',
                'left_sph' => '-0.75',
                'left_cyl' => '0.00',
                'left_pd' => '35',
                'ipd' => '70',
                'frame_details' => 'Rimless frame',
                'lens_details' => 'Blue Cut UV',
            ],
        ];

        $response = $this->withToken($this->token)->postJson('/api/pos/checkout', $checkoutData);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subtotal', '1000000.00')
            ->assertJsonPath('data.discount_amount', '100000.00')
            ->assertJsonPath('data.net_total', '900000.00')
            ->assertJsonPath('data.advance_payment', '400000.00')
            ->assertJsonPath('data.balance_due', '500000.00')
            ->assertJsonPath('data.payment_status', 'partial_dp')
            ->assertJsonPath('data.prescription.ipd', '70');

        // Pastikan stok terpotong: 10 - 2 = 8
        $this->assertEquals(8, $this->product->fresh()->current_stock);

        // Uji pelunasan sisa tagihan (settle balance due)
        $orderId = $response->json('data.id');
        $settleResponse = $this->withToken($this->token)->postJson("/api/pos/orders/{$orderId}/settle", [
            'amount_paid' => 500000,
            'payment_method' => 'cash',
        ]);

        $settleResponse->assertOk()
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_can_process_goods_receipt_in_and_increase_stock(): void
    {
        $initialStock = $this->product->current_stock;

        $response = $this->withToken($this->token)->postJson('/api/inventory/goods-receipts', [
            'supplier_id' => $this->supplier->id,
            'receipt_date' => now()->toDateString(),
            'reference_invoice' => 'INV-SUP-1234',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 5,
                    'buy_price' => 220000,
                ],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        // Stok bertambah 10 + 5 = 15
        $this->assertEquals($initialStock + 5, $this->product->fresh()->current_stock);
        $this->assertEquals('220000.00', $this->product->fresh()->cost_price);
    }

    public function test_can_perform_stock_opname_and_approve_adjustment(): void
    {
        // Stok saat ini = 10, hasil hitungan fisik = 12 (selisih +2)
        $opnameResponse = $this->withToken($this->token)->postJson('/api/inventory/stock-opnames', [
            'opname_date' => now()->toDateString(),
            'notes' => 'Audit mingguan etalase',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'physical_stock' => 12,
                    'adjustment_reason' => 'Koreksi kelebihan stok fisik',
                ],
            ],
        ]);

        $opnameResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total_difference_items', 1);

        $opnameId = $opnameResponse->json('data.id');

        // Approve STO
        $approveResponse = $this->withToken($this->token)->postJson("/api/inventory/stock-opnames/{$opnameId}/approve");

        $approveResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        // Pastikan stok sistem sudah disesuaikan menjadi 12
        $this->assertEquals(12, $this->product->fresh()->current_stock);
    }

    public function test_dashboard_stats_endpoint(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'today_sales',
                    'today_orders_count',
                    'month_sales',
                    'total_balance_due',
                    'pending_lab_count',
                    'ready_for_pickup_count',
                    'low_stock_count',
                    'chart_sales',
                    'pipeline',
                ],
            ]);
    }
}
