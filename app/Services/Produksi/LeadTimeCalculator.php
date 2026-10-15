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
 *   L_produksi = (jalur kritis - tumpang tindih) + (hari tahap terlama - 1)
 *
 *   hari tahap        = ceil(jumlah di tahap / kapasitas tahap), kapasitas dipakai
 *                       bersama semua produk yang dikerjakan di periode itu
 *   jalur kritis      = jalur pohon BOM produk utama dengan (jumlah waktu_proses
 *                       - tumpang tindih) terbesar
 *   tumpang tindih    = sambungan di jalur yang boleh mulai di hari yang sama:
 *                       tahap sebelumnya sanggup menyediakan satu muatan penuh
 *                       tahap ini dalam sehari, dan jumlah di tahap ini (per
 *                       tingkat pohon, semua produk) minimal satu muatan
 *                       (contoh: cetak 1.200 ke coating 780)
 *
 * Produk lain di periode yang sama hanya ikut memakai kapasitas. Jalur kritis
 * dan bahan yang harus dibeli diambil dari produk utama. L_beli = lead time
 * terbesar dari bahan produk utama yang total kebutuhannya (semua produk)
 * melebihi stok, kecuali pemeriksaan stok dimatikan (simulasi masa lalu
 * memakai L_beli penuh).
 */
class LeadTimeCalculator
{
    /**
     * Waktu tunggu satu barang jadi. $bebanLain berisi produk lain yang dikerjakan
     * di periode yang sama ([barang_id => jumlah]), hanya ikut memakai kapasitas.
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

        return $this->hitungGabungan($pesanan, $awalPeriodePenjualan, $periksaStok, [$barangJadi->id]);
    }

    /**
     * Waktu tunggu untuk beberapa barang jadi yang dikerjakan bersamaan.
     *
     * @param  array<int, float>  $pesanan  [barang_id => jumlah dalam satuan barang jadi]
     * @param  list<int>|null  $produkUtama  produk yang dihitung jalur kritis dan bahannya;
     *                                       null = semua produk di pesanan
     * @return array<string, mixed>
     */
    public function hitungGabungan(
        array $pesanan,
        ?DateTimeInterface $awalPeriodePenjualan = null,
        bool $periksaStok = true,
        ?array $produkUtama = null,
    ): array {
        $utama = array_flip(array_map('intval', $produkUtama ?? array_keys($pesanan)));
        $exploder = new BomExploder();
        $bahanSemua = [];     // barang_id => jumlah kebutuhan semua produk
        $bahanUtama = [];     // barang_id => true bila dipakai produk utama
        $jumlahTahapan = [];  // tahapan_id => jumlah semua produk
        $jumlahTingkat = [];  // "tahapan_id|kedalaman" => jumlah semua produk
        $simpul = [];         // pohon resep produk utama (indeks global)
        $anak = [];           // indeks simpul induk => list indeks anak
        $akar = [];
        $satuanTahapan = [];

        foreach ($pesanan as $barangId => $jumlah) {
            if ($jumlah <= 0) {
                continue;
            }
            $hasil = $exploder->ledakkan(Barang::findOrFail($barangId), (float) $jumlah);
            $isUtama = isset($utama[(int) $barangId]);

            foreach ($hasil['bahan_baku'] as $b) {
                $bahanSemua[$b['barang_id']] = ($bahanSemua[$b['barang_id']] ?? 0.0) + $b['jumlah'];
                if ($isUtama) {
                    $bahanUtama[$b['barang_id']] = true;
                }
            }
            foreach ($hasil['tahapan'] as $t) {
                $jumlahTahapan[$t['tahapan_id']] = ($jumlahTahapan[$t['tahapan_id']] ?? 0.0) + $t['jumlah'];
            }
            foreach ($hasil['langkah'] as $l) {
                $kunci = $l['tahapan_id'].'|'.$l['kedalaman'];
                $jumlahTingkat[$kunci] = ($jumlahTingkat[$kunci] ?? 0.0) + $l['jumlah'];
                $satuanTahapan[$l['tahapan_id']] ??= $l['barang_id'];
            }

            if ($isUtama) {
                $dasar = count($simpul);
                foreach ($hasil['langkah'] as $i => $l) {
                    $simpul[$dasar + $i] = $l;
                    if ($l['induk'] === null) {
                        $akar[] = $dasar + $i;
                    } else {
                        $anak[$dasar + $l['induk']][] = $dasar + $i;
                    }
                }
            }
        }

        $tahapan = TahapanProduksi::whereIn('id', array_keys($jumlahTahapan))->get()->keyBy('id');
        $satuanHasil = Barang::whereIn('id', array_values($satuanTahapan))->pluck('satuan', 'id');

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
                'satuan' => $satuanHasil[$satuanTahapan[$t->id]] ?? null,
                'kapasitas_per_hari' => (float) $t->kapasitas_per_hari,
                'waktu_proses_hari' => (float) $t->waktu_proses_hari,
                'hari_kapasitas' => $hari,
            ];
        }

        // Jalur kritis: dari setiap simpul, pilih anak dengan lama efektif terbesar
        // (waktu proses dikurangi tumpang tindih), supaya hasil tidak bergantung urutan.
        $boleh = function (array $hulu, array $hilir) use ($tahapan, $jumlahTingkat): bool {
            $tHulu = $tahapan[$hulu['tahapan_id']];
            $tHilir = $tahapan[$hilir['tahapan_id']];
            $kap = (float) $tHilir->kapasitas_per_hari;

            return (float) $tHilir->waktu_proses_hari > 0 && $kap > 0
                && (float) $tHulu->kapasitas_per_hari >= $kap
                && ($jumlahTingkat[$hilir['tahapan_id'].'|'.$hilir['kedalaman']] ?? 0.0) >= $kap;
        };
        $memo = [];
        $terbaik = function (int $i) use (&$terbaik, &$memo, $simpul, $anak, $tahapan, $boleh): array {
            if (isset($memo[$i])) {
                return $memo[$i];
            }
            $waktu = (float) $tahapan[$simpul[$i]['tahapan_id']]->waktu_proses_hari;
            $pilihan = ['efektif' => 0.0, 'waktu' => 0.0, 'tumpang' => 0];
            foreach ($anak[$i] ?? [] as $a) {
                $calon = $terbaik($a);
                $tumpang = $boleh($simpul[$a], $simpul[$i]) ? 1 : 0;
                $calon = ['efektif' => $calon['efektif'] - $tumpang, 'waktu' => $calon['waktu'], 'tumpang' => $calon['tumpang'] + $tumpang];
                if ($calon['efektif'] > $pilihan['efektif']
                    || ($calon['efektif'] === $pilihan['efektif'] && $calon['waktu'] > $pilihan['waktu'])) {
                    $pilihan = $calon;
                }
            }

            return $memo[$i] = [
                'efektif' => $pilihan['efektif'] + $waktu,
                'waktu' => $pilihan['waktu'] + $waktu,
                'tumpang' => $pilihan['tumpang'],
            ];
        };

        $kritis = null;
        foreach ($akar as $a) {
            $calon = $terbaik($a);
            if ($kritis === null || $calon['efektif'] > $kritis['efektif']
                || ($calon['efektif'] === $kritis['efektif'] && $calon['waktu'] > $kritis['waktu'])) {
                $kritis = $calon;
            }
        }

        $leadTimeProduksi = $kritis === null
            ? 0.0
            : max(1.0, $kritis['efektif'] + max(0, $hariTerlama - 1));

        // L_beli: bahan produk utama yang kurang (atau semuanya bila stok tidak diperiksa).
        $bahanKurang = [];
        $leadTimePembelian = 0.0;
        foreach (Barang::whereIn('id', array_keys($bahanUtama))->orderBy('kode_barang')->get() as $b) {
            $kebutuhan = $bahanSemua[$b->id];
            if ($periksaStok && $kebutuhan <= (float) $b->stok_tersedia) {
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
            'jalur_kritis_hari' => $kritis['waktu'] ?? 0.0,
            'hari_tahap_terlama' => $hariTerlama,
            'tumpang_tindih_hari' => $kritis['tumpang'] ?? 0,
            'rincian_tahapan' => $rincian,
            'bahan_kurang' => $bahanKurang,
        ];
    }
}
