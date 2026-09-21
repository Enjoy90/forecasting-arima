<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TAHAP 2 - koefisien model terpilih beserta hasil uji signifikansi (uji t).
     * Satu baris per koefisien: AR(1), AR(2), MA(1), ... dan konstanta.
     */
    public function up(): void
    {
        Schema::create('parameter_model', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peramalan_id')->constrained('peramalan')->cascadeOnDelete();
            $table->enum('jenis', ['AR', 'MA', 'KONSTANTA']);
            $table->unsignedTinyInteger('lag')->default(0);     // 0 untuk konstanta
            $table->decimal('koefisien', 18, 6);
            $table->decimal('standard_error', 18, 6)->nullable();
            $table->decimal('t_hitung', 18, 6)->nullable();
            $table->decimal('t_tabel', 18, 6)->nullable();
            $table->decimal('p_value', 10, 6)->nullable();
            $table->boolean('is_signifikan')->default(false);
            $table->timestamps();

            $table->unique(['peramalan_id', 'jenis', 'lag'], 'uq_parameter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_model');
    }
};
