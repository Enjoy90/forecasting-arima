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
     * Selain bahan baku, hasilnya memuat kebutuhan barang setengah jadi
     * (kepala, handle, rakitan, ...) dan "langkah": setiap resep yang dilewati
     * beserta resep induknya, dipakai LeadTimeCalculator mencari jalur kritis.
     *
     * @return array{bahan_baku: array<int, array{barang_id: int, jumlah: float, satuan: ?string, tahapan_id: int}>, tahapan: array<int, array{tahapan_id: int, jumlah: float}>, setengah_jadi: array<int, array{barang_id: int, jumlah: float}>, langkah: array<int, array{barang_id: int, tahapan_id: int, jumlah: float, induk_barang_id: ?int}>}
     */
    public function ledakkan(Barang $barang, float $jumlahTarget): array
    {
        $bahanBaku = [];
        $satuanBahan = [];
        $tahapanBahan = [];
        $tahapan = [];
        $setengahJadi = [];
        $langkah = [];

        $this->jelajahi($barang, $jumlahTarget, $bahanBaku, $satuanBahan, $tahapanBahan, $tahapan, [], $setengahJadi, $langkah, null);

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

        $daftarSetengahJadi = [];
        foreach ($setengahJadi as $barangId => $jumlah) {
            $daftarSetengahJadi[] = ['barang_id' => $barangId, 'jumlah' => $jumlah];
        }

        return [
            'bahan_baku' => $daftarBahanBaku,
            'tahapan' => $daftarTahapan,
            'setengah_jadi' => $daftarSetengahJadi,
            'langkah' => array_values($langkah),
        ];
    }

    /**
     * @param  array<int, float>  $bahanBaku
     * @param  array<int, string|null>  $satuanBahan
     * @param  array<int, int>  $tahapanBahan
     * @param  array<int, float>  $tahapan
     * @param  array<int, bool>  $jalur  barang_id yang sedang dieksplorasi di jalur rekursi saat ini,
     *                                   dipakai mendeteksi BOM berputar (mis. A butuh B, B butuh A lagi)
     * @param  array<int, float>  $setengahJadi
     * @param  array<int, array{barang_id: int, tahapan_id: int, jumlah: float, induk_barang_id: ?int}>  $langkah
     */
    private function jelajahi(Barang $barang, float $jumlah, array &$bahanBaku, array &$satuanBahan, array &$tahapanBahan, array &$tahapan, array $jalur, array &$setengahJadi, array &$langkah, ?int $indukBarangId): void
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

        // Satu barang bisa dipakai di beberapa cabang (mis. handle untuk dua
        // produk); jumlahnya diakumulasi pada satu langkah per barang.
        if (isset($langkah[$barang->id])) {
            $langkah[$barang->id]['jumlah'] += $jumlah;
        } else {
            $langkah[$barang->id] = [
                'barang_id' => $barang->id,
                'tahapan_id' => $bom->tahapan_id,
                'jumlah' => $jumlah,
                'induk_barang_id' => $indukBarangId,
            ];
        }

        foreach ($bom->kebutuhanUntuk($jumlah) as $komponen) {
            $komponenBarang = Barang::findOrFail($komponen['barang_id']);

            if ($komponenBarang->jenis_barang === Barang::JENIS_BAHAN_BAKU) {
                $bahanBaku[$komponen['barang_id']] = ($bahanBaku[$komponen['barang_id']] ?? 0.0) + $komponen['jumlah'];
                $satuanBahan[$komponen['barang_id']] = $komponen['satuan'];
                $tahapanBahan[$komponen['barang_id']] = $bom->tahapan_id;

                continue;
            }

            $setengahJadi[$komponen['barang_id']] = ($setengahJadi[$komponen['barang_id']] ?? 0.0) + $komponen['jumlah'];
            $this->jelajahi($komponenBarang, $komponen['jumlah'], $bahanBaku, $satuanBahan, $tahapanBahan, $tahapan, $jalur, $setengahJadi, $langkah, $barang->id);
        }
    }
}
