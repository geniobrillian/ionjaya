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

    /**
     * Katalog pembelian menampilkan batas pesan tiap barang dan filter
     * cepat Perlu dibeli (stok sudah mencapai atau di bawah batas pesan).
     */
    public function test_purchase_catalog_shows_order_threshold_and_need_filter(): void
    {
        $response = $this->actingAs($this->admin)->get(route('purchases.index'));

        $response->assertStatus(200);
        $response->assertSee('Batas pesan');
        $response->assertSee('Perlu dibeli');
        $response->assertSee('data-need=', false);
        // Keterangan di bawah dropdown: default (Semua barang) tampil
        // sebagai teks, varian Perlu dibeli ada di skrip pengubahnya.
        $response->assertSee('Menampilkan semua barang dari pemasok yang dipilih.');
        $response->assertSee('Menampilkan barang yang sudah waktunya dibeli.');
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

    public function test_purchase_transaction_requires_supplier(): void
    {
        $item = Item::first();

        $response = $this->actingAs($this->admin)->post('/pembelian', [
            'items' => [
                ['id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $response->assertSessionHasErrors('supplier_id');
    }

    public function test_purchase_transaction_rejects_item_without_supplier_relation(): void
    {
        // BRG003 seeder hanya dipasok SUP002 (id 2); dibeli dari SUP001 (id 1) harus ditolak.
        $item = Item::where('item_code', 'BRG003')->first();
        $initialStock = $item->stock;

        $response = $this->actingAs($this->admin)->post('/pembelian', [
            'supplier_id' => 1,
            'items' => [
                ['id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $response->assertSessionHas('error');
        $item->refresh();
        $this->assertEquals($initialStock, $item->stock);
    }

    public function test_purchase_history_shows_supplier_name(): void
    {
        $item = Item::first();
        $supplier = Supplier::first();

        $this->actingAs($this->admin)->post('/pembelian', [
            'supplier_id' => $supplier->id,
            'items' => [
                ['id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $response = $this->actingAs($this->admin)->get('/pembelian');
        $response->assertStatus(200);
        $response->assertSee('<th class="py-3 px-4">Pemasok</th>', false);
        $response->assertSee($supplier->supplier_name);
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

    public function test_stock_perlu_filter_shows_habis_first_then_menipis(): void
    {
        // Seeder tidak punya barang stok 0, jadi buat satu barang habis.
        Item::create([
            'item_code' => 'BRG_HABIS',
            'item_name' => 'Barang Habis Uji',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'stock' => 0,
            'fsn_category' => 'F',
            'rop' => 8,
        ]);

        $response = $this->actingAs($this->admin)->get('/stok?status=perlu');
        $response->assertStatus(200);
        $response->assertSee('Perlu Pengadaan');
        $response->assertSee('Barang Habis Uji');
        // BRG001 seeder: stok 3 di bawah ROP 8 -> menipis.
        $response->assertSee('Kampas Rem Depan Honda Beat');
        // Habis (paling gawat) tampil sebelum menipis.
        $response->assertSeeInOrder(['Barang Habis Uji', 'Kampas Rem Depan Honda Beat']);
    }

    public function test_stock_banner_shows_procurement_breakdown_and_links_to_perlu(): void
    {
        // Seeder punya barang menipis (mis. BRG001) sehingga banner tampil.
        $response = $this->actingAs($this->admin)->get('/stok');
        $response->assertStatus(200);
        $response->assertSee('Pemberitahuan Persediaan');
        $response->assertSee('yang perlu pengadaan');
        $response->assertSee('Lihat Barang yang Perlu Pengadaan');
        $response->assertSee('/stok?status=perlu', false);
    }
}
