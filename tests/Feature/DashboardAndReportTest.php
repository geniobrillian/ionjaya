<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Transaction;
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
        $response->assertSee('Ringkasan Operasional', false);
        $response->assertSee('Estimasi Laba Kotor');
        $response->assertSee('Total Penjualan');
        $response->assertSee('Peringatan Stok');
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
     * Test 6: Super Admin dapat mengakses laporan analisis FSN & ROP.
     */
    public function test_super_admin_can_access_fsn_rop_report(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('reports.index', ['type' => 'fsn']));

        $response->assertStatus(200);
        $response->assertSee('LAPORAN HASIL ANALISIS FSN', false);
        $response->assertSee('UD ION JAYA MOTOR');
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
