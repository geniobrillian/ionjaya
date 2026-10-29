<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->superAdmin = User::where('role', 'super_admin')->first();
    }

    public function test_super_admin_can_view_suppliers_and_items_pages(): void
    {
        $responseSuppliers = $this->actingAs($this->superAdmin)->get('/pemasok');
        $responseSuppliers->assertStatus(200);
        $responseSuppliers->assertSee('Data Pemasok');

        $responseItems = $this->actingAs($this->superAdmin)->get('/barang');
        $responseItems->assertStatus(200);
        $responseItems->assertSee('Data Barang');
    }

    public function test_super_admin_can_create_and_update_supplier(): void
    {
        // 1. Tambah Pemasok
        $responseCreate = $this->actingAs($this->superAdmin)->post('/pemasok', [
            'supplier_code' => 'SUP999',
            'supplier_name' => 'PT Test Supplier Jombang',
            'phone' => '089999888877',
            'lead_time' => 4,
            'address' => 'Jl. Test No. 10',
        ]);

        $responseCreate->assertRedirect('/pemasok');
        $responseCreate->assertSessionHas('success');

        $this->assertDatabaseHas('suppliers', [
            'supplier_code' => 'SUP999',
            'supplier_name' => 'PT Test Supplier Jombang',
            'lead_time' => 4,
        ]);

        // 2. Ubah Pemasok
        $supplier = Supplier::where('supplier_code', 'SUP999')->first();
        $responseUpdate = $this->actingAs($this->superAdmin)->put('/pemasok/' . $supplier->id, [
            'supplier_code' => 'SUP999',
            'supplier_name' => 'PT Test Supplier Terupdate',
            'phone' => '089999888877',
            'lead_time' => 3,
            'address' => 'Jl. Test No. 10 Baru',
        ]);

        $responseUpdate->assertRedirect('/pemasok');
        $responseUpdate->assertSessionHas('success');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'supplier_name' => 'PT Test Supplier Terupdate',
            'lead_time' => 3,
        ]);
    }

    public function test_supplier_cannot_be_deleted_if_has_items(): void
    {
        // Supplier 1 memiliki relasi dengan beberapa barang dari seeder
        $supplier = Supplier::find(1);

        $response = $this->actingAs($this->superAdmin)->delete('/pemasok/' . $supplier->id);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', [
            'id' => 1,
        ]);
    }

    public function test_supplier_can_be_deleted_if_no_items(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUP_EMPTY',
            'supplier_name' => 'Pemasok Kosong',
            'lead_time' => 2,
        ]);

        $response = $this->actingAs($this->superAdmin)->delete('/pemasok/' . $supplier->id);

        $response->assertRedirect('/pemasok');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('suppliers', [
            'id' => $supplier->id,
        ]);
    }

    public function test_super_admin_can_create_and_update_item(): void
    {
        // 1. Tambah Barang
        $responseCreate = $this->actingAs($this->superAdmin)->post('/barang', [
            'item_code' => 'BRG_TEST',
            'item_name' => 'Busi Iridium Racing Test',
            'supplier_id' => 1,
            'purchase_price' => 50000,
            'selling_price' => 75000,
        ]);

        $responseCreate->assertRedirect('/barang');
        $responseCreate->assertSessionHas('success');

        // Tahap 1 (1 pintu ketat): barang baru mulai dari stok 0.
        $this->assertDatabaseHas('items', [
            'item_code' => 'BRG_TEST',
            'item_name' => 'Busi Iridium Racing Test',
            'stock' => 0,
        ]);

        // 2. Ubah Barang
        $item = Item::where('item_code', 'BRG_TEST')->first();
        $responseUpdate = $this->actingAs($this->superAdmin)->put('/barang/' . $item->id, [
            'item_code' => 'BRG_TEST',
            'item_name' => 'Busi Iridium Racing V2',
            'supplier_id' => 1,
            'selling_price' => 80000,
        ]);

        $responseUpdate->assertRedirect('/barang');
        $responseUpdate->assertSessionHas('success');

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'item_name' => 'Busi Iridium Racing V2',
            'selling_price' => 80000,
            'stock' => 0, // Stok tetap tidak berubah
        ]);
    }

    public function test_super_admin_can_create_item_with_auto_increment_code_and_default_unit(): void
    {
        // Tambah barang pertama tanpa kode barang
        $response1 = $this->actingAs($this->superAdmin)->post('/barang', [
            'item_name' => 'Sparepart Baru Auto 1',
            'supplier_id' => 1,
            'purchase_price' => 15000,
            'selling_price' => 25000,
        ]);

        $response1->assertRedirect('/barang');
        $response1->assertSessionHas('success');

        // Pastikan kode terbuat otomatis BRG021 (seeder memiliki sampai BRG020)
        $this->assertDatabaseHas('items', [
            'item_code' => 'BRG021',
            'item_name' => 'Sparepart Baru Auto 1',
        ]);

        // Tambah barang kedua secara berurutan
        $response2 = $this->actingAs($this->superAdmin)->post('/barang', [
            'item_name' => 'Sparepart Baru Auto 2',
            'supplier_id' => 1,
            'purchase_price' => 20000,
            'selling_price' => 30000,
        ]);

        $response2->assertRedirect('/barang');
        $this->assertDatabaseHas('items', [
            'item_code' => 'BRG022',
            'item_name' => 'Sparepart Baru Auto 2',
        ]);
    }

    public function test_super_admin_can_create_item_without_supplier(): void
    {
        // Form tambah tidak lagi menawarkan pilihan pemasok;
        // penugasan pemasok hanya lewat Ubah Barang.
        $response = $this->actingAs($this->superAdmin)->post('/barang', [
            'item_name' => 'Sparepart Tanpa Pemasok',
            'purchase_price' => 10000,
            'selling_price' => 15000,
        ]);

        $response->assertRedirect('/barang');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('items', [
            'item_name' => 'Sparepart Tanpa Pemasok',
            'supplier_id' => null,
        ]);
    }

    public function test_item_cannot_be_deleted_if_has_transactions(): void
    {
        // Item 1 memiliki riwayat transaksi dari seeder
        $item = Item::find(1);

        $response = $this->actingAs($this->superAdmin)->delete('/barang/' . $item->id);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('items', [
            'id' => 1,
        ]);
    }

    public function test_item_can_be_deleted_if_no_transactions(): void
    {
        $item = Item::create([
            'item_code' => 'BRG_EMPTY',
            'item_name' => 'Barang Baru Belum Transaksi',
            'supplier_id' => 1,
            'purchase_price' => 20000,
            'selling_price' => 30000,
            'stock' => 5,
        ]);

        $response = $this->actingAs($this->superAdmin)->delete('/barang/' . $item->id);

        $response->assertRedirect('/barang');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('items', [
            'id' => $item->id,
        ]);
    }
}
