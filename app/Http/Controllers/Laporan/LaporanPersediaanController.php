<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Master\BarangController;
use App\Models\Barang;
use App\Models\MutasiStok;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Persediaan — kartu stok ringkas per barang.
 *
 * Berbeda dari tiga laporan lain yang mendaftar transaksi, laporan ini
 * merangkum PERGERAKAN tiap barang sepanjang rentang: berapa masuk, berapa
 * keluar, berapa disesuaikan, dan berapa stoknya sekarang.
 *
 * Stok awal dihitung mundur dari stok berjalan dikurangi seluruh pergerakan
 * dalam rentang. Cara ini dipilih karena sistem tidak menyimpan potret stok
 * harian; yang tersimpan adalah rantai mutasi, dan stok awal adalah
 * kesimpulan yang paling jujur yang bisa ditarik darinya.
 */
class LaporanPersediaanController extends LaporanController
{
    protected function judul(): string
    {
        return 'Laporan Persediaan';
    }

    protected function penyaring(Request $request): array
    {
        return [
            'jenis' => [
                'label' => 'Jenis barang',
                'nilai' => $request->query('jenis', 'semua'),
                'pilihan' => ['semua' => 'Semua jenis'] + BarangController::JENIS,
            ],
        ];
    }

    protected function susun(Carbon $dari, Carbon $sampai, Request $request): array
    {
        $jenis = $request->query('jenis', 'semua');

        $barang = Barang::query()
            ->aktif()
            ->when(array_key_exists($jenis, BarangController::JENIS), fn ($q) => $q->where('jenis_barang', $jenis))
            ->orderBy('jenis_barang')
            ->orderBy('kode_barang')
            ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'jenis_barang', 'stok_tersedia', 'stok_minimum', 'harga_beli']);

        // Pergerakan dalam rentang, dikumpulkan sekali untuk seluruh barang
        // agar tidak menembak satu query per baris laporan.
        $gerak = MutasiStok::query()
            ->whereBetween('tanggal', [$dari, $sampai])
            ->whereIn('barang_id', $barang->pluck('id'))
            ->select('barang_id')
            ->selectRaw("SUM(CASE WHEN jenis_mutasi = 'masuk' THEN jumlah ELSE 0 END) AS masuk")
            ->selectRaw("SUM(CASE WHEN jenis_mutasi = 'keluar' THEN jumlah ELSE 0 END) AS keluar")
            ->selectRaw("SUM(CASE WHEN jenis_mutasi = 'penyesuaian' THEN jumlah ELSE 0 END) AS sesuai")
            ->groupBy('barang_id')
            ->get()
            ->keyBy('barang_id');

        // Pergerakan SETELAH rentang, untuk memundurkan stok berjalan ke stok
        // akhir pada tanggal batas laporan.
        $sesudah = MutasiStok::query()
            ->where('tanggal', '>', $sampai)
            ->whereIn('barang_id', $barang->pluck('id'))
            ->select('barang_id')
            ->selectRaw("SUM(CASE WHEN jenis_mutasi = 'keluar' THEN -jumlah ELSE jumlah END) AS bersih")
            ->groupBy('barang_id')
            ->pluck('bersih', 'barang_id');

        $isi = [];
        $nilaiAkhir = 0.0;
        $jumlahMenipis = 0;

        foreach ($barang as $b) {
            $m = $gerak->get($b->id);
            $masuk = (float) ($m->masuk ?? 0);
            $keluar = (float) ($m->keluar ?? 0);
            $penyesuaian = (float) ($m->sesuai ?? 0);

            $stokAkhir = (float) $b->stok_tersedia - (float) ($sesudah[$b->id] ?? 0);
            $stokAwal = $stokAkhir - $masuk + $keluar - $penyesuaian;

            $nilaiAkhir += $stokAkhir * (float) $b->harga_beli;

            if ($stokAkhir <= (float) $b->stok_minimum) {
                $jumlahMenipis++;
            }

            $angka = fn (float $n) => rtrim(rtrim(number_format($n, 4, ',', '.'), '0'), ',');

            $isi[] = [
                $b->kode_barang,
                $b->nama_barang,
                BarangController::JENIS[$b->jenis_barang] ?? $b->jenis_barang,
                $angka($stokAwal),
                $angka($masuk),
                $angka($keluar),
                $angka($penyesuaian),
                $angka($stokAkhir).' '.$b->satuan,
                'Rp '.number_format($stokAkhir * (float) $b->harga_beli, 0, ',', '.'),
            ];
        }

        return [
            'kolom' => ['Kode', 'Barang', 'Jenis', 'Stok Awal', 'Masuk', 'Keluar', 'Penyesuaian', 'Stok Akhir', 'Nilai'],
            'baris' => $isi,
            'perataan' => [3 => 'right', 4 => 'right', 5 => 'right', 6 => 'right', 7 => 'right', 8 => 'right'],
            'ringkasan' => [
                'Jumlah barang' => number_format($barang->count(), 0, ',', '.'),
                'Barang di bawah stok minimum' => number_format($jumlahMenipis, 0, ',', '.'),
                'Nilai persediaan akhir' => 'Rp '.number_format($nilaiAkhir, 0, ',', '.'),
            ],
        ];
    }
}
