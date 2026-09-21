<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil BOM explosion dari target produksi.
     * Inilah jembatan antara hasil peramalan dan PERENCANAAN STOK pada judul skripsi:
     *
     *   target produksi sekop  --(ledak BOM)-->  kebutuhan tiap bahan baku
     *   kekurangan = kebutuhan - stok tersedia   -->  rekomendasi jumlah pembelian
     */
    public function up(): void
    {
        Schema::create('kebutuhan_bahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_produksi_id')->constrained('target_produksi')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();   // bahan / komponen
            $table->foreignId('tahapan_id')->nullable()->constrained('tahapan_produksi')->nullOnDelete();

            $table->decimal('jumlah_kebutuhan', 15, 4)->default(0);
            $table->decimal('stok_tersedia', 15, 4)->default(0);
            $table->decimal('kekurangan', 15, 4)->default(0);
            $table->decimal('safety_stock_bahan', 15, 4)->default(0);
            $table->decimal('qty_rekomendasi_beli', 15, 4)->default(0);
            $table->string('satuan', 20)->nullable();

            $table->enum('status', ['cukup', 'perlu_beli', 'mendesak'])->default('cukup');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['target_produksi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebutuhan_bahan');
    }
};
