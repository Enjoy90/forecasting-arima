<?php

namespace App\Services\Produksi;

use App\Models\Barang;
use RuntimeException;

/**
 * Ledak BOM rekursif dari barang jadi turun sampai bahan baku (docs/01 §1.3:
 * BOM bertingkat, mis. sekop <- sekop rakitan <- kepala ter-coating <- ...).
 *
 * Rumus satu level (kebutuhanUntuk() di model Bom, hariUntuk() di model
 * TahapanProduksi) sudah dibuat pada fondasi Fase 0; kelas ini mengurus
 * jalur rekursifnya dan mengakumulasi hasil akhir lintas cabang BOM.
 */
class BomExploder
{
    /**
     * @return array{bahan_baku: array<int, array{barang_id: int, jumlah: float, satuan: ?string, tahapan_id: int}>, tahapan: array<int, array{tahapan_id: int, jumlah: float}>}
     */
    public function ledakkan(Barang $barang, float $jumlahTarget): array
    {
        $bahanBaku = [];
        $satuanBahan = [];
        $tahapanBahan = [];
        $tahapan = [];

        $this->jelajahi($barang, $jumlahTarget, $bahanBaku, $satuanBahan, $tahapanBahan, $tahapan, []);

        $daftarBahanBaku = [];
        foreach ($bahanBaku as $barangId => $jumlah) {
            $daftarBahanBaku[] = [
                'barang_id' => $barangId,
                'jumlah' => $jumlah,
                'satuan' => $satuanBahan[$barangId] ?? null,
                'tahapan_id' => $tahapanBahan[$barangId],
            ];
        }

        $daftarTahapan = [];
        foreach ($tahapan as $tahapanId => $jumlah) {
            $daftarTahapan[] = ['tahapan_id' => $tahapanId, 'jumlah' => $jumlah];
        }

        return ['bahan_baku' => $daftarBahanBaku, 'tahapan' => $daftarTahapan];
    }

    /**
     * @param  array<int, float>  $bahanBaku
     * @param  array<int, string|null>  $satuanBahan
     * @param  array<int, int>  $tahapanBahan
     * @param  array<int, float>  $tahapan
     * @param  array<int, bool>  $jalur  barang_id yang sedang dieksplorasi di jalur rekursi saat ini,
     *                                   dipakai mendeteksi BOM berputar (mis. A butuh B, B butuh A lagi)
     */
    private function jelajahi(Barang $barang, float $jumlah, array &$bahanBaku, array &$satuanBahan, array &$tahapanBahan, array &$tahapan, array $jalur): void
    {
        if (isset($jalur[$barang->id])) {
            throw new RuntimeException("BOM berputar terdeteksi: {$barang->kode_barang} ({$barang->nama_barang}) menjadi komponen dari jalur produksinya sendiri.");
        }
        $jalur[$barang->id] = true;

        $bom = $barang->bomAktif();
        if ($bom === null) {
            throw new RuntimeException("Barang {$barang->kode_barang} ({$barang->nama_barang}) tidak punya BOM aktif, tidak bisa diledakkan.");
        }

        $tahapan[$bom->tahapan_id] = ($tahapan[$bom->tahapan_id] ?? 0.0) + $jumlah;

        foreach ($bom->kebutuhanUntuk($jumlah) as $komponen) {
            $komponenBarang = Barang::findOrFail($komponen['barang_id']);

            if ($komponenBarang->jenis_barang === Barang::JENIS_BAHAN_BAKU) {
                $bahanBaku[$komponen['barang_id']] = ($bahanBaku[$komponen['barang_id']] ?? 0.0) + $komponen['jumlah'];
                $satuanBahan[$komponen['barang_id']] = $komponen['satuan'];
                $tahapanBahan[$komponen['barang_id']] = $bom->tahapan_id;

                continue;
            }

            $this->jelajahi($komponenBarang, $komponen['jumlah'], $bahanBaku, $satuanBahan, $tahapanBahan, $tahapan, $jalur);
        }
    }
}
