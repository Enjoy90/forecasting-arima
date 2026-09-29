<?php

namespace App\Exports;

use App\Http\Controllers\Pembelian\PembelianController;
use App\Models\Pembelian;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Unduh data order pembelian yang sudah ada di sistem, dalam susunan kolom
 * yang SAMA PERSIS dengan yang dibaca PembelianImport -- jadi berkas hasil
 * unduhan ini bisa diimpor lagi apa adanya.
 *
 * Filternya sengaja dibuat sama seperti PembelianController::index() supaya
 * yang diunduh adalah persis apa yang sedang dilihat pengguna di layar.
 */
class PembelianExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        private readonly string $cari,
        private readonly string $status,
        private readonly string $supplierId,
        private readonly ?string $dari,
        private readonly ?string $sampai,
    ) {
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        $pembelian = Pembelian::query()
            ->with(['supplier:id,kode_supplier', 'detail.barang:id,kode_barang'])
            ->when($this->cari !== '', fn ($q) => $q->where('no_pembelian', 'like', "%{$this->cari}%"))
            ->when(array_key_exists($this->status, PembelianController::STATUS), fn ($q) => $q->where('status', $this->status))
            ->when(is_numeric($this->supplierId), fn ($q) => $q->where('supplier_id', (int) $this->supplierId))
            ->when($this->dari, fn ($q) => $q->whereDate('tanggal_pembelian', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->whereDate('tanggal_pembelian', '<=', $this->sampai))
            ->orderBy('tanggal_pembelian')
            ->orderBy('id')
            ->get();

        return $pembelian->flatMap(fn (Pembelian $p) => $p->detail->map(fn ($baris) => [
            'tanggal' => $p->tanggal_pembelian,
            'no_pembelian' => $p->no_pembelian,
            'kode_supplier' => $p->supplier?->kode_supplier ?? '',
            'kode_barang' => $baris->barang?->kode_barang ?? '',
            'jumlah' => $baris->jumlah,
            'harga_satuan' => $baris->harga_satuan,
        ]));
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['tanggal', 'no_pembelian', 'kode_supplier', 'kode_barang', 'jumlah', 'harga_satuan'];
    }

    /**
     * @param  array<string, mixed>  $baris
     * @return list<mixed>
     */
    public function map($baris): array
    {
        return [
            $baris['tanggal']?->format('Y-m-d'),
            $baris['no_pembelian'],
            $baris['kode_supplier'],
            $baris['kode_barang'],
            (float) $baris['jumlah'],
            (float) $baris['harga_satuan'],
        ];
    }

    public function title(): string
    {
        return 'Pembelian';
    }
}
