<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu tabel untuk seluruh item yang dikelola CV. Pande Sejahtera:
     *  - bahan_baku    : plat besi, kayu gagang, cat coating, kawat las, plastik kemasan
     *  - setengah_jadi : kepala sekop mentah, kepala sekop ter-coating, handle jadi
     *  - barang_jadi   : sekop siap jual (hasil pengemasan)
     *
     * Dipisah lewat kolom jenis_barang, bukan tabel terpisah, supaya mutasi stok,
     * BOM, dan produksi cukup mengacu ke satu tabel.
     */
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang', 30)->unique();
            $table->string('nama_barang', 150);
            $table->enum('jenis_barang', ['bahan_baku', 'setengah_jadi', 'barang_jadi']);
            $table->foreignId('kategori_id')->constrained('kategori')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('supplier')->nullOnDelete();
            $table->string('satuan', 20)->default('Pcs');

            $table->decimal('harga_beli', 15, 2)->default(0);    // relevan untuk bahan baku
            $table->decimal('harga_jual', 15, 2)->default(0);    // relevan untuk barang jadi

            // stok berjalan, diperbarui otomatis oleh observer lewat mutasi_stok
            $table->integer('stok_tersedia')->default(0);
            $table->integer('stok_minimum')->default(0);

            // parameter perencanaan stok
            $table->unsignedSmallInteger('lead_time_hari')->default(7);
            $table->decimal('service_level', 5, 2)->default(95.00);

            // hanya barang jadi yang penjualannya diramalkan dengan ARIMA
            $table->boolean('is_diramalkan')->default(false);

            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index('nama_barang');
            $table->index('jenis_barang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
