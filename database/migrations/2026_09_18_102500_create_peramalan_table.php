<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header satu proses peramalan: model ARIMA(p,d,q) terpilih beserta akurasinya.
     */
    public function up(): void
    {
        Schema::create('peramalan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_peramalan', 30)->unique();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // rentang data yang dipakai
            $table->string('periode_awal', 7);
            $table->string('periode_akhir', 7);
            $table->unsignedInteger('jumlah_data');

            // ordo model terpilih
            $table->unsignedTinyInteger('ordo_p')->default(0);
            $table->unsignedTinyInteger('ordo_d')->default(0);
            $table->unsignedTinyInteger('ordo_q')->default(0);
            $table->decimal('konstanta', 18, 6)->nullable();

            // kriteria pemilihan model
            $table->decimal('aic', 18, 6)->nullable();
            $table->decimal('bic', 18, 6)->nullable();
            $table->decimal('sigma_kuadrat', 18, 6)->nullable();

            // ukuran akurasi
            $table->decimal('mape', 10, 4)->nullable();
            $table->decimal('rmse', 18, 6)->nullable();
            $table->decimal('mae', 18, 6)->nullable();
            $table->string('kategori_akurasi', 20)->nullable();   // Sangat Baik | Baik | Cukup | Buruk

            $table->unsignedTinyInteger('horizon')->default(3);   // jumlah periode yang diramalkan
            $table->enum('status', ['draft', 'final', 'ditolak'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['barang_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peramalan');
    }
};
