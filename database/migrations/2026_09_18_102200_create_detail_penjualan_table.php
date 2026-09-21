<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_penjualan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('penjualan')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->integer('jumlah');

            // harga disimpan ulang di sini karena bersifat historis:
            // harga barang bisa berubah, nota lama harus tetap menampilkan harga saat transaksi
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            // sumber utama agregasi time series
            $table->index('barang_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_penjualan');
    }
};
