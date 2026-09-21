<?php

namespace App\Services\Simulasi;

use InvalidArgumentException;
use RuntimeException;

/**
 * Skenario kebijakan perusahaan (docs/01 §7.2), tiga metode pembanding:
 * produksi_aktual (angka produksi asli bila tersedia), naif_bulan_lalu
 * (rencana_produksi(t) = permintaan_aktual(t-1)), rata_rata_bergerak
 * (rata-rata 3 bulan terakhir). Tidak bergantung pada simulasi stok
 * (beda dari KebijakanSistem), jadi bisa dihitung di muka untuk 12 bulan sekaligus.
 */
class KebijakanPerusahaan
{
    /**
     * @param  array<int, float>  $historiSebelumSimulasi  deret in-sample (24 bulan) sebagai bahan lookback
     * @param  array<int, float>  $permintaanAktualSimulasi  12 bulan permintaan aktual periode simulasi
     * @param  array<int, float>|null  $produksiAktualSimulasi  wajib diisi bila metode=produksi_aktual
     * @return array<int, float> rencana_produksi 12 bulan (indeks 0..11)
     */
    public function hitungSemua(array $historiSebelumSimulasi, array $permintaanAktualSimulasi, string $metode, ?array $produksiAktualSimulasi = null): array
    {
        return match ($metode) {
            'produksi_aktual' => $this->produksiAktual($produksiAktualSimulasi),
            'naif_bulan_lalu' => $this->naifBulanLalu($historiSebelumSimulasi, $permintaanAktualSimulasi),
            'rata_rata_bergerak' => $this->rataRataBergerak($historiSebelumSimulasi, $permintaanAktualSimulasi),
            default => throw new InvalidArgumentException("Metode pembanding tidak dikenal: {$metode}"),
        };
    }

    /**
     * @param  array<int, float>|null  $data
     * @return array<int, float>
     */
    private function produksiAktual(?array $data): array
    {
        if ($data === null || count($data) !== 12) {
            throw new RuntimeException('Data produksi aktual tidak tersedia atau jumlahnya bukan 12 bulan.');
        }

        return array_values($data);
    }

    /**
     * @param  array<int, float>  $historiSebelumSimulasi
     * @param  array<int, float>  $permintaanAktualSimulasi
     * @return array<int, float>
     */
    private function naifBulanLalu(array $historiSebelumSimulasi, array $permintaanAktualSimulasi): array
    {
        $gabungan = [...array_values($historiSebelumSimulasi), ...array_values($permintaanAktualSimulasi)];
        $offset = count($historiSebelumSimulasi);

        $hasil = [];
        for ($i = 0; $i < count($permintaanAktualSimulasi); $i++) {
            $hasil[$i] = $gabungan[$offset + $i - 1];
        }

        return $hasil;
    }

    /**
     * @param  array<int, float>  $historiSebelumSimulasi
     * @param  array<int, float>  $permintaanAktualSimulasi
     * @return array<int, float>
     */
    private function rataRataBergerak(array $historiSebelumSimulasi, array $permintaanAktualSimulasi): array
    {
        $gabungan = [...array_values($historiSebelumSimulasi), ...array_values($permintaanAktualSimulasi)];
        $offset = count($historiSebelumSimulasi);

        $hasil = [];
        for ($i = 0; $i < count($permintaanAktualSimulasi); $i++) {
            $indeksSekarang = $offset + $i;
            $jendela = array_slice($gabungan, $indeksSekarang - 3, 3);
            $hasil[$i] = array_sum($jendela) / count($jendela);
        }

        return $hasil;
    }
}
