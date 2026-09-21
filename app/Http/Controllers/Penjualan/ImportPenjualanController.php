<?php

namespace App\Http\Controllers\Penjualan;

use App\Exports\TemplatePenjualanExport;
use App\Http\Controllers\Controller;
use App\Imports\PenjualanImport;
use App\Models\LogAktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Import penjualan dari berkas Excel.
 *
 * Inilah jalan masuk data penjualan asli CV. Pande Sejahtera (36 bulan) yang
 * akan menggantikan data dummy seeder. Selama data masih dummy, hasil simulasi
 * Modul B belum layak dipakai di laporan skripsi.
 *
 * Import bersifat historis dan TIDAK mengubah stok — alasannya dijelaskan pada
 * PenjualanImport.
 */
class ImportPenjualanController extends Controller
{
    private const MODUL = 'Import Penjualan';

    public function form(): View
    {
        return view('penjualan.import');
    }

    /**
     * Berkas contoh dibuat di tempat agar kolomnya selalu sesuai pembacanya.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new TemplatePenjualanExport(), 'template-import-penjualan.xlsx');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            // `extensions` memeriksa akhiran nama berkas, sedangkan `mimes`
            // memeriksa isinya. Keduanya dipakai bersama karena berkas CSV
            // dikenali PHP sebagai text/plain, sehingga `mimes:csv` saja akan
            // menolak berkas CSV yang sebenarnya sah.
            'berkas' => ['required', 'file', 'extensions:xlsx,xls,csv', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ], [
            'berkas.required' => 'Pilih berkas Excel yang akan diimpor.',
            'berkas.extensions' => 'Berkas harus berformat .xlsx, .xls, atau .csv.',
            'berkas.mimes' => 'Isi berkas tidak dikenali sebagai Excel atau CSV.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $import = new PenjualanImport();

        Excel::import($import, $request->file('berkas'));

        if (! $import->berhasil()) {
            return back()
                ->with('gagal', 'Import dibatalkan. Tidak ada data yang tersimpan.')
                ->with('galatImport', array_slice($import->galat, 0, 20))
                ->with('sisaGalat', max(0, count($import->galat) - 20));
        }

        LogAktivitas::catat(
            self::MODUL,
            "Mengimpor {$import->jumlahFaktur} faktur ({$import->jumlahBaris} baris) dari Excel"
        );

        $pesan = "Import selesai: {$import->jumlahFaktur} faktur baru dari {$import->jumlahBaris} baris data.";

        if ($import->dilewati !== []) {
            $jumlahDilewati = count($import->dilewati);
            $contoh = implode(', ', array_slice($import->dilewati, 0, 5));
            $pesan .= " {$jumlahDilewati} faktur dilewati karena nomornya sudah ada ({$contoh}".
                ($jumlahDilewati > 5 ? ', dan lainnya' : '').').';
        }

        return redirect()
            ->route('penjualan.faktur.index', ['sumber' => 'import'])
            ->with('sukses', $pesan);
    }
}
