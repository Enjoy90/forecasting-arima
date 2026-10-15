<?php

namespace App\Services\Stok;

use App\Models\Barang;
use App\Models\KebutuhanBahan;
use App\Models\Peramalan;
use App\Models\TargetProduksi;
use App\Services\Produksi\BomExploder;
use App\Services\Produksi\LeadTimeCalculator;
use App\Support\Math\Distribution;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Rencana stok bulanan (docs/01 §6): forecast -> waktu tunggu -> Safety
 * Stock -> Reorder Point -> Target Produksi -> ledak BOM jadi rekomendasi
 * kebutuhan bahan baku.
 *
 * Catatan cakupan: docs tidak memberi rumus eksplisit untuk "Safety Stock
 * bahan" per komponen (beda dengan Safety Stock barang jadi yang rumusnya
 * jelas). Di sini dipakai pendekatan konsisten: ledakkan BOM yang sama
 * dengan jumlah = Safety Stock barang jadi, sehingga safety stock tiap
 * bahan mengikuti rasio BOM yang sama seperti kebutuhan utamanya,
 * bukan formula baru, hanya memakai ulang mekanisme ledak BOM yang sudah ada.
 */
class TargetProduksiPlanner
{
    public function rencanakan(Peramalan $peramalan, string $periode, ?int $userId = null): TargetProduksi
    {
        $barang = $peramalan->barang;

        $hasilForecast = $peramalan->hasil()
            ->where('periode', $periode)
            ->where('tipe', 'forecast')
            ->first();

        if ($hasilForecast === null) {
            throw new RuntimeException("Tidak ada hasil forecast untuk periode {$periode} pada peramalan ini.");
        }

        $prediksiPenjualan = (float) $hasilForecast->nilai_prediksi;
        $sigmaError = sqrt((float) $peramalan->sigma_kuadrat);

        // Kapasitas tahapan dipakai bersama semua produk, jadi prakiraan produk
        // lain di periode yang sama ikut dihitung sebagai beban (docs/07).
        $waktuTunggu = (new LeadTimeCalculator())->hitung(
            $barang,
            $prediksiPenjualan,
            Carbon::parse($periode.'-01'),
            $this->bebanProdukLain($barang, $periode)
        );

        $ltPeriode = $waktuTunggu['lead_time_total_hari'] / 30;
        $nilaiZ = Distribution::zScore((float) $barang->service_level / 100);
        $safetyStock = $nilaiZ * $sigmaError * sqrt($ltPeriode);
        $reorderPoint = ($prediksiPenjualan / 30 * $waktuTunggu['lead_time_total_hari']) + $safetyStock;

        $stokBarangJadi = (float) $barang->stok_tersedia;
        $stokSetengahJadi = $this->stokSetengahJadiSiapRakit($barang);

        $targetProduksiMentah = $prediksiPenjualan + $safetyStock - $stokBarangJadi - $stokSetengahJadi;
        $jumlahTargetProduksi = max(0.0, $targetProduksiMentah);

        $statusStok = match (true) {
            $targetProduksiMentah <= 0 => 'aman',
            $stokBarangJadi <= (float) $barang->stok_minimum => 'kritis',
            default => 'segera_produksi',
        };

        $targetProduksi = TargetProduksi::updateOrCreate(
            ['peramalan_id' => $peramalan->id, 'barang_id' => $barang->id, 'periode' => $periode],
            [
                'user_id' => $userId,
                'prediksi_penjualan' => $prediksiPenjualan,
                'stok_barang_jadi' => $stokBarangJadi,
                'stok_setengah_jadi' => $stokSetengahJadi,
                'standar_deviasi_error' => $sigmaError,
                'lead_time_pembelian_hari' => $waktuTunggu['lead_time_pembelian_hari'],
                'lead_time_produksi_hari' => $waktuTunggu['lead_time_produksi_hari'],
                'lead_time_total_hari' => $waktuTunggu['lead_time_total_hari'],
                'tanggal_mulai_produksi' => $waktuTunggu['tanggal_mulai_produksi'],
                'tanggal_pesan_bahan' => $waktuTunggu['tanggal_pesan_bahan'],
                'nilai_z' => $nilaiZ,
                'safety_stock' => $safetyStock,
                'reorder_point' => $reorderPoint,
                'jumlah_target_produksi' => $jumlahTargetProduksi,
                'status_stok' => $statusStok,
            ]
        );

        $targetProduksi->kebutuhanBahan()->delete();
        if ($jumlahTargetProduksi > 0) {
            $this->simpanKebutuhanBahan($targetProduksi, $barang, $jumlahTargetProduksi, $safetyStock);
        }

        return $targetProduksi->fresh();
    }

    /**
     * Prakiraan penjualan produk jadi lain untuk periode yang sama, diambil dari
     * peramalan terakhir masing-masing produk.
     *
     * @return array<int, float> [barang_id => jumlah]
     */
    private function bebanProdukLain(Barang $barang, string $periode): array
    {
        $beban = [];
        foreach (Barang::diramalkan()->aktif()->where('id', '!=', $barang->id)->get() as $lain) {
            // Produk tanpa resep aktif tidak bisa dihitung bebannya; dilewati supaya
            // tidak menggagalkan rencana produk yang sedang dihitung.
            if ($lain->bomAktif() === null) {
                continue;
            }
            $peramalan = Peramalan::where('barang_id', $lain->id)->latest('id')->first();
            $nilai = $peramalan?->hasil()->where('tipe', 'forecast')->where('periode', $periode)->value('nilai_prediksi');
            if ($nilai !== null && (float) $nilai > 0) {
                $beban[$lain->id] = (float) $nilai;
            }
        }

        return $beban;
    }

    /**
     * Stok komponen setengah jadi yang langsung dipakai BOM aktif barang jadi
     * ini ("siap rakit" untuk tahapan terakhir), dalam satuan barang jadi.
     * Contoh: resep 1 dz butuh 12 rakitan, jadi 400 rakitan setara 33,3 dz.
     */
    private function stokSetengahJadiSiapRakit(Barang $barang): float
    {
        $bom = $barang->bomAktif();
        if ($bom === null) {
            return 0.0;
        }

        return $bom->detail
            ->filter(fn ($d) => $d->barang->jenis_barang === Barang::JENIS_SETENGAH_JADI && (float) $d->jumlah_kebutuhan > 0)
            ->sum(fn ($d) => (float) $d->barang->stok_tersedia * (float) $bom->jumlah_output / (float) $d->jumlah_kebutuhan);
    }

    private function simpanKebutuhanBahan(TargetProduksi $targetProduksi, Barang $barang, float $jumlahTargetProduksi, float $safetyStock): void
    {
        $exploder = new BomExploder();

        $kebutuhan = collect($exploder->ledakkan($barang, $jumlahTargetProduksi)['bahan_baku'])->keyBy('barang_id');
        $safetyStockBahan = $safetyStock > 0
            ? collect($exploder->ledakkan($barang, $safetyStock)['bahan_baku'])->keyBy('barang_id')
            : collect();

        foreach ($kebutuhan as $barangId => $item) {
            $bahan = Barang::findOrFail($barangId);
            $ssBahan = (float) ($safetyStockBahan[$barangId]['jumlah'] ?? 0.0);

            $kekuranganMentah = $item['jumlah'] - (float) $bahan->stok_tersedia;
            $kekurangan = max(0.0, $kekuranganMentah);

            $status = match (true) {
                $kekurangan <= 0 => 'cukup',
                (float) $bahan->stok_tersedia <= 0 => 'mendesak',
                default => 'perlu_beli',
            };

            KebutuhanBahan::create([
                'target_produksi_id' => $targetProduksi->id,
                'barang_id' => $barangId,
                'tahapan_id' => $item['tahapan_id'],
                'jumlah_kebutuhan' => $item['jumlah'],
                'stok_tersedia' => (float) $bahan->stok_tersedia,
                'kekurangan' => $kekurangan,
                'safety_stock_bahan' => $ssBahan,
                'qty_rekomendasi_beli' => $kekurangan > 0 ? $kekurangan + $ssBahan : 0.0,
                'satuan' => $item['satuan'],
                'status' => $status,
            ]);
        }
    }
}
