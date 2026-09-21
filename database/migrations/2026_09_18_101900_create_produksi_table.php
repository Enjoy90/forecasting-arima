<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu perintah kerja pada satu tahapan produksi.
     * Alur lengkap sekop membutuhkan 5 baris produksi yang berurutan:
     *   Produksi Kepala -> Produksi Handle -> Coating -> Perakitan -> Pengemasan
     *
     * Saat status berubah menjadi 'selesai':
     *   - bahan pada detail_produksi_bahan dicatat KELUAR di mutasi_stok
     *   - barang_output dicatat MASUK di mutasi_stok sebanyak jumlah_hasil
     */
    public function up(): void
    {
        Schema::create('produksi', function (Blueprint $table) {
            $table->id();
            $table->string('no_produksi', 30)->unique();
            $table->date('tanggal_produksi');
            $table->foreignId('tahapan_id')->constrained('tahapan_produksi')->restrictOnDelete();
            $table->foreignId('bom_id')->nullable()->constrained('bom')->nullOnDelete();
            $table->foreignId('barang_output_id')->constrained('barang')->restrictOnDelete();

            $table->decimal('jumlah_target', 15, 2)->default(0);
            $table->decimal('jumlah_hasil', 15, 2)->default(0);
            $table->decimal('jumlah_gagal', 15, 2)->default(0);

            $table->enum('status', ['draft', 'proses', 'selesai', 'batal'])->default('draft');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['tanggal_produksi', 'tahapan_id']);
            $table->index('barang_output_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produksi');
    }
};
