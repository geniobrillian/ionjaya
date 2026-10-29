<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 1 revisi sistem:
     * - Transaksi mencatat waktu (kolom time terpisah, bukan mengubah tipe date,
     *   agar seluruh logika periode berbasis tanggal tidak berubah).
     * - Pemasok barang menjadi opsional saat tambah barang (penugasan pemasok
     *   pindah ke detail/edit barang). Membutuhkan doctrine/dbal untuk change()
     *   pada SQLite.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->time('time')->nullable();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('time');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
        });
    }
};
