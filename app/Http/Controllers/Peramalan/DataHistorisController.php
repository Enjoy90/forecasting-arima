<?php

namespace App\Http\Controllers\Peramalan;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\LogAktivitas;
use App\Services\Arima\TimeSeriesBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Data Historis: agregasi penjualan bulanan barang jadi menjadi deret Zt
 * (docs/01 §5), sumber data pertama pipeline Box-Jenkins.
 */
class DataHistorisController extends Controller
{
    private const MODUL = 'Data Historis';

    public function index(Request $request): View
    {
        $daftarBarang = Barang::diramalkan()->aktif()->orderBy('kode_barang')->get();

        $barangId = $request->query('barang');
        $barang = $barangId ? $daftarBarang->firstWhere('id', (int) $barangId) : $daftarBarang->first();

        $deret = $barang
            ? $barang->dataTimeSeries()->orderBy('urutan_t')->get()
            : collect();

        return view('peramalan.historis.index', [
            'daftarBarang' => $daftarBarang,
            'barang' => $barang,
            'deret' => $deret,
        ]);
    }

    public function agregasi(Request $request): RedirectResponse
    {
        $request->validate([
            'barang_id' => ['required', 'exists:barang,id'],
        ]);

        $barang = Barang::findOrFail($request->integer('barang_id'));
        $deret = (new TimeSeriesBuilder())->bangun($barang);

        LogAktivitas::catat(self::MODUL, "Membangun ulang deret waktu {$barang->nama_barang} ({$deret->count()} periode)");

        // Fragment #hasil-deret supaya browser langsung scroll ke tabel/chart
        // yang baru diperbarui, bukan balik ke paling atas halaman.
        return redirect()
            ->to(route('peramalan.historis.index', ['barang' => $barang->id]).'#hasil-deret')
            ->with('sukses', "Data historis {$barang->nama_barang} berhasil diperbarui: {$deret->count()} periode.");
    }
}
