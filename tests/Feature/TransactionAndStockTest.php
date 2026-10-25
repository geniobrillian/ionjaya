<?php

namespace Tests\Feature;

use App\Models\Item;
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

    public function test_purchase_transaction_can_be_created_and_increases_stock_and_updates_cost(): void
    {
        $item = Item::first();
        $initialStock = $item->stock;
        $qtyPurchased = 15;
        $newCostPrice = 75000;

        $response = $this->actingAs($this->admin)->post('/pembelian', [
            'items' => [
                [
                    'id' => $item->id,
                    'quantity' => $qtyPurchased,
                    'purchase_price' => $newCostPrice,
                ],
            ],
        ]);

        $response->assertRedirect('/pembelian');
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals($initialStock + $qtyPurchased, $item->stock);
        $this->assertEquals($newCostPrice, (float) $item->purchase_price);

        $this->assertDatabaseHas('transactions', [
            'transaction_type' => 'pembelian',
            'user_id' => $this->admin->id,
            'total' => $qtyPurchased * $newCostPrice,
        ]);
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

    public function test_recommendation_page_shows_only_low_stock_items_without_suggested_order_column(): void
    {
        $response = $this->actingAs($this->admin)->get('/rekomendasi');
        $response->assertStatus(200);
        $response->assertSee('Rekomendasi Pengadaan');
        $response->assertSee('Waktu Tunggu');

        // Memastikan tidak ada kolom terlarang 'Jumlah Pesan Disarankan' sesuai AGENTS.md
        $response->assertDontSee('Jumlah Pesan Disarankan');
        $response->assertDontSee('Jumlah Disarankan');
    }

    public function test_recommendation_page_shows_safe_message_when_no_low_stock(): void
    {
        // Ubah semua stok barang menjadi sangat tinggi di atas batas ROP
        Item::query()->update(['stock' => 999]);

        $response = $this->actingAs($this->admin)->get('/rekomendasi');
        $response->assertStatus(200);
        $response->assertSee('Seluruh Stok dalam Kondisi Aman');
    }
}
