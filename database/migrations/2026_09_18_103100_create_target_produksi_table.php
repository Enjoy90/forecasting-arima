<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu: Peramalan > Target Produksi.
     * Menerjemahkan hasil ramalan penjualan menjadi jumlah yang harus diproduksi.
     *
     *   Safety Stock   = Z * sigma_error * sqrt(lead time)
     *   Target Produksi = Prediksi Penjualan + Safety Stock - Stok Barang Jadi
     *                     - Stok Setengah Jadi Siap Rakit
     */
    public function up(): void
    {
        Schema::create('target_produksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peramalan_id')->constrained('peramalan')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();   // barang jadi
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('periode', 7);

            $table->decimal('prediksi_penjualan', 15, 2);
            $table->decimal('stok_barang_jadi', 15, 2)->default(0);
            $table->decimal('stok_setengah_jadi', 15, 2)->default(0);

            // komponen perhitungan disimpan supaya rinciannya bisa ditampilkan di laporan
            $table->decimal('standar_deviasi_error', 18, 6)->nullable();

            // RINCIAN WAKTU TUNGGU OPERASIONAL (revisi sidang proposal)
            // total = lama beli bahan baku + lama seluruh tahapan produksi
            $table->decimal('lead_time_pembelian_hari', 8, 2)->default(0);
            $table->decimal('lead_time_produksi_hari', 8, 2)->default(0);
            $table->decimal('lead_time_total_hari', 8, 2)->default(0);

            // tanggal paling lambat perintah produksi harus dimulai agar barang
            // siap sebelum periode penjualan berjalan
            $table->date('tanggal_mulai_produksi')->nullable();
            $table->date('tanggal_pesan_bahan')->nullable();

            $table->decimal('nilai_z', 8, 4)->default(1.65);
            $table->decimal('safety_stock', 15, 2)->default(0);
            $table->decimal('reorder_point', 15, 2)->default(0);
            $table->decimal('jumlah_target_produksi', 15, 2)->default(0);

            $table->enum('status_stok', ['aman', 'segera_produksi', 'kritis'])->default('aman');
            $table->enum('status_approval', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu');
            $table->timestamp('disetujui_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['peramalan_id', 'barang_id', 'periode'], 'uq_target_produksi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_produksi');
    }
};
