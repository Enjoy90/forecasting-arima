<?php

namespace App\Http\Controllers\Laporan;

use App\Models\Produksi;
use App\Models\TahapanProduksi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Laporan Produksi — hasil tiap perintah kerja beserta tingkat kegagalannya.
 *
 * Hanya perintah berstatus `selesai` yang dilaporkan, karena hanya itu yang
 * benar-benar menghasilkan barang. Perintah draft dan proses belum punya hasil,
 * dan yang batal tidak pernah terjadi.
 *
 * Kolom persen gagal ditampilkan karena angka inilah yang menunjukkan apakah
 * asumsi `persen_susut` pada BOM masih masuk akal dibanding kenyataan di lantai
 * produksi.
 */
class LaporanProduksiController extends LaporanController
{
    protected function judul(): string
    {
        return 'Laporan Produksi';
    }

    protected function penyaring(Request $request): array
    {
        return [
            'tahapan' => [
                'label' => 'Tahapan',
                'nilai' => $request->query('tahapan', 'semua'),
                'pilihan' => ['semua' => 'Semua tahapan'] + TahapanProduksi::orderBy('urutan')
                    ->pluck('nama_tahapan', 'id')->all(),
            ],
        ];
    }

    protected function susun(Carbon $dari, Carbon $sampai, Request $request): array
    {
        $tahapanId = $request->query('tahapan', 'semua');

        $perintah = Produksi::query()
            ->with(['tahapan:id,nama_tahapan,urutan', 'barangOutput:id,kode_barang,nama_barang,satuan', 'bom:id,kode_bom'])
            ->where('status', Produksi::STATUS_SELESAI)
            ->whereBetween('tanggal_produksi', [$dari, $sampai])
            ->when(is_numeric($tahapanId), fn ($q) => $q->where('tahapan_id', (int) $tahapanId))
            ->orderBy('tanggal_produksi')
            ->orderBy('id')
            ->get();

        $isi = $perintah->map(fn (Produksi $p) => [
            $p->tanggal_produksi?->format('d/m/Y') ?? '-',
            $p->no_produksi,
            $p->tahapan?->nama_tahapan ?? '-',
            $p->barangOutput?->nama_barang ?? '-',
            rtrim(rtrim(number_format((float) $p->jumlah_target, 2, ',', '.'), '0'), ','),
            rtrim(rtrim(number_format((float) $p->jumlah_hasil, 2, ',', '.'), '0'), ','),
            rtrim(rtrim(number_format((float) $p->jumlah_gagal, 2, ',', '.'), '0'), ','),
            number_format($p->persen_gagal, 2, ',', '.').'%',
        ])->values()->all();

        $totalHasil = (float) $perintah->sum('jumlah_hasil');
        $totalGagal = (float) $perintah->sum('jumlah_gagal');
        $totalDikerjakan = $totalHasil + $totalGagal;

        return [
            'kolom' => ['Tanggal', 'No. Perintah', 'Tahapan', 'Barang Hasil', 'Target', 'Hasil', 'Gagal', '% Gagal'],
            'baris' => $isi,
            'perataan' => [4 => 'right', 5 => 'right', 6 => 'right', 7 => 'right'],
            'ringkasan' => [
                'Jumlah perintah selesai' => number_format($perintah->count(), 0, ',', '.'),
                'Total unit jadi' => number_format($totalHasil, 0, ',', '.').' unit',
                'Total unit gagal' => number_format($totalGagal, 0, ',', '.').' unit',
                'Rata-rata tingkat gagal' => $totalDikerjakan > 0
                    ? number_format($totalGagal / $totalDikerjakan * 100, 2, ',', '.').'%'
                    : '0%',
            ],
        ];
    }
}
