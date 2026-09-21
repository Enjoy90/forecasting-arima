<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat seluruh pergerakan stok (menu: Persediaan > Mutasi Stok).
     * Sumber mutasi:
     *   MASUK        : penerimaan pembelian, hasil produksi
     *   KELUAR       : pemakaian bahan pada produksi, penjualan
     *   PENYESUAIAN  : koreksi stok opname
     *
     * Kolom stok_awal & stok_akhir disimpan agar kartu stok bisa dicetak
     * apa adanya tanpa menghitung ulang saldo berjalan.
     */
    public function up(): void
    {
        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('jenis_mutasi', ['masuk', 'keluar', 'penyesuaian']);
            $table->enum('sumber', ['pembelian', 'produksi', 'penjualan', 'opname', 'lainnya'])
                  ->default('lainnya');

            // relasi polymorphic ke pembelian / produksi / penjualan
            $table->string('referensi_tipe', 60)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();

            $table->decimal('jumlah', 15, 4);
            $table->decimal('stok_awal', 15, 4);
            $table->decimal('stok_akhir', 15, 4);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['barang_id', 'tanggal']);
            $table->index(['referensi_tipe', 'referensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_stok');
    }
};
