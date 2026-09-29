<?php

namespace App\Exports;

use App\Models\Penjualan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Unduh data penjualan yang sudah ada di sistem, dalam susunan kolom yang
 * SAMA PERSIS dengan yang dibaca PenjualanImport -- jadi berkas hasil unduhan
 * ini bisa diimpor lagi apa adanya (mis. ke instalasi lain, atau sebagai
 * cadangan sebelum diedit massal).
 *
 * Filternya sengaja dibuat sama seperti yang dipakai PenjualanController::index()
 * supaya yang diunduh adalah persis apa yang sedang dilihat pengguna di layar,
 * bukan seluruh tabel tanpa pandang bulu.
 */
class PenjualanExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        private readonly string $cari,
        private readonly string $pelangganId,
        private readonly string $sumber,
        private readonly ?string $dari,
        private readonly ?string $sampai,
    ) {
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        $penjualan = Penjualan::query()
            ->with(['pelanggan:id,nama_pelanggan', 'detail.barang:id,kode_barang'])
            ->when($this->cari !== '', fn ($q) => $q->where('no_faktur', 'like', "%{$this->cari}%"))
            ->when(is_numeric($this->pelangganId), fn ($q) => $q->where('pelanggan_id', (int) $this->pelangganId))
            ->when(in_array($this->sumber, ['manual', 'import'], true), fn ($q) => $q->where('sumber_data', $this->sumber))
            ->when($this->dari, fn ($q) => $q->whereDate('tanggal_penjualan', '>=', $this->dari))
            ->when($this->sampai, fn ($q) => $q->whereDate('tanggal_penjualan', '<=', $this->sampai))
            ->orderBy('tanggal_penjualan')
            ->orderBy('id')
            ->get();

        return $penjualan->flatMap(fn (Penjualan $p) => $p->detail->map(fn ($baris) => [
            'tanggal' => $p->tanggal_penjualan,
            'no_faktur' => $p->no_faktur,
            'nama_pelanggan' => $p->pelanggan?->nama_pelanggan ?? $p->nama_pelanggan_manual ?? '',
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
        return ['tanggal', 'no_faktur', 'nama_pelanggan', 'kode_barang', 'jumlah', 'harga_satuan'];
    }

    /**
     * @param  array<string, mixed>  $baris
     * @return list<mixed>
     */
    public function map($baris): array
    {
        return [
            $baris['tanggal']?->format('Y-m-d'),
            $baris['no_faktur'],
            $baris['nama_pelanggan'],
            $baris['kode_barang'],
            (float) $baris['jumlah'],
            (float) $baris['harga_satuan'],
        ];
    }

    public function title(): string
    {
        return 'Penjualan';
    }
}
