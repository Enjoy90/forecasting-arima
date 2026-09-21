<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TAHAP 1 - hasil uji Augmented Dickey-Fuller pada tiap tingkat differencing.
     * Satu baris untuk d = 0, satu baris untuk d = 1, dan seterusnya.
     */
    public function up(): void
    {
        Schema::create('uji_stasioneritas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peramalan_id')->constrained('peramalan')->cascadeOnDelete();
            $table->unsignedTinyInteger('differencing_ke');      // 0 = data asli
            $table->decimal('rata_rata', 18, 6)->nullable();
            $table->decimal('standar_deviasi', 18, 6)->nullable();
            $table->decimal('adf_statistic', 18, 6)->nullable();
            $table->decimal('nilai_kritis_1', 18, 6)->nullable();
            $table->decimal('nilai_kritis_5', 18, 6)->nullable();
            $table->decimal('nilai_kritis_10', 18, 6)->nullable();
            $table->decimal('p_value', 10, 6)->nullable();
            $table->boolean('is_stasioner')->default(false);
            $table->text('kesimpulan')->nullable();
            $table->timestamps();

            $table->unique(['peramalan_id', 'differencing_ke'], 'uq_stasioner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uji_stasioneritas');
    }
};
