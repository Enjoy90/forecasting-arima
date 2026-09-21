<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export Excel untuk seluruh laporan operasional.
 *
 * Satu kelas melayani keempat laporan karena bentuknya seragam: judul,
 * rentang tanggal, ringkasan, lalu tabel. Judul dan ringkasan ikut ditulis ke
 * berkas supaya lembar yang sudah diunduh tetap bisa dipahami tanpa harus
 * membuka aplikasinya lagi.
 */
class LaporanExport implements FromArray, WithTitle, WithEvents
{
    private int $barisJudulTabel = 0;

    /**
     * @param  array{kolom: list<string>, baris: list<list<mixed>>, ringkasan: array<string, string>}  $laporan
     */
    public function __construct(
        private readonly string $judul,
        private readonly Carbon $dari,
        private readonly Carbon $sampai,
        private readonly array $laporan,
    ) {
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $isi = [
            ['CV. PANDE SEJAHTERA'],
            [strtoupper($this->judul)],
            ['Periode: '.$this->dari->translatedFormat('d F Y').' s/d '.$this->sampai->translatedFormat('d F Y')],
            ['Dicetak: '.now()->translatedFormat('d F Y H:i')],
            [],
        ];

        foreach ($this->laporan['ringkasan'] as $label => $nilai) {
            $isi[] = [$label, $nilai];
        }

        if ($this->laporan['ringkasan'] !== []) {
            $isi[] = [];
        }

        $this->barisJudulTabel = count($isi) + 1;

        $isi[] = $this->laporan['kolom'];

        foreach ($this->laporan['baris'] as $baris) {
            $isi[] = $baris;
        }

        if ($this->laporan['baris'] === []) {
            $isi[] = ['Tidak ada data pada rentang tanggal ini.'];
        }

        return $isi;
    }

    public function title(): string
    {
        return str($this->judul)->limit(28, '')->toString();
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $jumlahKolom = max(1, count($this->laporan['kolom']));
                $kolomAkhir = $sheet->getCellByColumnAndRow($jumlahKolom, 1)->getColumn();

                $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A{$this->barisJudulTabel}:{$kolomAkhir}{$this->barisJudulTabel}")
                    ->getFont()->setBold(true);
                $sheet->getStyle("A{$this->barisJudulTabel}:{$kolomAkhir}{$this->barisJudulTabel}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $barisAkhir = $this->barisJudulTabel + max(1, count($this->laporan['baris']));
                $sheet->getStyle("A{$this->barisJudulTabel}:{$kolomAkhir}{$barisAkhir}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                for ($i = 1; $i <= $jumlahKolom; $i++) {
                    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
                }
            },
        ];
    }
}
