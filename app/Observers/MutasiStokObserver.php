<?php

namespace App\Observers;

use App\Models\Barang;
use App\Models\MutasiStok;

/**
 * Menjaga `barang.stok_tersedia` selalu sama dengan mutasi stok terakhir.
 *
 * Observer ini sengaja dibuat sangat sempit tugasnya: ia tidak menghitung
 * apa pun, hanya menyalin `stok_akhir` yang sudah dihitung StockMutator.
 * Dengan begitu hanya ada satu tempat yang menentukan angka stok, dan
 * `barang.stok_tersedia` menjadi sekadar cerminan cepat dari riwayat mutasi.
 *
 * Tabel mutasi_stok bersifat catat-tambah (append only): koreksi dilakukan
 * dengan mencatat mutasi PENYESUAIAN baru, bukan dengan mengubah atau
 * menghapus baris lama. Karena itu observer ini hanya menangani `created`.
 *
 * Catatan: kolom barang.stok_tersedia bertipe integer, sedangkan stok_akhir
 * bertipe decimal(15,4). Nilai pecahan karena itu dibulatkan saat disalin.
 * Riwayat pada mutasi_stok tetap menyimpan angka aslinya.
 */
class MutasiStokObserver
{
    public function created(MutasiStok $mutasi): void
    {
        Barang::query()
            ->whereKey($mutasi->barang_id)
            ->update(['stok_tersedia' => (int) round((float) $mutasi->stok_akhir)]);
    }
}
