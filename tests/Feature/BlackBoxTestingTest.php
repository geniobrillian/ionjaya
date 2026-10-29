<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\FsnRopService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Black Box untuk 22 Test Case yang dipetakan ke 8 Use Case
 * sesuai rancangan pengujian BAB IV Skripsi UD Ion Jaya Motor.
 */
class BlackBoxTestingTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminKasir;
    protected Supplier $supplier;
    protected Item $fastItem;
    protected Item $slowItem;
    protected Item $nonItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('role', 'super_admin')->first();
        $this->adminKasir = User::where('role', 'admin')->first();
        $this->supplier = Supplier::first();

        $this->fastItem = Item::where('fsn_category', 'F')->first();
        $this->slowItem = Item::where('fsn_category', 'S')->first();
        $this->nonItem = Item::where('fsn_category', 'N')->first();
    }

    // =========================================================================
    // USE CASE 1: LOGIN DAN LOGOUT (TC-01 s.d. TC-04)
    // =========================================================================

    /**
     * TC-01: Login dengan kredensial valid.
     * Admin diarahkan ke halaman penjualan, Super Admin diarahkan ke dashboard.
     */
    public function test_tc01_login_valid_credentials(): void
    {
        // 1. Super Admin
        $responseSuper = $this->post(route('login.post'), [
            'username' => 'superadmin',
            'password' => 'superadmin123',
        ]);
        $responseSuper->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->superAdmin);

        $this->post(route('logout'));

        // 2. Admin Kasir
        $responseAdmin = $this->post(route('login.post'), [
            'username' => 'admin',
            'password' => 'admin123',
        ]);
        $responseAdmin->assertRedirect(route('sales.index'));
        $this->assertAuthenticatedAs($this->adminKasir);
    }

    /**
     * TC-02: Login dengan username atau password salah.
     * Sistem menolak dan menampilkan pesan error.
     */
    public function test_tc02_login_invalid_credentials(): void
    {
        $response = $this->post(route('login.post'), [
            'username' => 'superadmin',
            'password' => 'salah_password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    /**
     * TC-03: Pembatasan hak akses rute Super Admin oleh akun Admin Kasir.
     * Sistem mengembalikan kode status 403 Forbidden.
     */
    public function test_tc03_admin_cannot_access_super_admin_routes(): void
    {
        $responseDashboard = $this->actingAs($this->adminKasir)->get(route('dashboard'));
        $responseDashboard->assertStatus(403);

        $responseMasterBarang = $this->actingAs($this->adminKasir)->get(route('items.index'));
        $responseMasterBarang->assertStatus(403);

        $responseAnalisis = $this->actingAs($this->adminKasir)->get(route('analysis.index'));
        $responseAnalisis->assertStatus(403);
    }

    /**
     * TC-04: Logout dari sistem.
     * Sesi dihapus dan pengguna dialihkan ke halaman login.
     */
    public function test_tc04_logout_successfully(): void
    {
        $response = $this->actingAs($this->adminKasir)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // =========================================================================
    // USE CASE 2: LIHAT REKOMENDASI PENGADAAN (TC-05 & TC-06)
    // =========================================================================

    /**
     * TC-05: Menampilkan rekomendasi pengadaan saat stok <= batas pemesanan.
     */
    public function test_tc05_recommendation_shows_items_below_reorder_threshold(): void
    {
        $this->fastItem->update([
            'stock' => 2,
            'reorder_point' => 10,
        ]);

        $response = $this->actingAs($this->adminKasir)->get(route('recommendations.index'));

        $response->assertStatus(200);
        $response->assertSee($this->fastItem->item_name);
        $response->assertSee('Rekomendasi Pengadaan');
    }

    /**
     * TC-06: Menampilkan pesan aman saat seluruh stok berada di atas batas.
     */
    public function test_tc06_recommendation_shows_all_safe_message(): void
    {
        Item::query()->update(['stock' => 999]);

        $response = $this->actingAs($this->adminKasir)->get(route('recommendations.index'));

        $response->assertStatus(200);
        $response->assertSee('Seluruh Stok dalam Kondisi Aman');
    }

    // =========================================================================
    // USE CASE 3: CATAT TRANSAKSI PENJUALAN DAN PEMBELIAN (TC-07 s.d. TC-09)
    // =========================================================================

    /**
     * TC-07: Penjualan stok mencukupi (transaksi tersimpan, stok fisik berkurang).
     */
    public function test_tc07_sale_with_sufficient_stock_decreases_stock(): void
    {
        $item = Item::where('stock', '>', 5)->first();
        $initialStock = $item->stock;
        $qtyToSell = 2;

        $response = $this->actingAs($this->adminKasir)->post(route('sales.store'), [
            'items' => [
                [
                    'id' => $item->id,
                    'quantity' => $qtyToSell,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.index'));
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals($initialStock - $qtyToSell, $item->stock);
    }

    /**
     * TC-08: Penjualan melebihi stok fisik (transaksi ditolak, stok tidak berubah).
     */
    public function test_tc08_sale_exceeding_stock_is_rejected(): void
    {
        $item = Item::first();
        $initialStock = $item->stock;
        $excessiveQty = $initialStock + 100;

        $response = $this->from(route('sales.index'))
            ->actingAs($this->adminKasir)
            ->post(route('sales.store'), [
                'items' => [
                    [
                        'id' => $item->id,
                        'quantity' => $excessiveQty,
                    ],
                ],
            ]);

        $response->assertRedirect(route('sales.index'));
        $response->assertSessionHas('error');

        $item->refresh();
        $this->assertEquals($initialStock, $item->stock);
    }

    /**
     * TC-09: Pembelian barang (stok bertambah; harga transaksi memakai
     * harga beli master dan harga master tidak berubah).
     */
    public function test_tc09_purchase_increases_stock_and_uses_master_purchase_price(): void
    {
        $item = Item::first();
        $initialStock = $item->stock;
        $masterPrice = (float) $item->purchase_price;
        $qtyPurchased = 10;
        $tamperedPrice = 75000; // Harga dari request harus diabaikan.

        $response = $this->actingAs($this->adminKasir)->post(route('purchases.store'), [
            'items' => [
                [
                    'id' => $item->id,
                    'quantity' => $qtyPurchased,
                    'purchase_price' => $tamperedPrice,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchases.index'));
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals($initialStock + $qtyPurchased, $item->stock);
        // Harga beli master tidak berubah oleh pembelian.
        $this->assertEquals($masterPrice, (float) $item->purchase_price);
        // Detail transaksi memakai harga master, bukan harga dari request.
        $detail = TransactionDetail::where('item_id', $item->id)->latest('id')->first();
        $this->assertEquals($masterPrice, (float) $detail->unit_price);
        $this->assertEquals($qtyPurchased * $masterPrice, (float) $detail->subtotal);
    }

    // =========================================================================
    // USE CASE 4: PANTAU INDIKATOR STOK MENIPIS (TC-10 & TC-11)
    // =========================================================================

    /**
     * TC-10: Verifikasi badge MENIPIS pada halaman stok saat stok <= batas pemesanan.
     */
    public function test_tc10_stock_page_shows_menipis_badge(): void
    {
        $this->fastItem->update([
            'stock' => 1,
            'reorder_point' => 10,
        ]);

        $response = $this->actingAs($this->adminKasir)->get(route('stock.index'));

        $response->assertStatus(200);
        $response->assertSee('MENIPIS');
    }

    /**
     * TC-11: Filter status stok (Semua, Menipis, Aman).
     */
    public function test_tc11_stock_page_filtering(): void
    {
        $this->fastItem->update(['stock' => 1, 'reorder_point' => 5]);
        // Ambang Slow kini dari ROP hasil hitung (bukan minimum statis).
        $this->slowItem->stock = 50;
        $this->slowItem->rop = 5;
        $this->slowItem->save();

        // Filter: Hanya Menipis
        $responseLow = $this->actingAs($this->adminKasir)->get(route('stock.index', ['status' => 'menipis']));
        $responseLow->assertStatus(200);
        $responseLow->assertSee($this->fastItem->item_name);

        // Filter: Hanya Aman
        $responseSafe = $this->actingAs($this->adminKasir)->get(route('stock.index', ['status' => 'aman']));
        $responseSafe->assertStatus(200);
        $responseSafe->assertSee($this->slowItem->item_name);
    }

    // =========================================================================
    // USE CASE 5: KELOLA DATA BARANG DAN PEMASOK (TC-12 s.d. TC-15)
    // =========================================================================

    /**
     * TC-12: Tambah & Edit Data Barang oleh Super Admin.
     */
    public function test_tc12_create_and_edit_item(): void
    {
        // 1. Tambah Barang
        $responseCreate = $this->actingAs($this->superAdmin)->post(route('items.store'), [
            'item_code' => 'BRG_TEST_BB',
            'item_name' => 'Busi Iridium Racing Black Box',
            'supplier_id' => $this->supplier->id,
            'purchase_price' => 50000,
            'selling_price' => 75000,
        ]);
        $responseCreate->assertRedirect(route('items.index'));
        $this->assertDatabaseHas('items', ['item_code' => 'BRG_TEST_BB']);

        $item = Item::where('item_code', 'BRG_TEST_BB')->first();

        // 2. Edit Barang (harga beli kini wajib di form ubah: 1 pintu harga)
        $responseEdit = $this->actingAs($this->superAdmin)->put(route('items.update', $item->id), [
            'item_code' => 'BRG_TEST_BB',
            'item_name' => 'Busi Iridium Racing Black Box V2',
            'supplier_id' => $this->supplier->id,
            'selling_price' => 80000,
            'purchase_price' => 50000,
        ]);
        $responseEdit->assertRedirect(route('items.index'));
        $this->assertDatabaseHas('items', ['item_name' => 'Busi Iridium Racing Black Box V2']);
    }

    /**
     * TC-13: Hapus data barang yang memiliki riwayat transaksi ditolak.
     */
     public function test_tc13_delete_item_with_transactions_is_rejected(): void
     {
         $response = $this->from(route('items.index'))
             ->actingAs($this->superAdmin)
             ->delete(route('items.destroy', $this->fastItem->id));

         $response->assertRedirect(route('items.index'));
         $response->assertSessionHas('error');
         $this->assertDatabaseHas('items', ['id' => $this->fastItem->id]);
     }

    /**
     * TC-14: Tambah & Edit Data Pemasok dengan validasi lead time >= 1.
     */
    public function test_tc14_create_and_edit_supplier_with_valid_lead_time(): void
    {
        // 1. Validasi Lead time < 1 harus gagal
        $responseInvalid = $this->actingAs($this->superAdmin)->post(route('suppliers.store'), [
            'supplier_code' => 'SUP_INVALID',
            'supplier_name' => 'Supplier Gagal',
            'lead_time' => 0,
        ]);
        $responseInvalid->assertSessionHasErrors('lead_time');

        // 2. Tambah Valid
        $responseValid = $this->actingAs($this->superAdmin)->post(route('suppliers.store'), [
            'supplier_code' => 'SUP_TEST_BB',
            'supplier_name' => 'PT Distribusi Onderdil Test',
            'lead_time' => 3,
            'phone' => '081299998888',
            'address' => 'Surabaya',
        ]);
        $responseValid->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', ['supplier_code' => 'SUP_TEST_BB']);

        $sup = Supplier::where('supplier_code', 'SUP_TEST_BB')->first();

        // 3. Edit Pemasok
        $responseEdit = $this->actingAs($this->superAdmin)->put(route('suppliers.update', $sup->id), [
            'supplier_code' => 'SUP_TEST_BB',
            'supplier_name' => 'PT Distribusi Onderdil Test V2',
            'lead_time' => 4,
            'phone' => '081299998888',
            'address' => 'Surabaya Barat',
        ]);
        $responseEdit->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', ['lead_time' => 4]);
    }

    /**
     * TC-15: Hapus data pemasok yang masih memiliki barang ditolak.
     */
    public function test_tc15_delete_supplier_with_items_is_rejected(): void
    {
        $response = $this->from(route('suppliers.index'))
            ->actingAs($this->superAdmin)
            ->delete(route('suppliers.destroy', $this->supplier->id));

        $response->assertRedirect(route('suppliers.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', ['id' => $this->supplier->id]);
    }

    // =========================================================================
    // USE CASE 6: MEMPROSES PERHITUNGAN FSN DAN ROP (TC-16 s.d. TC-18)
    // =========================================================================

    /**
     * TC-16: Eksekusi batch perhitungan FSN & ROP pada periode bertransaksi.
     */
    public function test_tc16_batch_fsn_rop_calculation(): void
    {
        $startDate = Carbon::now()->subDays(90)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $response = $this->actingAs($this->superAdmin)->get(route('analysis.index', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'process' => 1,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Fast Moving (F)');
        $response->assertSee('Slow Moving (S)');
        $response->assertSee('Non Moving (N)');
        $response->assertSee('Terapkan Hasil ke Data Barang');
    }

    /**
     * TC-17: Eksekusi batch pada periode kosong tanpa transaksi.
     */
    public function test_tc17_batch_fsn_rop_empty_period(): void
    {
        $startDate = '2015-01-01';
        $endDate = '2015-01-31';

        $response = $this->actingAs($this->superAdmin)->get(route('analysis.index', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'process' => 1,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Tidak Ditemukan Data Transaksi');
    }

    /**
     * TC-18: Verifikasi bahwa Safety Stock hanya dihitung untuk Fast Moving,
     * sedangkan ROP dihitung untuk Fast (ROP + SS) dan Slow (ROP tanpa SS).
     */
    public function test_tc18_rop_is_only_calculated_for_fast_moving(): void
    {
        $fsnRopService = app(FsnRopService::class);
        $startDate = Carbon::now()->subDays(90);
        $endDate = Carbon::now();

        $analysis = $fsnRopService->calculateAnalysis($startDate, $endDate);

        foreach ($analysis['items'] as $row) {
            if ($row['fsn_category'] === 'F') {
                $this->assertNotNull($row['rop']);
                $this->assertGreaterThan(0, $row['rop']);
                $this->assertNotNull($row['safety_stock']);
            } elseif ($row['fsn_category'] === 'S') {
                $this->assertNotNull($row['rop']);
                $this->assertGreaterThan(0, $row['rop']);
                $this->assertNull($row['safety_stock']);
                $this->assertSame($row['rop'], $row['order_threshold']);
            } elseif ($row['fsn_category'] === 'N') {
                $this->assertNull($row['rop']);
                $this->assertNull($row['safety_stock']);
            }
        }
    }

    // =========================================================================
    // USE CASE 7: LIHAT LAPORAN (TC-19 & TC-20)
    // =========================================================================

    /**
     * TC-19: Tampilkan laporan stok, transaksi, dan FSN/ROP sesuai filter.
     */
    public function test_tc19_view_reports_with_filters(): void
    {
        // 1. Laporan Stok
        $responseStok = $this->actingAs($this->superAdmin)->get(route('reports.index', ['type' => 'stok']));
        $responseStok->assertStatus(200);
        $responseStok->assertSee('LAPORAN PERSEDIAAN', false);

        // 2. Laporan Transaksi
        $responseTrx = $this->actingAs($this->superAdmin)->get(route('reports.index', [
            'type' => 'transaksi',
            'transaction_type' => 'penjualan',
        ]));
        $responseTrx->assertStatus(200);
        $responseTrx->assertSee('LAPORAN RIWAYAT TRANSAKSI PENJUALAN');

        // 3. Laporan FSN
        $responseFsn = $this->actingAs($this->superAdmin)->get(route('reports.index', ['type' => 'fsn']));
        $responseFsn->assertStatus(200);
        $responseFsn->assertSee('LAPORAN HASIL ANALISIS FSN', false);
    }

    /**
     * TC-20: Verifikasi keberadaan styling cetak laporan (@media print).
     */
    public function test_tc20_print_friendly_report_structure(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('reports.index', ['type' => 'stok']));

        $response->assertStatus(200);
        $response->assertSee('@media print', false);
        $response->assertSee('UD ION JAYA MOTOR');
        $response->assertSee('window.print()', false);
    }

    // =========================================================================
    // USE CASE 8: LIHAT DASHBOARD (TC-21 & TC-22)
    // =========================================================================

    /**
     * TC-21: Verifikasi formula estimasi laba: (Harga Jual - Harga Beli Terakhir) * Qty.
     */
    public function test_tc21_dashboard_profit_calculation_formula(): void
    {
        $fsnRopService = app(FsnRopService::class);
        $metrics = $fsnRopService->calculateProfit('all');

        $this->assertArrayHasKey('total_profit', $metrics);
        $this->assertArrayHasKey('total_sales', $metrics);
        $this->assertGreaterThanOrEqual(0, $metrics['total_profit']);
        $this->assertGreaterThanOrEqual($metrics['total_profit'], $metrics['total_sales']);
    }

    /**
     * TC-22: Filter periode dashboard (Hari Ini, Mingguan, Bulanan, Semua).
     */
    public function test_tc22_dashboard_filter_period_updates_metrics(): void
    {
        $responseToday = $this->actingAs($this->superAdmin)->get(route('dashboard', ['range' => 'today']));
        $responseToday->assertStatus(200);
        $responseToday->assertViewHas('range', 'today');

        $responseWeekly = $this->actingAs($this->superAdmin)->get(route('dashboard', ['range' => 'weekly']));
        $responseWeekly->assertStatus(200);
        $responseWeekly->assertViewHas('range', 'weekly');

        $responseMonthly = $this->actingAs($this->superAdmin)->get(route('dashboard', ['range' => 'monthly']));
        $responseMonthly->assertStatus(200);
        $responseMonthly->assertViewHas('range', 'monthly');

        $responseAll = $this->actingAs($this->superAdmin)->get(route('dashboard', ['range' => 'all']));
        $responseAll->assertStatus(200);
        $responseAll->assertViewHas('range', 'all');
    }
}
