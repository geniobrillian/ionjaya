<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $adminKasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->superAdmin = User::where('role', 'super_admin')->first();
        $this->adminKasir = User::where('role', 'admin')->first();
    }

    private function libraryAvailable(): bool
    {
        return class_exists(Spreadsheet::class);
    }

    /** Membangun file .xlsx dari array per sheet, mengembalikan path temp. */
    private function buildXlsx(array $sheets): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        foreach ($sheets as $title => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            $sheet->fromArray($rows, null, 'A1');
        }
        $path = tempnam(sys_get_temp_dir(), 'import_test_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function validSheets(): array
    {
        return [
            'Data Barang' => [
                ['Kode Barang', 'Nama Barang', 'Kode Pemasok', 'Nama Pemasok', 'Lead Time (hari)', 'Harga Beli (Rp)', 'Harga Jual (Rp)'],
                ['BRG901', 'Barang Uji Satu', 'SUP901', 'Pemasok Uji', 1, 50000, 65000],
                ['BRG902', 'Barang Uji Dua', 'SUP901', 'Pemasok Uji', 1, 20000, 30000],
            ],
            'Stok Opname' => [
                ['Kode Barang', 'Nama Barang', 'Stok Awal', 'Stok Akhir'],
                ['BRG901', 'Barang Uji Satu', 10, 12],
                ['BRG902', 'Barang Uji Dua', 5, 7],
            ],
            'Pembelian' => [
                ['No. Pembelian', 'Tanggal', 'Waktu', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Harga Beli (Rp)', 'Kode Pemasok', 'Nama Pemasok'],
                // Harga file sengaja beda dari master: impor harus pakai harga master.
                ['PBL-1', '2026-01-05', '09:00', 'BRG901', 'Barang Uji Satu', 5, 99999, 'SUP901', 'Pemasok Uji'],
                ['PBL-1', '2026-01-05', '09:00', 'BRG902', 'Barang Uji Dua', 10, 88888, 'SUP901', 'Pemasok Uji'],
            ],
            'Penjualan' => [
                ['No. Transaksi', 'Tanggal', 'Waktu', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Harga Jual (Rp)'],
                ['TRJ-1', '2026-01-06', '10:30', 'BRG901', 'Barang Uji Satu', 3, 11111],
                ['TRJ-2', '2026-01-06', '11:00', 'BRG902', 'Barang Uji Dua', 8, 22222],
            ],
        ];
    }

    public function test_super_admin_can_view_import_page(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/impor');

        $response->assertStatus(200);
        $response->assertSee('Impor Data');
    }

    public function test_admin_cannot_access_import_page(): void
    {
        $response = $this->actingAs($this->adminKasir)->get('/impor');

        $response->assertStatus(403);
    }

    public function test_upload_invalid_file_type_rejected(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'import_invalid_');
        file_put_contents($tmp, 'bukan excel');

        $response = $this->actingAs($this->superAdmin)->post('/impor/pratinjau', [
            'file' => new UploadedFile($tmp, 'data.txt', 'text/plain', null, true),
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_confirm_with_missing_file_redirects_to_index(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/impor/konfirmasi', [
            'import_id' => 'tidakada.xlsx',
        ]);

        $response->assertRedirect('/impor');
        $response->assertSessionHas('error');
    }

    public function test_template_download(): void
    {
        if (! $this->libraryAvailable()) {
            $this->markTestSkipped('PhpSpreadsheet belum terpasang.');
        }

        $response = $this->actingAs($this->superAdmin)->get('/impor/template');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_full_import_flow_uses_master_prices(): void
    {
        if (! $this->libraryAvailable()) {
            $this->markTestSkipped('PhpSpreadsheet belum terpasang.');
        }

        $path = $this->buildXlsx($this->validSheets());
        $preview = $this->actingAs($this->superAdmin)->post('/impor/pratinjau', [
            'file' => new UploadedFile($path, 'import.xlsx', null, null, true),
        ]);
        $preview->assertStatus(200);
        $preview->assertSee('Konfirmasi');
        $preview->assertSee('BRG901');

        preg_match('/name="import_id" value="([^"]+)"/', $preview->getContent(), $m);
        $this->assertNotEmpty($m[1] ?? null, 'Pratinjau harus menyertakan import_id.');

        $confirm = $this->actingAs($this->superAdmin)->post('/impor/konfirmasi', [
            'import_id' => $m[1],
        ]);
        $confirm->assertRedirect('/impor/hasil');

        // Stok akhir: BRG901 = 10 + 5 - 3 = 12; BRG902 = 5 + 10 - 8 = 7.
        $this->assertDatabaseHas('items', ['item_code' => 'BRG901', 'stock' => 12]);
        $this->assertDatabaseHas('items', ['item_code' => 'BRG902', 'stock' => 7]);
        $this->assertDatabaseHas('suppliers', ['supplier_code' => 'SUP901', 'lead_time' => 1]);

        // Harga detail memakai harga master, bukan harga file (99999/11111).
        $item901 = Item::where('item_code', 'BRG901')->first();
        $purchaseDetail = TransactionDetail::where('item_id', $item901->id)
            ->whereHas('transaction', fn ($q) => $q->where('transaction_type', 'pembelian'))->first();
        $this->assertEquals(50000, (float) $purchaseDetail->unit_price);
        $saleDetail = TransactionDetail::where('item_id', $item901->id)
            ->whereHas('transaction', fn ($q) => $q->where('transaction_type', 'penjualan'))->first();
        $this->assertEquals(65000, (float) $saleDetail->unit_price);
    }

    public function test_oversell_file_shows_errors_in_preview(): void
    {
        if (! $this->libraryAvailable()) {
            $this->markTestSkipped('PhpSpreadsheet belum terpasang.');
        }

        $sheets = $this->validSheets();
        // Penjualan BRG902 jadi 100 (stok tersedia 15) -> stok negatif.
        $sheets['Penjualan'][2][5] = 100;

        $path = $this->buildXlsx($sheets);
        $preview = $this->actingAs($this->superAdmin)->post('/impor/pratinjau', [
            'file' => new UploadedFile($path, 'import.xlsx', null, null, true),
        ]);

        $preview->assertStatus(200);
        $preview->assertSee('tidak bisa dilanjutkan');
        $preview->assertSee('negatif');
        $preview->assertDontSee('Konfirmasi &amp; Impor');
    }

    public function test_duplicate_item_rejected_in_preview(): void
    {
        if (! $this->libraryAvailable()) {
            $this->markTestSkipped('PhpSpreadsheet belum terpasang.');
        }

        // BRG001 sudah ada dari seeder.
        $sheets = $this->validSheets();
        $sheets['Data Barang'][1][0] = 'BRG001';

        $path = $this->buildXlsx($sheets);
        $preview = $this->actingAs($this->superAdmin)->post('/impor/pratinjau', [
            'file' => new UploadedFile($path, 'import.xlsx', null, null, true),
        ]);

        $preview->assertStatus(200);
        $preview->assertSee('sudah ada di database');
    }
}
