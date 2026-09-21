<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TAHAP 1 - nilai ACF dan PACF per lag, dipakai untuk plot correlogram
     * dan penentuan kandidat ordo p dan q.
     */
    public function up(): void
    {
        Schema::create('korelasi_lag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peramalan_id')->constrained('peramalan')->cascadeOnDelete();
            $table->enum('jenis', ['ACF', 'PACF']);
            $table->unsignedSmallInteger('lag');
            $table->decimal('nilai', 12, 6);
            $table->decimal('batas_atas', 12, 6)->nullable();     // +1.96/sqrt(n)
            $table->decimal('batas_bawah', 12, 6)->nullable();    // -1.96/sqrt(n)
            $table->boolean('is_signifikan')->default(false);
            $table->timestamps();

            $table->unique(['peramalan_id', 'jenis', 'lag'], 'uq_korelasi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('korelasi_lag');
    }
};
