<?php

namespace App\Http\Controllers\Laporan;

use App\Exports\LaporanExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Induk keempat laporan operasional.
 *
 * Keempat laporan (pembelian, produksi, persediaan, penjualan) punya bentuk
 * yang sama: satu rentang tanggal, satu tabel, beberapa angka ringkasan, dan
 * tiga cara menampilkannya — layar, PDF, dan Excel. Yang berbeda hanya
 * datanya. Karena itu seluruh kerangkanya ditaruh di sini, dan tiap laporan
 * hanya perlu menjawab dua hal lewat method yang wajib diisi:
 *
 *   judul()  : nama laporan
 *   susun()  : data tabel + ringkasan untuk rentang tanggal tertentu
 *
 * Tanpa pembagian ini, keempat laporan berarti empat salinan kode cetak PDF,
 * export Excel, dan penyaring tanggal yang sama persis.
 */
abstract class LaporanController extends Controller
{
    /** Nama laporan, dipakai pada judul halaman dan nama berkas unduhan. */
    abstract protected function judul(): string;

    /**
     * Menyusun isi laporan untuk satu rentang tanggal.
     *
     * @return array{
     *     kolom: list<string>,
     *     baris: list<list<string|int|float>>,
     *     ringkasan: array<string, string>,
     *     perataan?: array<int, string>
     * }
     */
    abstract protected function susun(Carbon $dari, Carbon $sampai, Request $request): array;

    /**
     * Penyaring tambahan khas tiap laporan, bila ada.
     *
     * @return array<string, mixed>
     */
    protected function penyaring(Request $request): array
    {
        return [];
    }

    public function index(Request $request): View|BinaryFileResponse|Response
    {
        [$dari, $sampai] = $this->rentang($request);

        $laporan = $this->susun($dari, $sampai, $request);

        $data = [
            'judul' => $this->judul(),
            'dari' => $dari,
            'sampai' => $sampai,
            'laporan' => $laporan,
            'penyaring' => $this->penyaring($request),
            'rute' => $request->route()->getName(),
        ];

        return match ($request->query('unduh')) {
            'pdf' => $this->pdf($data),
            'excel' => $this->excel($data),
            default => view('laporan.tampil', $data),
        };
    }

    /**
     * Rentang bawaan: awal bulan ini sampai hari ini. Dipilih karena itulah
     * rentang yang paling sering dilihat saat laporan dibuka tanpa penyaring.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function rentang(Request $request): array
    {
        $dari = $request->query('dari')
            ? Carbon::parse($request->query('dari'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $sampai = $request->query('sampai')
            ? Carbon::parse($request->query('sampai'))->endOfDay()
            : Carbon::now()->endOfDay();

        // Rentang terbalik dibetulkan diam-diam daripada menolak permintaan;
        // maksud penggunanya tetap jelas.
        if ($dari->greaterThan($sampai)) {
            [$dari, $sampai] = [$sampai->copy()->startOfDay(), $dari->copy()->endOfDay()];
        }

        return [$dari, $sampai];
    }

    private function pdf(array $data): Response
    {
        $pdf = Pdf::loadView('laporan.pdf.umum', $data)
            // Tabel laporan cenderung lebar, jadi kertas dipasang mendatar.
            ->setPaper('a4', 'landscape');

        return $pdf->download($this->namaBerkas($data, 'pdf'));
    }

    private function excel(array $data): BinaryFileResponse
    {
        return Excel::download(
            new LaporanExport($data['judul'], $data['dari'], $data['sampai'], $data['laporan']),
            $this->namaBerkas($data, 'xlsx')
        );
    }

    private function namaBerkas(array $data, string $ekstensi): string
    {
        $nama = str($data['judul'])->slug()->toString();

        return sprintf(
            '%s-%s-sd-%s.%s',
            $nama,
            $data['dari']->format('Ymd'),
            $data['sampai']->format('Ymd'),
            $ekstensi
        );
    }
}
