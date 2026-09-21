<?php

namespace App\Services\Stok;

use App\Exceptions\StokTidakCukupException;
use App\Models\Barang;
use App\Models\MutasiStok;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pencatatan mutasi stok terpusat — SATU-SATUNYA pintu masuk perubahan stok.
 *
 * Pembelian, produksi, penjualan, dan stok opname semuanya memanggil kelas ini.
 * Tidak ada modul yang boleh mengubah `barang.stok_tersedia` secara langsung,
 * karena stok tanpa baris mutasi berarti stok tanpa jejak asal-usul: saat angka
 * stok tidak cocok dengan kenyataan di gudang, tidak ada yang bisa ditelusuri.
 *
 * Pembagian tugas:
 *   - Kelas ini  : menghitung stok_awal & stok_akhir, lalu menulis baris mutasi.
 *   - Observer   : menyalin stok_akhir ke barang.stok_tersedia (MutasiStokObserver).
 *
 * Arti `jumlah` per jenis mutasi:
 *   - MASUK       : selalu positif, menambah stok.
 *   - KELUAR      : selalu positif, mengurangi stok.
 *   - PENYESUAIAN : bertanda. Positif berarti stok fisik lebih banyak dari
 *                   catatan, negatif berarti lebih sedikit.
 *
 * Catatan ketelitian angka:
 * Kolom mutasi_stok.jumlah/stok_awal/stok_akhir bertipe decimal(15,4),
 * sedangkan barang.stok_tersedia bertipe integer. Rantai mutasi karena itu
 * disusun dari stok_akhir mutasi sebelumnya (bukan dari barang.stok_tersedia)
 * supaya riwayatnya tetap utuh walau nilai pada barang dibulatkan.
 */
class StockMutator
{
    /** @var list<string> */
    public const SUMBER = ['pembelian', 'produksi', 'penjualan', 'opname', 'lainnya'];

    /** @var list<string> */
    public const JENIS = [MutasiStok::MASUK, MutasiStok::KELUAR, MutasiStok::PENYESUAIAN];

    /**
     * Mencatat satu mutasi stok dan memperbarui stok barang.
     *
     * @throws StokTidakCukupException bila mutasi keluar melebihi stok tersedia
     * @throws InvalidArgumentException bila jenis/sumber/jumlah tidak masuk akal
     */
    public function catat(
        Barang $barang,
        string $jenis,
        string $sumber,
        float $jumlah,
        CarbonInterface|string|null $tanggal = null,
        ?Model $referensi = null,
        ?string $keterangan = null,
        ?int $userId = null,
    ): MutasiStok {
        $this->pastikanJenisSah($jenis);
        $this->pastikanSumberSah($sumber);
        $this->pastikanJumlahSah($jenis, $jumlah);

        return DB::transaction(function () use ($barang, $jenis, $sumber, $jumlah, $tanggal, $referensi, $keterangan, $userId) {
            // Kunci baris barang selama transaksi supaya dua proses yang
            // mencatat mutasi bersamaan tidak menghitung stok awal yang sama.
            $terkunci = Barang::query()->lockForUpdate()->findOrFail($barang->getKey());

            $stokAwal = $this->stokAwal($terkunci);
            $perubahan = $jenis === MutasiStok::KELUAR ? -abs($jumlah) : $jumlah;
            $stokAkhir = $stokAwal + $perubahan;

            if ($stokAkhir < 0) {
                throw new StokTidakCukupException($terkunci, abs($perubahan), $stokAwal);
            }

            $mutasi = MutasiStok::create([
                'barang_id' => $terkunci->getKey(),
                'tanggal' => $tanggal ?? now(),
                'jenis_mutasi' => $jenis,
                'sumber' => $sumber,
                'referensi_tipe' => $referensi ? $referensi::class : null,
                'referensi_id' => $referensi?->getKey(),
                'jumlah' => $jenis === MutasiStok::KELUAR ? abs($jumlah) : $jumlah,
                'stok_awal' => $stokAwal,
                'stok_akhir' => $stokAkhir,
                'user_id' => $userId ?? auth()->id(),
                'keterangan' => $keterangan,
            ]);

            // Segarkan instance yang dipegang pemanggil agar tidak memakai
            // angka stok yang sudah basi.
            $barang->refresh();

            return $mutasi;
        });
    }

    /**
     * Menyesuaikan stok ke hasil hitung fisik gudang (stok opname).
     * Selisihnya dicatat sebagai satu mutasi PENYESUAIAN.
     *
     * Mengembalikan null bila stok fisik sama dengan catatan, karena mencatat
     * penyesuaian bernilai nol hanya akan mengotori riwayat.
     */
    public function sesuaikanKe(
        Barang $barang,
        float $stokFisik,
        CarbonInterface|string|null $tanggal = null,
        ?string $keterangan = null,
        ?int $userId = null,
    ): ?MutasiStok {
        if ($stokFisik < 0) {
            throw new InvalidArgumentException('Hasil hitung fisik tidak boleh negatif.');
        }

        $selisih = $stokFisik - $this->stokAwal($barang);

        if (abs($selisih) < 0.00005) {
            return null;
        }

        return $this->catat(
            barang: $barang,
            jenis: MutasiStok::PENYESUAIAN,
            sumber: 'opname',
            jumlah: $selisih,
            tanggal: $tanggal,
            keterangan: $keterangan,
            userId: $userId,
        );
    }

    /**
     * Stok berjalan sebuah barang.
     *
     * Diambil dari stok_akhir mutasi terakhir bila ada, dan baru jatuh ke
     * barang.stok_tersedia bila barang belum pernah punya mutasi sama sekali.
     * Urutannya memakai id, bukan tanggal, karena beberapa mutasi bisa terjadi
     * pada tanggal yang sama dan yang menentukan adalah urutan pencatatan.
     */
    public function stokAwal(Barang $barang): float
    {
        $terakhir = MutasiStok::query()
            ->where('barang_id', $barang->getKey())
            ->orderByDesc('id')
            ->value('stok_akhir');

        return $terakhir !== null ? (float) $terakhir : (float) $barang->stok_tersedia;
    }

    private function pastikanJenisSah(string $jenis): void
    {
        if (! in_array($jenis, self::JENIS, true)) {
            throw new InvalidArgumentException("Jenis mutasi '{$jenis}' tidak dikenal.");
        }
    }

    private function pastikanSumberSah(string $sumber): void
    {
        if (! in_array($sumber, self::SUMBER, true)) {
            throw new InvalidArgumentException("Sumber mutasi '{$sumber}' tidak dikenal.");
        }
    }

    private function pastikanJumlahSah(string $jenis, float $jumlah): void
    {
        if ($jenis === MutasiStok::PENYESUAIAN) {
            if (abs($jumlah) < 0.00005) {
                throw new InvalidArgumentException('Penyesuaian bernilai nol tidak perlu dicatat.');
            }

            return;
        }

        if ($jumlah <= 0) {
            throw new InvalidArgumentException('Jumlah mutasi masuk/keluar harus lebih besar dari nol.');
        }
    }
}
