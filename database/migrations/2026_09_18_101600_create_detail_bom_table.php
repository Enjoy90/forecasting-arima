<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komponen penyusun sebuah BOM. Tabel inilah yang "diledakkan" (BOM explosion)
     * saat menghitung kebutuhan bahan baku dari target produksi hasil peramalan.
     */
    public function up(): void
    {
        Schema::create('detail_bom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('bom')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();   // komponen
            $table->decimal('jumlah_kebutuhan', 15, 4);     // per jumlah_output pada header BOM
            $table->string('satuan', 20)->nullable();

            // persentase susut/gagal yang diperhitungkan, mis. 3% plat terbuang
            $table->decimal('persen_susut', 5, 2)->default(0);

            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['bom_id', 'barang_id'], 'uq_detail_bom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_bom');
    }
};
