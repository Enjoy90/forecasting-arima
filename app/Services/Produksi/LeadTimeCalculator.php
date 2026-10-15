<?php

namespace App\Services\Produksi;

use App\Models\Barang;
use App\Models\TahapanProduksi;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Waktu tunggu operasional pabrik (docs/01 §4, direvisi docs/07).
 *
 * L_produksi mengikuti cara kerja pabrik: tahap di cabang BOM yang berbeda
 * berjalan bersamaan (cetak kepala dan handle), dan tahap berjalan
 * sambung-menyambung setiap hari, jadi seluruh target tidak harus selesai di
 * satu tahap sebelum tahap berikutnya mulai:
 *
 *   L_produksi = jalur kritis + (hari tahap terlama - 1) - tumpang tindih
 *
 *   jalur kritis      = jumlah waktu_proses terbesar dari daun BOM ke barang jadi
 *   hari tahap        = ceil(jumlah di tahap / kapasitas tahap), kapasitas dipakai
 *                       bersama oleh semua produk yang dihitung
 *   tumpang tindih    = sambungan di jalur kritis yang boleh mulai di hari yang
 *                       sama: tahap sebelumnya sanggup menyediakan satu muatan
 *                       penuh tahap ini dalam sehari, dan jumlahnya minimal
 *                       satu muatan (contoh: cetak 1.200 ke coating 780)
 *
 * L_beli hanya dihitung dari bahan yang kebutuhannya melebihi stok, kecuali
 * pemeriksaan stok dimatikan (simulasi masa lalu memakai L_beli penuh).
 */
class LeadTimeCalculator
{
    /**
     * Waktu tunggu satu barang jadi. $bebanLain berisi produk lain yang dikerjakan
     * di periode yang sama ([barang_id => jumlah]), ikut memakai kapasitas tahapan.
     *
     * @param  array<int, float>  $bebanLain
     * @return array<string, mixed>
     */
    public function hitung(
        Barang $barangJadi,
        float $jumlahTarget,
        ?DateTimeInterface $awalPeriodePenjualan = null,
        array $bebanLain = [],
        bool $periksaStok = true,
    ): array {
        $pesanan = [$barangJadi->id => $jumlahTarget];
        foreach ($bebanLain as $barangId => $jumlah) {
            if ((int) $barangId !== $barangJadi->id) {
                $pesanan[(int) $barangId] = (float) $jumlah;
            }
        }

        return $this->hitungGabungan($pesanan, $awalPeriodePenjualan, $periksaStok);
    }

    /**
     * Waktu tunggu untuk beberapa barang jadi yang dikerjakan bersamaan.
     *
     * @param  array<int, float>  $pesanan  [barang_id => jumlah dalam satuan barang jadi]
     * @return array<string, mixed>
     */
    public function hitungGabungan(array $pesanan, ?DateTimeInterface $awalPeriodePenjualan = null, bool $periksaStok = true): array
    {
        $exploder = new BomExploder();
        $bahan = [];          // barang_id => jumlah
        $jumlahTahapan = [];  // tahapan_id => jumlah
        $tahapanBarang = [];  // barang_id hasil => tahapan_id
        $anak = [];           // barang_id induk => [barang_id anak => true]
        $akar = [];

        foreach ($pesanan as $barangId => $jumlah) {
            if ($jumlah <= 0) {
                continue;
            }
            $hasil = $exploder->ledakkan(Barang::findOrFail($barangId), (float) $jumlah);
            foreach ($hasil['bahan_baku'] as $b) {
                $bahan[$b['barang_id']] = ($bahan[$b['barang_id']] ?? 0.0) + $b['jumlah'];
            }
            foreach ($hasil['tahapan'] as $t) {
                $jumlahTahapan[$t['tahapan_id']] = ($jumlahTahapan[$t['tahapan_id']] ?? 0.0) + $t['jumlah'];
            }
            foreach ($hasil['langkah'] as $l) {
                $tahapanBarang[$l['barang_id']] = $l['tahapan_id'];
                if ($l['induk_barang_id'] === null) {
                    $akar[$l['barang_id']] = true;
                } else {
                    $anak[$l['induk_barang_id']][$l['barang_id']] = true;
                }
            }
        }

        $tahapan = TahapanProduksi::whereIn('id', array_keys($jumlahTahapan))->get()->keyBy('id');
        $satuanHasil = Barang::whereIn('id', array_keys($tahapanBarang))->pluck('satuan', 'id');
        $satuanTahapan = [];
        foreach ($tahapanBarang as $barangId => $tahapanId) {
            $satuanTahapan[$tahapanId] ??= $satuanHasil[$barangId] ?? null;
        }

        // Hari tiap tahap dari kapasitas gabungan.
        $rincian = [];
        $hariTerlama = 0;
        foreach ($tahapan->sortBy('urutan') as $t) {
            $jumlah = $jumlahTahapan[$t->id];
            $hari = (float) $t->kapasitas_per_hari > 0 ? (int) ceil($jumlah / (float) $t->kapasitas_per_hari - 1e-9) : 1;
            $hariTerlama = max($hariTerlama, $hari);
            $rincian[] = [
                'kode_tahapan' => $t->kode_tahapan,
                'nama_tahapan' => $t->nama_tahapan,
                'jumlah' => $jumlah,
                'satuan' => $satuanTahapan[$t->id] ?? null,
                'kapasitas_per_hari' => (float) $t->kapasitas_per_hari,
                'waktu_proses_hari' => (float) $t->waktu_proses_hari,
                'hari_kapasitas' => $hari,
            ];
        }

        // Jalur kritis: jumlah waktu_proses terbesar dari daun sampai barang jadi.
        $memo = [];
        $panjang = function (int $barangId) use (&$panjang, &$memo, $anak, $tahapanBarang, $tahapan): array {
            if (isset($memo[$barangId])) {
                return $memo[$barangId];
            }
            $t = $tahapan[$tahapanBarang[$barangId]];
            $terbaik = ['hari' => 0.0, 'jalur' => []];
            foreach (array_keys($anak[$barangId] ?? []) as $a) {
                $calon = $panjang($a);
                if ($calon['hari'] > $terbaik['hari']) {
                    $terbaik = $calon;
                }
            }

            return $memo[$barangId] = [
                'hari' => $terbaik['hari'] + (float) $t->waktu_proses_hari,
                'jalur' => [...$terbaik['jalur'], $t->id],
            ];
        };

        $kritis = ['hari' => 0.0, 'jalur' => []];
        foreach (array_keys($akar) as $a) {
            $calon = $panjang($a);
            if ($calon['hari'] > $kritis['hari']) {
                $kritis = $calon;
            }
        }

        // Tumpang tindih di sepanjang jalur kritis (dari hulu ke hilir).
        $tumpangTindih = 0;
        for ($i = 1; $i < count($kritis['jalur']); $i++) {
            $sebelum = $tahapan[$kritis['jalur'][$i - 1]];
            $ini = $tahapan[$kritis['jalur'][$i]];
            $kapIni = (float) $ini->kapasitas_per_hari;
            if ((float) $ini->waktu_proses_hari > 0 && $kapIni > 0
                && (float) $sebelum->kapasitas_per_hari >= $kapIni
                && $jumlahTahapan[$ini->id] >= $kapIni) {
                $tumpangTindih++;
            }
        }

        $leadTimeProduksi = $kritis['hari'] > 0
            ? max(1.0, $kritis['hari'] + max(0, $hariTerlama - 1) - $tumpangTindih)
            : 0.0;

        // L_beli: hanya bahan yang kurang (atau semua bahan bila stok tidak diperiksa).
        $bahanKurang = [];
        $leadTimePembelian = 0.0;
        foreach (Barang::whereIn('id', array_keys($bahan))->orderBy('kode_barang')->get() as $b) {
            $kebutuhan = $bahan[$b->id];
            $kurang = ! $periksaStok || $kebutuhan > (float) $b->stok_tersedia;
            if (! $kurang) {
                continue;
            }
            $leadTimePembelian = max($leadTimePembelian, (float) $b->lead_time_hari);
            $bahanKurang[] = [
                'kode_barang' => $b->kode_barang,
                'nama_barang' => $b->nama_barang,
                'kebutuhan' => $kebutuhan,
                'stok_tersedia' => (float) $b->stok_tersedia,
                'satuan' => $b->satuan,
                'lead_time_hari' => (float) $b->lead_time_hari,
            ];
        }

        $tanggalMulaiProduksi = null;
        $tanggalPesanBahan = null;
        if ($awalPeriodePenjualan !== null) {
            // Produksi hanya di hari kerja (Senin sampai Jumat).
            $tanggalMulaiProduksi = Carbon::parse($awalPeriodePenjualan)->subWeekdays((int) ceil($leadTimeProduksi));
            $tanggalPesanBahan = $tanggalMulaiProduksi->copy()->subDays((int) ceil($leadTimePembelian));
        }

        return [
            'lead_time_pembelian_hari' => $leadTimePembelian,
            'lead_time_produksi_hari' => $leadTimeProduksi,
            'lead_time_total_hari' => $leadTimePembelian + $leadTimeProduksi,
            'tanggal_mulai_produksi' => $tanggalMulaiProduksi,
            'tanggal_pesan_bahan' => $tanggalPesanBahan,
            'jalur_kritis_hari' => $kritis['hari'],
            'hari_tahap_terlama' => $hariTerlama,
            'tumpang_tindih_hari' => $tumpangTindih,
            'rincian_tahapan' => $rincian,
            'bahan_kurang' => $bahanKurang,
        ];
    }
}
