<?php

namespace App\Http\Controllers\Laporan;

use App\Models\DetailPembelian;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Laporan Pembelian — pengadaan bahan per baris barang.
 *
 * Yang dilaporkan hanya order berstatus `diterima`, karena itulah pembelian
 * yang benar-benar terwujud jadi bahan di gudang. Order yang masih dipesan
 * belum menjadi biaya maupun stok, dan order batal tidak pernah terjadi.
 */
class LaporanPembelianController extends LaporanController
{
    protected function judul(): string
    {
        return 'Laporan Pembelian';
    }

    protected function penyaring(Request $request): array
    {
        return [
            'supplier' => [
                'label' => 'Supplier',
                'nilai' => $request->query('supplier', 'semua'),
                'pilihan' => ['semua' => 'Semua supplier'] + Supplier::orderBy('kode_supplier')
                    ->pluck('nama_supplier', 'id')->all(),
            ],
        ];
    }

    protected function susun(Carbon $dari, Carbon $sampai, Request $request): array
    {
        $supplierId = $request->query('supplier', 'semua');

        $baris = DetailPembelian::query()
            ->with(['barang:id,kode_barang,nama_barang,satuan', 'pembelian.supplier:id,nama_supplier'])
            ->whereHas('pembelian', function ($q) use ($dari, $sampai, $supplierId) {
                $q->where('status', 'diterima')
                    ->whereBetween('tanggal_terima', [$dari, $sampai])
                    ->when(is_numeric($supplierId), fn ($s) => $s->where('supplier_id', (int) $supplierId));
            })
            ->get()
            ->sortBy(fn ($d) => $d->pembelian?->tanggal_terima);

        $isi = $baris->map(fn ($d) => [
            $d->pembelian?->tanggal_terima?->format('d/m/Y') ?? '-',
            $d->pembelian?->no_pembelian ?? '-',
            $d->pembelian?->supplier?->nama_supplier ?? '-',
            $d->barang?->kode_barang ?? '-',
            $d->barang?->nama_barang ?? '-',
            number_format($d->jumlah, 0, ',', '.').' '.($d->barang?->satuan ?? ''),
            'Rp '.number_format((float) $d->harga_satuan, 0, ',', '.'),
            'Rp '.number_format((float) $d->subtotal, 0, ',', '.'),
        ])->values()->all();

        return [
            'kolom' => ['Tanggal Terima', 'No. Pembelian', 'Supplier', 'Kode', 'Barang', 'Jumlah', 'Harga Satuan', 'Subtotal'],
            'baris' => $isi,
            'perataan' => [5 => 'right', 6 => 'right', 7 => 'right'],
            'ringkasan' => [
                'Jumlah nota diterima' => number_format($baris->pluck('pembelian_id')->unique()->count(), 0, ',', '.'),
                'Jumlah baris barang' => number_format($baris->count(), 0, ',', '.'),
                'Total nilai pembelian' => 'Rp '.number_format((float) $baris->sum('subtotal'), 0, ',', '.'),
            ],
        ];
    }
}
