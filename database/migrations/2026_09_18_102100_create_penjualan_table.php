<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan', function (Blueprint $table) {
            $table->id();
            $table->string('no_faktur', 30)->unique();
            $table->date('tanggal_penjualan');
            $table->foreignId('pelanggan_id')->nullable()->constrained('pelanggan')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // diisi saat import Excel bila pelanggan belum terdaftar di master
            $table->string('nama_pelanggan_manual', 150)->nullable();

            $table->decimal('total_harga', 15, 2)->default(0);
            $table->enum('sumber_data', ['manual', 'import'])->default('manual');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            // dipakai berat saat agregasi time series per bulan
            $table->index('tanggal_penjualan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan');
    }
};
