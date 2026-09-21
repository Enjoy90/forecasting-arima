<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TAHAP 2 - seluruh kombinasi ARIMA(p,d,q) yang diuji pada grid search.
     * Satu baris ditandai is_terpilih = true sebagai model terbaik.
     */
    public function up(): void
    {
        Schema::create('kandidat_model', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peramalan_id')->constrained('peramalan')->cascadeOnDelete();
            $table->unsignedTinyInteger('ordo_p');
            $table->unsignedTinyInteger('ordo_d');
            $table->unsignedTinyInteger('ordo_q');
            $table->decimal('aic', 18, 6)->nullable();
            $table->decimal('bic', 18, 6)->nullable();
            $table->decimal('sse', 18, 6)->nullable();
            $table->decimal('sigma_kuadrat', 18, 6)->nullable();
            $table->decimal('mape', 10, 4)->nullable();
            $table->boolean('semua_signifikan')->default(false);
            $table->boolean('lolos_ljung_box')->default(false);
            $table->boolean('is_terpilih')->default(false);
            $table->string('keterangan', 255)->nullable();   // alasan ditolak bila gagal
            $table->timestamps();

            $table->unique(['peramalan_id', 'ordo_p', 'ordo_d', 'ordo_q'], 'uq_kandidat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kandidat_model');
    }
};
