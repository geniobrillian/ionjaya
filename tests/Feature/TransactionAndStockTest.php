<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionAndStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('role', 'admin')->first();
    }

    public function test_sale_transaction_can_be_created_and_deducts_stock(): void
    {
        $item = Item::where('stock', '>', 5)->first();
        $initialStock = $item->stock;
        $qtyToSell = 2;

        $response = $this->actingAs($this->admin)->post('/penjualan', [
            'items' => [
                [
                    'id' => $item->id,
                    'quantity' => $qtyToSell,
                ],
            ],
        ]);

        $response->assertRedirect('/penjualan');
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals($initialStock - $qtyToSell, $item->stock);

        $this->assertDatabaseHas('transactions', [
            'transaction_type' => 'penjualan',
            'user_id' => $this->admin->id,
            'total' => $qtyToSell * $item->selling_price,
        ]);
    }

    public function test_sale_transaction_is_rejected_if_stock_is_insufficient(): void
    {
        $item = Item::first();
        $initialStock = $item->stock;
        $excessiveQty = $initialStock + 100;

        $response = $this->actingAs($this->admin)->post('/penjualan', [
            'items' => [
                [
                    'id' => $item->id,
                    'quantity' => $excessiveQty,
                ],
            ],
        ]);

        $response->assertSessionHas('error');

        $item->refresh();
        $this->assertEquals($initialStock, $item->stock);
    }

    public function test_purchase_transaction_can_be_created_and_increases_stock_and_records_supplier(): void
    {
        $item = Item::first();
        $initialStock = $item->stock;
        $initialPrice = (float) $item->purchase_price;
        $qtyPurchased = 15;
        $supplier = Supplier::first();

        $response = $this->actingAs($this->admin)->post('/pembelian', [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'id' => $item->id,
                    'quantity' => $qtyPurchased,
                    // Harga dari request sengaja beda: pembelian harus
                    // tetap memakai harga master (1 pintu harga).
                    'purchase_price' => $initialPrice + 10000,
                ],
            ],
        ]);

        $response->assertRedirect('/pembelian');
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals($initialStock + $qtyPurchased, $item->stock);
        // Harga master TIDAK berubah lewat pembelian (perilaku Tahap 4;
        // versi lama test ini keliru menuntut harga berubah).
        $this->assertEquals($initialPrice, (float) $item->purchase_price);

        $this->assertDatabaseHas('transactions', [
            'transaction_type' => 'pembelian',
            'user_id' => $this->admin->id,
            'supplier_id' => $supplier->id,
            'total' => $qtyPurchased * $initialPrice,
        ]);
    }

    public function test_purchase_transaction_can_be_created_without_supplier_id(): void
    {
        $item = Item::first();

        $response = $this->actingAs($this->admin)->post('/pembelian', [
            'items' => [
                ['id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect('/pembelian');
        $response->assertSessionHas('success');
    }

    public function test_purchase_history_does_not_have_supplier_column(): void
    {
        $response = $this->actingAs($this->admin)->get('/pembelian');
        $response->assertStatus(200);
        $response->assertDontSee('<th class="py-3 px-4">Pemasok</th>', false);
    }

    public function test_stock_monitoring_page_shows_correct_indicators_and_filters(): void
    {
        $response = $this->actingAs($this->admin)->get('/stok');
        $response->assertStatus(200);
        $response->assertSee('Stok Barang');
        $response->assertSee('MENIPIS');
        $response->assertSee('AMAN');

        // Filter status menipis
        $responseMenipis = $this->actingAs($this->admin)->get('/stok?status=menipis');
        $responseMenipis->assertStatus(200);
        $responseMenipis->assertSee('MENIPIS');

        // Filter status aman
        $responseAman = $this->actingAs($this->admin)->get('/stok?status=aman');
        $responseAman->assertStatus(200);
        $responseAman->assertSee('AMAN');
    }

    public function test_stock_menipis_filter_shows_only_low_stock_items_without_suggested_order_column(): void
    {
        $response = $this->actingAs($this->admin)->get('/stok?status=menipis');
        $response->assertStatus(200);
        $response->assertSee('Stok Barang');
        $response->assertSee('MENIPIS');

        // Memastikan tidak ada kolom terlarang 'Jumlah Pesan Disarankan' sesuai AGENTS.md
        $response->assertDontSee('Jumlah Pesan Disarankan');
        $response->assertDontSee('Jumlah Disarankan');
    }

    public function test_stock_menipis_filter_shows_empty_message_when_no_low_stock(): void
    {
        // Ubah semua stok barang menjadi sangat tinggi di atas batas ROP
        Item::query()->update(['stock' => 999]);

        $response = $this->actingAs($this->admin)->get('/stok?status=menipis');
        $response->assertStatus(200);
        $response->assertSee('Tidak ada data barang yang sesuai dengan kriteria filter.');
    }
}
