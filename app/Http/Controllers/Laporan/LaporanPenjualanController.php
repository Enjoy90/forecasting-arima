<?php

namespace App\Http\Controllers\Laporan;

use App\Models\Barang;
use App\Models\DetailPenjualan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Laporan Penjualan — penjualan per baris barang.
 *
 * Laporan ini juga menjadi bahan pemeriksaan silang bagi Modul B: angka total
 * unit di sini harus cocok dengan deret waktu yang diramalkan ARIMA untuk
 * rentang yang sama.
 */
class LaporanPenjualanController extends LaporanController
{
    protected function judul(): string
    {
        return 'Laporan Penjualan';
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

        $baris = DetailPenjualan::query()
            ->with(['barang:id,kode_barang,nama_barang,satuan', 'penjualan.pelanggan:id,nama_pelanggan'])
            ->when(is_numeric($barangId), fn ($q) => $q->where('barang_id', (int) $barangId))
            ->whereHas('penjualan', fn ($q) => $q->whereBetween('tanggal_penjualan', [$dari, $sampai]))
            ->get()
            ->sortBy(fn ($d) => $d->penjualan?->tanggal_penjualan);

        $isi = $baris->map(fn ($d) => [
            $d->penjualan?->tanggal_penjualan?->format('d/m/Y') ?? '-',
            $d->penjualan?->no_faktur ?? '-',
            $d->penjualan?->nama_pembeli ?? '-',
            $d->barang?->kode_barang ?? '-',
            $d->barang?->nama_barang ?? '-',
            number_format($d->jumlah, 0, ',', '.').' '.($d->barang?->satuan ?? ''),
            'Rp '.number_format((float) $d->harga_satuan, 0, ',', '.'),
            'Rp '.number_format((float) $d->subtotal, 0, ',', '.'),
        ])->values()->all();

        return [
            'kolom' => ['Tanggal', 'No. Faktur', 'Pembeli', 'Kode', 'Barang', 'Jumlah', 'Harga Satuan', 'Subtotal'],
            'baris' => $isi,
            'perataan' => [5 => 'right', 6 => 'right', 7 => 'right'],
            'ringkasan' => [
                'Jumlah faktur' => number_format($baris->pluck('penjualan_id')->unique()->count(), 0, ',', '.'),
                'Total unit terjual' => number_format((float) $baris->sum('jumlah'), 0, ',', '.').' unit',
                'Total nilai penjualan' => 'Rp '.number_format((float) $baris->sum('subtotal'), 0, ',', '.'),
            ],
        ];
    }
}
