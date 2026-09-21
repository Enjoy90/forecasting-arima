<?php

namespace App\Http\Controllers\Pembelian;

use App\Exports\TemplatePembelianExport;
use App\Http\Controllers\Controller;
use App\Imports\PembelianImport;
use App\Models\LogAktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Import order pembelian dari berkas Excel.
 *
 * Mempercepat entri banyak order sekaligus (mis. dari catatan/backlog lama)
 * dibanding mengetik satu per satu lewat form. Order hasil import berstatus
 * "dipesan", persis seperti order manual — stok TIDAK bergerak sampai staf
 * memproses penerimaannya lewat menu Pembelian seperti biasa.
 */
class ImportPembelianController extends Controller
{
    private const MODUL = 'Import Pembelian';

    public function form(): View
    {
        return view('pembelian.import');
    }

    /**
     * Berkas contoh dibuat di tempat agar kolomnya selalu sesuai pembacanya.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new TemplatePembelianExport(), 'template-import-pembelian.xlsx');
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

        $import = new PembelianImport();

        Excel::import($import, $request->file('berkas'));

        if (! $import->berhasil()) {
            return back()
                ->with('gagal', 'Import dibatalkan. Tidak ada data yang tersimpan.')
                ->with('galatImport', array_slice($import->galat, 0, 20))
                ->with('sisaGalat', max(0, count($import->galat) - 20));
        }

        LogAktivitas::catat(
            self::MODUL,
            "Mengimpor {$import->jumlahOrder} order pembelian ({$import->jumlahBaris} baris) dari Excel"
        );

        $pesan = "Import selesai: {$import->jumlahOrder} order baru berstatus dipesan dari {$import->jumlahBaris} baris data. ".
            'Stok belum bertambah sampai tiap order diproses penerimaannya.';

        if ($import->dilewati !== []) {
            $jumlahDilewati = count($import->dilewati);
            $contoh = implode(', ', array_slice($import->dilewati, 0, 5));
            $pesan .= " {$jumlahDilewati} order dilewati karena nomornya sudah ada ({$contoh}".
                ($jumlahDilewati > 5 ? ', dan lainnya' : '').').';
        }

        return redirect()
            ->route('pembelian.order.index', ['status' => 'dipesan'])
            ->with('sukses', $pesan);
    }
}
