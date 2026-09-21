<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->string('kode_supplier', 20)->unique();
            $table->string('nama_supplier', 150);
            $table->string('telepon', 25)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('alamat')->nullable();
            // lead time default pemasok ini, dipakai kalau barang belum punya lead time sendiri
            $table->unsignedSmallInteger('lead_time_default')->default(7);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier');
    }
};
