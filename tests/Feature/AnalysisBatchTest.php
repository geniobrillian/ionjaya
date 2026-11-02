<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->superAdmin = User::where('role', 'super_admin')->first();
    }

    public function test_super_admin_can_view_analysis_page(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/analisis-fsn-rop');

        $response->assertStatus(200);
        $response->assertSee('Analisis Persediaan');
        $response->assertSee('Periode Analisis');
    }

    public function test_analysis_batch_process_displays_fsn_and_rop_results(): void
    {
        $startDate = Carbon::now()->subDays(90)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $response = $this->actingAs($this->superAdmin)->get("/analisis-fsn-rop?start_date={$startDate}&end_date={$endDate}&process=1");

        $response->assertStatus(200);
        $response->assertSee('Fast Moving (F)');
        $response->assertSee('Slow Moving (S)');
        $response->assertSee('Non Moving (N)');
        $response->assertSee('Hasil Analisis Persediaan');
        $response->assertSee('Terapkan Hasil Analisis');
    }

    /**
     * Tabel hasil menampilkan ringkasan jumlah per kategori dan kolom Stok
     * berdampingan dengan Batas Pesan.
     */
    public function test_analysis_results_show_category_summary_and_stock_column(): void
    {
        $startDate = Carbon::now()->subDays(90)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $response = $this->actingAs($this->superAdmin)->get("/analisis-fsn-rop?start_date={$startDate}&end_date={$endDate}&process=1");

        $response->assertStatus(200);
        $response->assertSee('Barang Cepat Terjual');
        $response->assertSee('Barang Lambat Terjual');
        $response->assertSee('Barang Jarang/Tidak Terjual');
        $response->assertSee('<th class="py-3.5 px-4 text-center w-24">Stok</th>', false);
    }

    /**
     * Modal konfirmasi Terapkan memakai kalimat natural (tanpa istilah
     * FSN/ROP di bodinya).
     */
    public function test_apply_modal_uses_owner_language(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/analisis-fsn-rop');

        $response->assertStatus(200);
        $response->assertSee('Sistem akan memperbarui kategori barang dan batas pesan');
        $response->assertDontSee('kategori FSN, stok pengaman, dan batas pesan ROP');
    }

    public function test_analysis_batch_handles_empty_period_gracefully(): void
    {
        // Tanggal di masa lalu yang dipastikan tidak memiliki transaksi
        $startDate = '2015-01-01';
        $endDate = '2015-01-31';

        $response = $this->actingAs($this->superAdmin)->get("/analisis-fsn-rop?start_date={$startDate}&end_date={$endDate}&process=1");

        $response->assertStatus(200);
        $response->assertSee('Tidak Ditemukan Data Transaksi');
    }

    public function test_super_admin_can_apply_analysis_results_to_items(): void
    {
        $startDate = Carbon::now()->subDays(90)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $response = $this->actingAs($this->superAdmin)->post('/analisis-fsn-rop/terapkan', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Pastikan barang di database ter-update kategori FSN-nya
        $this->assertDatabaseHas('items', [
            'fsn_category' => 'F',
        ]);

        $fastItem = Item::where('fsn_category', 'F')->first();
        $this->assertNotNull($fastItem);
        $this->assertNotNull($fastItem->safety_stock);
        $this->assertNotNull($fastItem->rop);
    }
}
