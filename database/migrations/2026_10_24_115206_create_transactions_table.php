<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // tanggal transaksi
            $table->string('transaction_type', 20); // penjualan atau pembelian
            $table->decimal('total', 12, 2)->default(0); // total nilai transaksi
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // kasir/admin pencatat
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
