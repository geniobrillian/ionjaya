<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu barang boleh dipasok banyak pemasok, satu pemasok boleh
     * memasok banyak barang (many-to-many). Pivot barang_pemasok
     * hanya membawa penanda pemasok utama — TANPA harga dan TANPA
     * lead time (keduanya tetap di tempat lama: harga di master
     * barang, lead time di pemasok).
     *
     * Migrasi ini sekaligus memindahkan data lama: setiap
     * items.supplier_id yang ada menjadi baris pivot dengan
     * pemasok_utama = 1, lalu kolom items.supplier_id dihapus
     * (satu sumber kebenaran, tanpa cache).
     */
    public function up(): void
    {
        Schema::create('barang_pemasok', function (Blueprint $table) {
            $table->foreignId('barang_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('pemasok_id')->constrained('suppliers')->cascadeOnDelete();
            $table->boolean('pemasok_utama')->default(false);
            $table->unique(['barang_id', 'pemasok_id']);
        });

        // Pindahkan pemasok lama menjadi relasi Utama.
        $rows = DB::table('items')->whereNotNull('supplier_id')->get(['id', 'supplier_id']);
        foreach ($rows as $row) {
            DB::table('barang_pemasok')->insert([
                'barang_id' => $row->id,
                'pemasok_id' => $row->supplier_id,
                'pemasok_utama' => true,
            ]);
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
        });

        // Kembalikan pemasok Utama menjadi kolom tunggal.
        $rows = DB::table('barang_pemasok')->where('pemasok_utama', true)->get(['barang_id', 'pemasok_id']);
        foreach ($rows as $row) {
            DB::table('items')->where('id', $row->barang_id)->update(['supplier_id' => $row->pemasok_id]);
        }

        Schema::dropIfExists('barang_pemasok');
    }
};
