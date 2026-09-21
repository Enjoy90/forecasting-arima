<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pelanggan', 20)->unique();
            $table->string('nama_pelanggan', 150);
            $table->enum('jenis', ['toko', 'distributor', 'perorangan', 'instansi'])->default('toko');
            $table->string('telepon', 25)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('alamat')->nullable();
            $table->string('kota', 100)->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index('nama_pelanggan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
