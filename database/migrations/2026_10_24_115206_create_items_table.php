<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 20)->unique();
            $table->string('item_name', 100);
            $table->decimal('purchase_price', 12, 2); // harga beli terakhir
            $table->decimal('selling_price', 12, 2); // harga jual
            $table->integer('stock')->default(0); // stok fisik, tidak boleh negatif
            $table->string('unit', 20)->default('pcs'); // satuan (pcs, set, botol, dll)
            $table->char('fsn_category', 1)->nullable(); // F, S, atau N
            $table->integer('safety_stock')->nullable(); // safety stock (khusus F)
            $table->integer('rop')->nullable(); // reorder point (khusus F)
            $table->integer('minimum_stock')->nullable(); // batas minimum statis (khusus S)
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
