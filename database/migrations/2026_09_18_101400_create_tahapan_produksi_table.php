<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master tahapan produksi sekop, berurutan:
     *   1. Produksi Kepala
     *   2. Produksi Handle
     *   3. Proses Coating
     *   4. Perakitan Sekop
     *   5. Proses Pengemasan
     *
     * Dibuat sebagai tabel master (bukan enum) supaya urutan dan namanya
     * bisa disesuaikan tanpa mengubah struktur database.
     */
    public function up(): void
    {
        Schema::create('tahapan_produksi', function (Blueprint $table) {
            $table->id();
            $table->string('kode_tahapan', 20)->unique();
            $table->string('nama_tahapan', 100);
            $table->unsignedTinyInteger('urutan');

            // RINCIAN WAKTU TUNGGU OPERASIONAL PABRIK (revisi sidang proposal).
            // Lama pengerjaan satu tahapan ini, dipakai menghitung total waktu tunggu
            // dari perintah produksi sampai barang jadi siap dijual.
            $table->decimal('waktu_proses_hari', 8, 2)->default(1);

            // kapasitas produksi per hari pada tahapan ini (unit/hari).
            // Dipakai menghitung tambahan hari bila target melebihi kapasitas harian.
            $table->decimal('kapasitas_per_hari', 15, 2)->default(0);

            $table->text('deskripsi')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahapan_produksi');
    }
};
