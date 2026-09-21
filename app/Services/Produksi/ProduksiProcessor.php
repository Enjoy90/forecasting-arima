<?php

namespace App\Services\Produksi;

use App\Exceptions\BahanTidakCukupException;
use App\Models\Barang;
use App\Models\Bom;
use App\Models\MutasiStok;
use App\Models\Produksi;
use App\Services\Stok\StockMutator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Eksekusi perintah produksi: penyalinan rencana bahan dan pencatatan mutasi
 * stok saat perintah dinyatakan selesai.
 *
 * Alur satu perintah produksi (docs/01-alur-kerja-sistem.md bagian 3.1):
 *
 *   1. Staf memilih tahapan + BOM + jumlah target        -> status draft
 *   2. Sistem menyalin komponen BOM ke detail_produksi_bahan sebagai
 *      jumlah_rencana, sudah memperhitungkan persen susut
 *   3. Perintah dimulai                                   -> status proses
 *   4. Staf mengisi realisasi: jumlah_pakai, jumlah_hasil, jumlah_gagal
 *   5. Perintah diselesaikan                              -> status selesai
 *        - tiap bahan dicatat mutasi KELUAR sebanyak jumlah_pakai
 *        - barang output dicatat mutasi MASUK sebanyak jumlah_hasil
 *
 * Rencana bahan sengaja DISALIN, bukan dibaca ulang dari BOM saat perintah
 * diselesaikan. Dengan begitu revisi resep di kemudian hari tidak mengubah
 * perintah produksi yang sudah telanjur berjalan.
 */
class ProduksiProcessor
{
    public function __construct(private readonly StockMutator $stockMutator)
    {
    }

    /**
     * Menyalin komponen BOM menjadi rencana pemakaian bahan.
     *
     * `jumlah_pakai` diisi sama dengan rencana sebagai nilai awal, karena pada
     * kebanyakan perintah realisasinya memang sama; staf tinggal membetulkan
     * yang berbeda.
     */
    public function salinRencanaBahan(Produksi $produksi, Bom $bom, float $target): void
    {
        $bom->loadMissing('detail.barang');

        $produksi->bahan()->delete();

        foreach ($bom->kebutuhanUntuk($target) as $kebutuhan) {
            $barang = Barang::find($kebutuhan['barang_id']);

            $produksi->bahan()->create([
                'barang_id' => $kebutuhan['barang_id'],
                'jumlah_rencana' => $kebutuhan['jumlah'],
                'jumlah_pakai' => $kebutuhan['jumlah'],
                'satuan' => $kebutuhan['satuan'] ?? $barang?->satuan,
            ]);
        }
    }

    /**
     * Memeriksa kecukupan stok seluruh bahan sekaligus.
     *
     * @return list<array{nama:string, satuan:string, dibutuhkan:float, tersedia:float, kurang:float}>
     */
    public function kekuranganBahan(Produksi $produksi): array
    {
        $produksi->loadMissing('bahan.barang');

        $kurang = [];

        foreach ($produksi->bahan as $baris) {
            if (! $baris->barang) {
                continue;
            }

            $dibutuhkan = (float) $baris->jumlah_pakai;

            if ($dibutuhkan <= 0) {
                continue;
            }

            $tersedia = $this->stockMutator->stokAwal($baris->barang);

            if ($dibutuhkan > $tersedia) {
                $kurang[] = [
                    'nama' => $baris->barang->nama_barang,
                    'satuan' => (string) ($baris->satuan ?? $baris->barang->satuan),
                    'dibutuhkan' => $dibutuhkan,
                    'tersedia' => $tersedia,
                    'kurang' => $dibutuhkan - $tersedia,
                ];
            }
        }

        return $kurang;
    }

    /**
     * Menyelesaikan perintah produksi dan mencatat seluruh mutasi stoknya.
     *
     * @throws BahanTidakCukupException bila ada bahan yang stoknya kurang
     * @throws RuntimeException bila status perintah tidak memungkinkan
     */
    public function selesaikan(Produksi $produksi): void
    {
        if ($produksi->status !== Produksi::STATUS_PROSES) {
            throw new RuntimeException(
                'Hanya perintah berstatus proses yang dapat diselesaikan.'
            );
        }

        if ((float) $produksi->jumlah_hasil <= 0) {
            throw new RuntimeException(
                'Jumlah hasil belum diisi. Catat dulu realisasi produksinya sebelum perintah diselesaikan.'
            );
        }

        // Diperiksa lebih dulu supaya staf melihat SELURUH kekurangan sekaligus,
        // bukan gagal satu per satu pada mutasi pertama yang kurang.
        $kekurangan = $this->kekuranganBahan($produksi);

        if ($kekurangan !== []) {
            throw new BahanTidakCukupException($kekurangan);
        }

        DB::transaction(function () use ($produksi) {
            $produksi->loadMissing(['bahan.barang', 'barangOutput', 'tahapan']);

            $tahapan = $produksi->tahapan?->nama_tahapan ?? 'produksi';

            // Bahan keluar dari gudang.
            foreach ($produksi->bahan as $baris) {
                $dipakai = (float) $baris->jumlah_pakai;

                if ($dipakai <= 0 || ! $baris->barang) {
                    continue;
                }

                $this->stockMutator->catat(
                    barang: $baris->barang,
                    jenis: MutasiStok::KELUAR,
                    sumber: 'produksi',
                    jumlah: $dipakai,
                    tanggal: $produksi->tanggal_produksi,
                    referensi: $produksi,
                    keterangan: "Pemakaian bahan {$produksi->no_produksi} ({$tahapan})",
                );
            }

            // Barang hasil masuk gudang. Hanya yang berhasil; produk gagal
            // tidak menambah stok karena memang tidak dapat dijual maupun
            // dipakai tahapan berikutnya.
            $this->stockMutator->catat(
                barang: $produksi->barangOutput,
                jenis: MutasiStok::MASUK,
                sumber: 'produksi',
                jumlah: (float) $produksi->jumlah_hasil,
                tanggal: $produksi->tanggal_produksi,
                referensi: $produksi,
                keterangan: "Hasil {$produksi->no_produksi} ({$tahapan})",
            );

            $produksi->update(['status' => Produksi::STATUS_SELESAI]);
        });
    }
}
