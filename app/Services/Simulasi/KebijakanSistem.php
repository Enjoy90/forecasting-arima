<?php

namespace App\Services\Simulasi;

/**
 * Skenario rekomendasi sistem (docs/01 §7.2, direvisi 2026-09-21):
 *
 *   rencana_produksi(t) = SUM(forecast(t)..forecast(t+delay)) + SS
 *                         - stok_awal(t) - barang_dalam_proses
 *
 * Satu kali pesan harus menutupi permintaan SEPANJANG waktu tunggu (delay+1
 * bulan ke depan), bukan cuma forecast bulan itu saja -- rumus awal hanya
 * menetralkan forecast(t) tunggal, yang terbukti salah begitu waktu tunggu
 * lebih dari sebulan (lihat docs/01 §7.5 poin 4 untuk penjelasan lengkap).
 * Rumus ini kembali sama persis dengan rumus awal saat delay=0.
 */
class KebijakanSistem
{
    /**
     * @param  array<int, float>  $forecastSepanjangWaktuTunggu  forecast(t)..forecast(t+delay), delay+1 nilai
     */
    public function rencanaProduksi(array $forecastSepanjangWaktuTunggu, float $safetyStock, float $stokAwalT, float $barangDalamProses): float
    {
        return max(0.0, array_sum($forecastSepanjangWaktuTunggu) + $safetyStock - $stokAwalT - $barangDalamProses);
    }
}
