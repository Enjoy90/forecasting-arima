<?php

namespace App\Http\Controllers\Simulasi;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\LogAktivitas;
use App\Models\Simulasi;
use App\Services\Simulasi\SimulationRunner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simulasi & Pengujian (docs/01 §7): jalankan backtesting rencana stok dan
 * tampilkan rincian bulanan tiap skenario. Ini tolak ukur utama keberhasilan
 * sistem menurut revisi sidang.
 */
class SimulasiController extends Controller
{
    private const MODUL = 'Simulasi';

    public function index(): View
    {
        return view('simulasi.index', [
            'riwayat' => Simulasi::with('barang')->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('simulasi.create', [
            'daftarBarang' => Barang::diramalkan()->aktif()->orderBy('kode_barang')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'barang_id' => ['required', 'exists:barang,id'],
            'metode_pembanding' => ['required', 'in:produksi_aktual,naif_bulan_lalu,rata_rata_bergerak'],
            'stok_awal_simulasi' => ['required', 'numeric', 'min:0'],
            'biaya_simpan_per_unit' => ['required', 'numeric', 'min:0'],
            'biaya_stockout_per_unit' => ['required', 'numeric', 'min:0'],
        ]);

        $barang = Barang::findOrFail($data['barang_id']);

        try {
            $simulasi = (new SimulationRunner())->jalankan(
                barang: $barang,
                metodePembanding: $data['metode_pembanding'],
                stokAwalSimulasi: (float) $data['stok_awal_simulasi'],
                biayaSimpanPerUnit: (float) $data['biaya_simpan_per_unit'],
                biayaStockoutPerUnit: (float) $data['biaya_stockout_per_unit'],
                userId: auth()->id(),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        LogAktivitas::catat(self::MODUL, "Menjalankan simulasi {$barang->nama_barang}: {$simulasi->kode_simulasi}");

        return redirect()
            ->route('simulasi.show', $simulasi)
            ->with('sukses', 'Simulasi selesai dijalankan.');
    }

    public function show(Simulasi $simulasi): View
    {
        $simulasi->load('barang');

        return view('simulasi.show', [
            'simulasi' => $simulasi,
            'detailPerusahaan' => $simulasi->detailPerusahaan(),
            'detailSistem' => $simulasi->detailSistem(),
        ]);
    }

    /**
     * Cetak PDF satu simulasi lengkap: kesimpulan, ringkasan berdampingan,
     * dan rincian bulanan kedua skenario. Bukti pengujian untuk sidang.
     */
    public function cetak(Simulasi $simulasi): Response
    {
        $simulasi->load('barang');

        $namaBerkas = str("simulasi-{$simulasi->kode_simulasi}")->slug().'.pdf';

        return Pdf::loadView('simulasi.pdf.cetak', [
            'simulasi' => $simulasi,
            'detailPerusahaan' => $simulasi->detailPerusahaan(),
            'detailSistem' => $simulasi->detailSistem(),
        ])
            ->setPaper('a4', 'landscape')
            ->download($namaBerkas);
    }
}
