<?php

namespace App\Exports;

use App\Models\Peramalan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Unduh tabel hasil forecast (Tahap 4) satu peramalan: periode, prediksi,
 * dan interval kepercayaan -- persis isi tabel yang tampil di halaman hasil.
 */
class HasilPeramalanExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly Peramalan $peramalan)
    {
    }

    public function collection(): Collection
    {
        return $this->peramalan->hasil()
            ->where('tipe', 'forecast')
            ->orderBy('urutan_t')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['periode', 'prediksi', 'batas_bawah_95', 'batas_atas_95'];
    }

    /**
     * @param  \App\Models\HasilPeramalan  $baris
     * @return list<mixed>
     */
    public function map($baris): array
    {
        return [
            $baris->periode,
            round((float) $baris->nilai_prediksi, 2),
            round((float) $baris->batas_bawah, 2),
            round((float) $baris->batas_atas, 2),
        ];
    }

    public function title(): string
    {
        return 'Hasil Peramalan';
    }
}
