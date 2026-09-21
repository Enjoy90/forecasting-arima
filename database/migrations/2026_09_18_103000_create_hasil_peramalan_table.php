<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TAHAP 4 - nilai per periode.
     * tipe = in_sample  : ada nilai aktual, dipakai menghitung MAPE/RMSE/MAE
     * tipe = forecast   : periode ke depan, nilai aktual masih kosong
     */
    public function up(): void
    {
        Schema::create('hasil_peramalan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peramalan_id')->constrained('peramalan')->cascadeOnDelete();
            $table->string('periode', 7);
            $table->unsignedInteger('urutan_t');
            $table->enum('tipe', ['in_sample', 'forecast']);
            $table->decimal('nilai_aktual', 15, 2)->nullable();
            $table->decimal('nilai_prediksi', 15, 2)->nullable();
            $table->decimal('residual', 18, 6)->nullable();
            $table->decimal('persen_error', 12, 6)->nullable();
            $table->decimal('batas_bawah', 15, 2)->nullable();    // interval kepercayaan 95%
            $table->decimal('batas_atas', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['peramalan_id', 'urutan_t']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_peramalan');
    }
};
