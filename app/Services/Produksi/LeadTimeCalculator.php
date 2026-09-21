<?php

namespace App\Services\Produksi;

use App\Models\Barang;
use App\Models\TahapanProduksi;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Waktu tunggu operasional pabrik (docs/01 §4): L_beli (bahan baku paling
 * lama datang) + L_produksi (jumlah hari seluruh tahapan yang dilewati
 * rantai BOM), lalu tanggal mulai produksi & tanggal pesan bahan.
 */
class LeadTimeCalculator
{
    /**
     * @return array{lead_time_pembelian_hari: float, lead_time_produksi_hari: float, lead_time_total_hari: float, tanggal_mulai_produksi: ?Carbon, tanggal_pesan_bahan: ?Carbon}
     */
    public function hitung(Barang $barangJadi, float $jumlahTarget, ?DateTimeInterface $awalPeriodePenjualan = null): array
    {
        $hasilLedak = (new BomExploder())->ledakkan($barangJadi, $jumlahTarget);

        $leadTimePembelian = 0.0;
        foreach ($hasilLedak['bahan_baku'] as $b) {
            $bahan = Barang::findOrFail($b['barang_id']);
            $leadTimePembelian = max($leadTimePembelian, (float) $bahan->lead_time_hari);
        }

        $leadTimeProduksi = 0.0;
        foreach ($hasilLedak['tahapan'] as $t) {
            $tahapan = TahapanProduksi::findOrFail($t['tahapan_id']);
            $leadTimeProduksi += $tahapan->hariUntuk($t['jumlah']);
        }

        $leadTimeTotal = $leadTimePembelian + $leadTimeProduksi;

        $tanggalMulaiProduksi = null;
        $tanggalPesanBahan = null;
        if ($awalPeriodePenjualan !== null) {
            $tanggalMulaiProduksi = Carbon::parse($awalPeriodePenjualan)->subDays((int) ceil($leadTimeProduksi));
            $tanggalPesanBahan = $tanggalMulaiProduksi->copy()->subDays((int) ceil($leadTimePembelian));
        }

        return [
            'lead_time_pembelian_hari' => $leadTimePembelian,
            'lead_time_produksi_hari' => $leadTimeProduksi,
            'lead_time_total_hari' => $leadTimeTotal,
            'tanggal_mulai_produksi' => $tanggalMulaiProduksi,
            'tanggal_pesan_bahan' => $tanggalPesanBahan,
        ];
    }
}
