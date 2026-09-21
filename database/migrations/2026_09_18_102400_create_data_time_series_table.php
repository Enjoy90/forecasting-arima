<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil agregasi penjualan per barang per periode (deret Zt).
     * Disimpan permanen supaya hasil peramalan tetap dapat direproduksi
     * meskipun transaksi lama diubah.
     */
    public function up(): void
    {
        Schema::create('data_time_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->string('periode', 7);              // format YYYY-MM
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedInteger('urutan_t');       // t = 1, 2, 3, ... n
            $table->decimal('nilai_zt', 15, 2);        // total kuantitas terjual pada periode itu
            $table->timestamp('dihitung_pada')->nullable();
            $table->timestamps();

            $table->unique(['barang_id', 'periode'], 'uq_timeseries');
            $table->index(['barang_id', 'urutan_t']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_time_series');
    }
};
