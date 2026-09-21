<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MODUL PENGUJIAN RENCANA STOK (revisi sidang proposal).
     *
     * Tolak ukur utama keberhasilan sistem: rencana stok diuji ulang memakai
     * data penjualan masa lalu perusahaan (backtesting), lalu dibandingkan
     * antara dua skenario pada rentang periode yang sama:
     *
     *   skenario PERUSAHAAN : kebijakan stok yang selama ini dipakai
     *   skenario SISTEM     : rekomendasi ARIMA + safety stock + waktu tunggu
     *
     * Ringkasan kedua skenario disimpan berdampingan di tabel ini supaya
     * tabel perbandingan di bab pengujian bisa dicetak langsung.
     */
    public function up(): void
    {
        Schema::create('simulasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode_simulasi', 30)->unique();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->foreignId('peramalan_id')->nullable()->constrained('peramalan')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // rentang data historis yang disimulasikan
            $table->string('periode_awal', 7);
            $table->string('periode_akhir', 7);
            $table->unsignedInteger('jumlah_periode');
            $table->decimal('stok_awal_simulasi', 15, 2)->default(0);

            // kebijakan perusahaan yang dijadikan pembanding
            $table->enum('metode_pembanding', [
                'produksi_aktual',      // memakai data produksi asli perusahaan
                'naif_bulan_lalu',      // produksi = penjualan bulan lalu
                'rata_rata_bergerak',   // produksi = rata-rata 3 bulan terakhir
            ])->default('naif_bulan_lalu');

            // parameter yang dipakai skenario sistem
            $table->decimal('nilai_z', 8, 4)->default(1.65);
            $table->decimal('service_level', 5, 2)->default(95.00);
            $table->decimal('lead_time_total_hari', 8, 2)->default(0);
            $table->decimal('biaya_simpan_per_unit', 15, 2)->default(0);
            $table->decimal('biaya_stockout_per_unit', 15, 2)->default(0);

            // --- ringkasan hasil: skenario kebijakan perusahaan ---
            $table->decimal('pb_total_stockout_unit', 15, 2)->default(0);
            $table->unsignedInteger('pb_bulan_stockout')->default(0);
            $table->decimal('pb_rata_stok_akhir', 15, 2)->default(0);
            $table->decimal('pb_total_overstock_unit', 15, 2)->default(0);
            $table->decimal('pb_service_level_tercapai', 8, 4)->default(0);
            $table->decimal('pb_perputaran_persediaan', 10, 4)->default(0);
            $table->decimal('pb_total_biaya', 18, 2)->default(0);

            // --- ringkasan hasil: skenario rekomendasi sistem ---
            $table->decimal('sis_total_stockout_unit', 15, 2)->default(0);
            $table->unsignedInteger('sis_bulan_stockout')->default(0);
            $table->decimal('sis_rata_stok_akhir', 15, 2)->default(0);
            $table->decimal('sis_total_overstock_unit', 15, 2)->default(0);
            $table->decimal('sis_service_level_tercapai', 8, 4)->default(0);
            $table->decimal('sis_perputaran_persediaan', 10, 4)->default(0);
            $table->decimal('sis_total_biaya', 18, 2)->default(0);

            // --- selisih perbaikan (yang dilaporkan sebagai bukti keberhasilan) ---
            $table->decimal('penurunan_overstock_persen', 8, 4)->default(0);
            $table->decimal('penurunan_stockout_persen', 8, 4)->default(0);
            $table->decimal('penghematan_biaya', 18, 2)->default(0);
            $table->boolean('is_sistem_lebih_baik')->default(false);
            $table->text('kesimpulan')->nullable();

            $table->timestamps();

            $table->index(['barang_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulasi');
    }
};
