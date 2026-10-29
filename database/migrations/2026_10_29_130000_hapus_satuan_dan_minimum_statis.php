<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahap 2 revisi sistem: hapus kolom satuan dan batas minimum statis.
     * Aman dihapus sekarang karena tidak ada lagi kode yang membaca
     * kedua kolom tersebut (ambang Slow sudah memakai ROP hasil hitung).
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['unit', 'minimum_stock']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('unit', 20)->default('pcs');
            $table->integer('minimum_stock')->nullable();
        });
    }
};
