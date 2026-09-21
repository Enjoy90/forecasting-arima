<?php

namespace App\Http\Controllers\Peramalan;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\LogAktivitas;
use App\Models\Peramalan;
use App\Models\TargetProduksi;
use App\Services\Stok\TargetProduksiPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Target Produksi: terjemahkan hasil peramalan menjadi rencana produksi
 * bulanan + rekomendasi kebutuhan bahan baku (docs/01 §6).
 */
class TargetProduksiController extends Controller
{
    private const MODUL = 'Target Produksi';

    public function index(Request $request): View
    {
        $daftarBarang = Barang::diramalkan()->aktif()->orderBy('kode_barang')->get();

        $barangId = $request->query('barang');
        $periodeForecast = collect();
        if ($barangId) {
            $peramalanTerbaru = Peramalan::where('barang_id', $barangId)->latest()->first();
            if ($peramalanTerbaru !== null) {
                $periodeForecast = $peramalanTerbaru->hasil()
                    ->where('tipe', 'forecast')
                    ->orderBy('urutan_t')
                    ->pluck('periode');
            }
        }

        $targetId = $request->query('target');
        $detail = $targetId
            ? TargetProduksi::with(['barang', 'kebutuhanBahan.barang'])->find($targetId)
            : null;

        return view('peramalan.target.index', [
            'daftarBarang' => $daftarBarang,
            'barangId' => $barangId,
            'periodeForecast' => $periodeForecast,
            'riwayat' => TargetProduksi::with('barang')->latest()->paginate(15),
            'detail' => $detail,
        ]);
    }

    public function hitung(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'barang_id' => ['required', 'exists:barang,id'],
            'periode' => ['required', 'string'],
        ]);

        $peramalan = Peramalan::where('barang_id', $data['barang_id'])->latest()->first();
        if ($peramalan === null) {
            return back()->with('gagal', 'Belum ada peramalan untuk barang ini. Jalankan Proses Forecasting dulu.');
        }

        try {
            $target = (new TargetProduksiPlanner())->rencanakan($peramalan, $data['periode'], auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        LogAktivitas::catat(
            self::MODUL,
            "Menghitung target produksi {$peramalan->barang->nama_barang} periode {$data['periode']}: {$target->jumlah_target_produksi} unit"
        );

        return redirect()
            ->route('peramalan.target.index', ['barang' => $data['barang_id'], 'target' => $target->id])
            ->with('sukses', "Target produksi dihitung: {$target->jumlah_target_produksi} unit ({$target->status_stok}).");
    }

    public function setujui(TargetProduksi $target): RedirectResponse
    {
        $target->update([
            'status_approval' => 'disetujui',
            'disetujui_pada' => now(),
            'user_id' => auth()->id(),
        ]);

        LogAktivitas::catat(self::MODUL, "Menyetujui target produksi {$target->barang->nama_barang} periode {$target->periode}");

        return redirect()
            ->route('peramalan.target.index', ['barang' => $target->barang_id, 'target' => $target->id])
            ->with('sukses', 'Target produksi disetujui.');
    }
}
