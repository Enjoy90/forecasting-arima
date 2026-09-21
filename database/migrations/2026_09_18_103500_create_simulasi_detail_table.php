<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak simulasi bulan per bulan untuk tiap skenario.
     * Tabel inilah yang dicetak sebagai lampiran pengujian: terlihat bulan mana
     * perusahaan kehabisan stok dan bulan mana barangnya menumpuk, lalu
     * dibandingkan dengan hasil bila memakai rekomendasi sistem.
     *
     * Alur tiap periode:
     *   stok_akhir = stok_awal + barang_masuk - terpenuhi
     *   stockout_unit = permintaan_aktual - terpenuhi     (pesanan yang batal)
     *   overstock_unit = stok_akhir - permintaan periode berikutnya  (bila positif)
     */
    public function up(): void
    {
        Schema::create('simulasi_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulasi_id')->constrained('simulasi')->cascadeOnDelete();
            $table->enum('skenario', ['perusahaan', 'sistem']);
            $table->string('periode', 7);
            $table->unsignedInteger('urutan_t');

            $table->decimal('stok_awal', 15, 2)->default(0);
            $table->decimal('permintaan_aktual', 15, 2)->default(0);

            // hanya terisi pada skenario sistem
            $table->decimal('prediksi_permintaan', 15, 2)->nullable();
            $table->decimal('safety_stock', 15, 2)->default(0);

            $table->decimal('rencana_produksi', 15, 2)->default(0);

            // hasil produksi yang benar-benar tiba pada periode ini,
            // sudah memperhitungkan waktu tunggu pembelian bahan + proses produksi
            $table->decimal('barang_masuk', 15, 2)->default(0);

            $table->decimal('terpenuhi', 15, 2)->default(0);
            $table->decimal('stockout_unit', 15, 2)->default(0);
            $table->decimal('stok_akhir', 15, 2)->default(0);
            $table->decimal('overstock_unit', 15, 2)->default(0);

            $table->decimal('biaya_simpan', 18, 2)->default(0);
            $table->decimal('biaya_stockout', 18, 2)->default(0);

            $table->boolean('is_stockout')->default(false);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['simulasi_id', 'skenario', 'periode'], 'uq_simulasi_detail');
            $table->index(['simulasi_id', 'skenario', 'urutan_t'], 'idx_simulasi_urut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulasi_detail');
    }
};
