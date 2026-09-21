<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bill of Material - komposisi bahan untuk menghasilkan satu item output
     * pada satu tahapan produksi.
     *
     * Contoh:
     *   BOM "Kepala Sekop Mentah" (tahapan: Produksi Kepala)
     *      output      : 10 pcs kepala sekop mentah
     *      komponennya : 1 lembar plat besi, 0.2 kg kawat las
     */
    public function up(): void
    {
        Schema::create('bom', function (Blueprint $table) {
            $table->id();
            $table->string('kode_bom', 30)->unique();
            $table->string('nama_bom', 150);
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();     // item output
            $table->foreignId('tahapan_id')->constrained('tahapan_produksi')->restrictOnDelete();

            // jumlah output yang dihasilkan oleh satu resep ini (basis perhitungan)
            $table->decimal('jumlah_output', 15, 4)->default(1);

            $table->boolean('is_aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['barang_id', 'is_aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom');
    }
};
