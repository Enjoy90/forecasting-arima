<?php

namespace App\Http\Controllers\Laporan;

use App\Models\Barang;
use App\Models\Simulasi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Laporan Hasil Simulasi - rekap seluruh backtesting yang pernah dijalankan.
 *
 * Rentang tanggal di sini menyaring kapan simulasi DIJALANKAN (created_at),
 * bukan periode 12 bulan yang diujinya (periode_awal/periode_akhir), karena
 * satu simulasi bisa saja menguji periode masa lalu kapan pun dijalankan.
 */
class LaporanSimulasiController extends LaporanController
{
    protected function judul(): string
    {
        return 'Laporan Hasil Simulasi';
    }

    protected function penyaring(Request $request): array
    {
        return [
            'barang' => [
                'label' => 'Barang',
                'nilai' => $request->query('barang', 'semua'),
                'pilihan' => ['semua' => 'Semua barang'] + Barang::orderBy('kode_barang')
                    ->pluck('nama_barang', 'id')->all(),
            ],
        ];
    }

    protected function susun(Carbon $dari, Carbon $sampai, Request $request): array
    {
        $barangId = $request->query('barang', 'semua');

        $baris = Simulasi::query()
            ->with('barang:id,kode_barang,nama_barang')
            ->when(is_numeric($barangId), fn ($q) => $q->where('barang_id', (int) $barangId))
            ->whereBetween('created_at', [$dari, $sampai])
            ->orderBy('created_at')
            ->get();

        $isi = $baris->map(fn ($s) => [
            $s->created_at?->format('d/m/Y H:i') ?? '-',
            $s->kode_simulasi,
            $s->barang?->nama_barang ?? '-',
            $s->metode_pembanding,
            $s->periode_awal.' s.d. '.$s->periode_akhir,
            number_format((float) $s->pb_total_stockout_unit, 0, ',', '.'),
            number_format((float) $s->sis_total_stockout_unit, 0, ',', '.'),
            number_format((float) $s->pb_total_overstock_unit, 0, ',', '.'),
            number_format((float) $s->sis_total_overstock_unit, 0, ',', '.'),
            number_format((float) $s->pb_service_level_tercapai, 2, ',', '.').'%',
            number_format((float) $s->sis_service_level_tercapai, 2, ',', '.').'%',
            $s->is_sistem_lebih_baik ? 'Ya' : 'Tidak',
        ])->values()->all();

        $jumlah = $baris->count();

        return [
            'kolom' => [
                'Tanggal Dijalankan', 'Kode Simulasi', 'Barang', 'Metode Pembanding', 'Periode Diuji',
                'Stockout PB', 'Stockout Sistem', 'Overstock PB', 'Overstock Sistem',
                'Service Level PB', 'Service Level Sistem', 'Sistem Lebih Baik?',
            ],
            'baris' => $isi,
            'perataan' => [5 => 'right', 6 => 'right', 7 => 'right', 8 => 'right', 9 => 'right', 10 => 'right'],
            'ringkasan' => [
                'Jumlah simulasi dijalankan' => number_format($jumlah, 0, ',', '.'),
                'Simulasi dengan sistem lebih baik' => number_format(
                    $baris->where('is_sistem_lebih_baik', true)->count(), 0, ',', '.'
                ),
                'Rata-rata penurunan stockout' => number_format(
                    $jumlah > 0 ? (float) $baris->avg('penurunan_stockout_persen') : 0, 2, ',', '.'
                ).'%',
                'Rata-rata penurunan overstock' => number_format(
                    $jumlah > 0 ? (float) $baris->avg('penurunan_overstock_persen') : 0, 2, ',', '.'
                ).'%',
                'Total penghematan biaya' => $this->formatRupiah((float) $baris->sum('penghematan_biaya')),
            ],
        ];
    }

    /**
     * Penghematan biaya bisa negatif (sistem justru lebih mahal daripada
     * kebijakan lama); tanda minus diletakkan sebelum "Rp", bukan di antara
     * "Rp" dan angka, supaya terbaca wajar (mis. "-Rp 1.667.370").
     */
    private function formatRupiah(float $nilai): string
    {
        $tanda = $nilai < 0 ? '-' : '';

        return $tanda.'Rp '.number_format(abs($nilai), 0, ',', '.');
    }
}
