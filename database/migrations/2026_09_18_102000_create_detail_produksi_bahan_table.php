<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bahan yang dikonsumsi pada satu perintah produksi.
     * jumlah_rencana diisi otomatis dari BOM, jumlah_pakai diisi realisasi di lapangan
     * sehingga selisihnya bisa dianalisis sebagai susut/efisiensi.
     */
    public function up(): void
    {
        Schema::create('detail_produksi_bahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produksi_id')->constrained('produksi')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->decimal('jumlah_rencana', 15, 4)->default(0);
            $table->decimal('jumlah_pakai', 15, 4)->default(0);
            $table->string('satuan', 20)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('barang_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_produksi_bahan');
    }
};
