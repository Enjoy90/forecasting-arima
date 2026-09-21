<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('no_pembelian', 30)->unique();
            $table->date('tanggal_pembelian');
            $table->date('tanggal_terima')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('supplier')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_harga', 15, 2)->default(0);
            $table->enum('status', ['dipesan', 'diterima', 'batal'])->default('dipesan');
            $table->enum('sumber_data', ['manual', 'import'])->default('manual');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('tanggal_pembelian');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembelian');
    }
};
