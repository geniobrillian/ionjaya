<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\FsnRopService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminKasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('role', 'super_admin')->first();
        $this->adminKasir = User::where('role', 'admin')->first();
    }

    /**
     * Test 1: Super Admin dapat mengakses dashboard utama.
     */
    public function test_super_admin_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.index');
        $response->assertSee('Ringkasan Toko', false);
        $response->assertSee('Estimasi Laba');
        $response->assertSee('Total Penjualan');
        $response->assertSee('Peringatan Stok');
    }

    /**
     * Kartu Peringatan Stok bisa diklik menuju daftar Perlu Pengadaan
     * saat ada barang yang stoknya mencapai batas; saat tidak ada,
     * card tetap div biasa (tanpa tautan).
     */
    public function test_stock_warning_card_links_to_perlu_filter_when_low_stock_exists(): void
    {
        // Seeder punya barang menipis (mis. BRG001) sehingga jumlah > 0.
        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('title="Lihat barang yang perlu pengadaan"', false);
        $response->assertSee('/stok?status=perlu', false);
    }

    /**
     * Saat jumlah > 0, card Peringatan Stok memakai tint merah yang sama
     * dengan hero alert Perlu Pengadaan (bg-red-50/border-red-200).
     * Dicari lewat title-nya sehingga hanya card yang terperiksa.
     */
    public function test_stock_warning_card_uses_alert_tint_when_low_stock_exists(): void
    {
        // Seeder punya barang menipis (mis. BRG001) sehingga jumlah > 0.
        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        preg_match_all('/<a\b[^>]*>/', $response->getContent(), $anchors);
        $card = collect($anchors[0])->first(
            fn ($tag) => str_contains($tag, 'title="Lihat barang yang perlu pengadaan"')
        );
        $this->assertNotNull($card, 'Card Peringatan Stok tidak ditemukan.');
        $this->assertStringContainsString('bg-red-50', $card);
        $this->assertStringContainsString('border-red-200', $card);
    }

    public function test_stock_warning_card_is_not_a_link_when_no_low_stock(): void
    {
        Item::query()->update(['stock' => 999]);

        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Seluruh stok dalam kondisi aman');
        $response->assertDontSee('title="Lihat barang yang perlu pengadaan"', false);
    }

    /**
     * Sidebar Laporan adalah tautan langsung (seperti Dashboard),
     * bukan dropdown: menunya tetap ada, tapi tanpa ringkasan grup.
     */
    public function test_sidebar_laporan_is_direct_link_not_dropdown(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Laporan');
        // Ringkasan dropdown lama (<span>Laporan</span>) sudah tidak ada.
        $response->assertDontSee('<span>Laporan</span>', false);
        $response->assertSee('/laporan', false);
    }

    /**
     * Card laba menampilkan estimasi laba (baris margin disembunyikan
     * pada perapian label 2026-11-03, jadi tidak lagi diperiksa),
     * dan setiap hari pada grafik membawa data hover (penjualan + laba)
     * untuk kartu tooltip.
     */
    public function test_dashboard_profit_card_shows_estimated_profit(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Estimasi Laba');
        $response->assertSee('Berdasarkan harga beli terakhir');
    }

    /**
     * Grafik memakai kartu tooltip di atas kursor (bukan baris rincian):
     * kalimat petunjuk lama hilang, ruangnya tetap ada, setiap hari
     * membawa judul tanggal lengkap + data penjualan/laba, dan elemen
     * kartu tooltip tersedia.
     */
    public function test_dashboard_chart_days_carry_hover_data(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Arahkan kursor ke salah satu hari');
        $response->assertSee('data-title=', false);
        $response->assertSee('data-sales=', false);
        $response->assertSee('data-profit=', false);
        $response->assertSee('id="chart-tooltip"', false);
    }

    /**
     * Regresi: grafik tren harian harus menghitung penjualan nyata.
     * (Bug lama: tren selalu nol meski ada transaksi. Tes ini membandingkan
     * selisih sebelum/sesudah menambah transaksi, jadi kebal terhadap
     * data seeder dan gagal bila tren mengabaikan data baru.)
     */
    public function test_daily_trend_counts_recent_sales(): void
    {
        $service = app(FsnRopService::class);
        $item = Item::first();
        $this->assertNotNull($item);

        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();
        $purchasePrice = (float) $item->purchase_price;

        $before = collect($service->calculateProfit('weekly')['daily_trend'])->keyBy('date');

        $txYesterday = Transaction::create([
            'user_id' => $this->superAdmin->id,
            'transaction_type' => 'penjualan',
            'date' => $yesterday->toDateString(),
            'total' => 200000,
        ]);
        TransactionDetail::create([
            'transaction_id' => $txYesterday->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'unit_price' => 100000,
            'subtotal' => 200000,
        ]);

        $txToday = Transaction::create([
            'user_id' => $this->superAdmin->id,
            'transaction_type' => 'penjualan',
            'date' => $today->toDateString(),
            'total' => 50000,
        ]);
        TransactionDetail::create([
            'transaction_id' => $txToday->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'subtotal' => 50000,
        ]);

        $after = collect($service->calculateProfit('weekly')['daily_trend'])->keyBy('date');

        $this->assertEqualsWithDelta($before[$yesterday->toDateString()]['sales'] + 200000, $after[$yesterday->toDateString()]['sales'], 0.01);
        $this->assertEqualsWithDelta($before[$yesterday->toDateString()]['profit'] + (200000 - 2 * $purchasePrice), $after[$yesterday->toDateString()]['profit'], 0.01);
        $this->assertEqualsWithDelta($before[$today->toDateString()]['sales'] + 50000, $after[$today->toDateString()]['sales'], 0.01);
        $this->assertEqualsWithDelta($before[$today->toDateString()]['profit'] + (50000 - 1 * $purchasePrice), $after[$today->toDateString()]['profit'], 0.01);
    }

    /**
     * Test 2: Filter rentang waktu pada dashboard.
     */
    public function test_super_admin_can_filter_dashboard_time_range(): void
    {
        $responseWeekly = $this->actingAs($this->superAdmin)->get(route('dashboard', ['range' => 'weekly']));
        $responseWeekly->assertStatus(200);

        $responseMonthly = $this->actingAs($this->superAdmin)->get(route('dashboard', ['range' => 'monthly']));
        $responseMonthly->assertStatus(200);

        $responseToday = $this->actingAs($this->superAdmin)->get(route('dashboard', ['range' => 'today']));
        $responseToday->assertStatus(200);

        $responseAll = $this->actingAs($this->superAdmin)->get(route('dashboard', ['range' => 'all']));
        $responseAll->assertStatus(200);
    }

    /**
     * Test 3: Admin kasir ditolak saat mengakses dashboard.
     */
    public function test_admin_cashier_cannot_access_dashboard(): void
    {
        $response = $this->actingAs($this->adminKasir)->get(route('dashboard'));

        $response->assertStatus(403);
    }

    /**
     * Test 4: Super Admin dapat mengakses laporan stok barang.
     */
    public function test_super_admin_can_access_stock_report(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('reports.index', ['type' => 'stok']));

        $response->assertStatus(200);
        $response->assertViewIs('reports.index');
        $response->assertSee('LAPORAN PERSEDIAAN', false);
        $response->assertSee('UD ION JAYA MOTOR');
    }

    /**
     * Status Laporan Stok memakai aturan yang sama dengan halaman Stok:
     * stok 0 tampil HABIS (bukan MENIPIS seperti aturan biner lama), dan
     * barang yang belum pernah dianalisis tampil BELUM (bukan AMAN).
     */
    public function test_stock_report_status_matches_stock_page_status(): void
    {
        Item::first()->update(['stock' => 0]);
        Item::create([
            'item_code' => 'BRG999',
            'item_name' => 'Barang Belum Dianalisis Uji',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'stock' => 10,
            'fsn_category' => null,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('reports.index', ['type' => 'stok']));

        $response->assertStatus(200);
        $response->assertSee('HABIS');
        $response->assertSee('BELUM');
    }

    /**
     * Test 5: Super Admin dapat mengakses laporan transaksi dengan filter tanggal dan jenis.
     */
    public function test_super_admin_can_access_transaction_report_with_filters(): void
    {
        $startDate = Carbon::now()->subDays(90)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $response = $this->actingAs($this->superAdmin)->get(route('reports.index', [
            'type' => 'transaksi',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'transaction_type' => 'penjualan',
        ]));

        $response->assertStatus(200);
        $response->assertSee('LAPORAN RIWAYAT TRANSAKSI PENJUALAN');
    }

    /**
     * Test 6: Super Admin dapat mengakses laporan persediaan (dulu: laporan
     * analisis FSN & ROP). Judul dan header memakai bahasa owner; keterangan
     * metode tetap tercantum kecil di bawah kop.
     */
    public function test_super_admin_can_access_fsn_rop_report(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('reports.index', ['type' => 'fsn']));

        $response->assertStatus(200);
        $response->assertSee('LAPORAN PERSEDIAAN', false);
        $response->assertSee('Analisis berdasarkan metode FSN dan ROP');
        $response->assertSee('Pergerakan');
        $response->assertDontSee('Laporan Hasil FSN');
        $response->assertSee('UD ION JAYA MOTOR');
    }

    /**
     * Hero dashboard DISEMBUNYIKAN SEMENTARA (2026-11-02, permintaan
     * owner): dibungkus komentar Blade, bukan dihapus. Bahkan saat ada
     * barang yang perlu pengadaan, tombol hero tidak tampil — jalur
     * menuju daftarnya tetap lewat card Peringatan Stok.
     */
    public function test_dashboard_hero_hidden_temporarily_even_when_needed(): void
    {
        Item::first()->update(['stock' => 0]);

        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Lihat Barang yang Perlu Pengadaan');
    }

    /**
     * Filter "Bulan Lalu": label periode menunjukkan satu bulan kalender
     * penuh sebelum bulan berjalan.
     */
    public function test_dashboard_last_month_filter_shows_previous_calendar_month(): void
    {
        $lastMonth = now()->subMonthNoOverflow();
        $expectedStart = $lastMonth->copy()->startOfMonth()->translatedFormat('d M Y');
        $expectedEnd = $lastMonth->copy()->endOfMonth()->translatedFormat('d M Y');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('dashboard', ['range' => 'last_month']));

        $response->assertStatus(200);
        $response->assertSee('Bulan Lalu');
        $response->assertSee($expectedStart);
        $response->assertSee($expectedEnd);
    }

    public function test_dashboard_hero_hidden_when_no_procurement_needed(): void
    {
        Item::query()->update(['stock' => 999]);

        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Lihat Barang yang Perlu Pengadaan');
    }

    /**
     * Urutan card dashboard: Peringatan Stok paling kiri, lalu Jumlah
     * Transaksi, Estimasi Laba, dan Total Penjualan.
     */
    public function test_dashboard_cards_order_starts_with_stock_warning(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSeeInOrder(['Peringatan Stok', 'Jumlah Transaksi', 'Estimasi Laba', 'Total Penjualan']);
    }

    /**
     * Test 7: Admin kasir ditolak saat mengakses laporan.
     */
    public function test_admin_cashier_cannot_access_reports(): void
    {
        $response = $this->actingAs($this->adminKasir)->get(route('reports.index'));

        $response->assertStatus(403);
    }
}
