<?php

namespace App\Http\Controllers\Peramalan;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\TahapanProduksi;
use App\Services\Produksi\LeadTimeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

/**
 * Kalkulator waktu tunggu operasional pabrik (docs/01 §4): L_beli + L_produksi
 * dari rantai BOM barang jadi untuk jumlah target tertentu.
 */
class WaktuTungguController extends Controller
{
    public function index(): View
    {
        return view('peramalan.waktu-tunggu.index', [
            'daftarBarang' => Barang::diramalkan()->aktif()->orderBy('kode_barang')->get(),
        ]);
    }

    public function hitung(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'barang_id' => ['required', 'exists:barang,id'],
            'jumlah_target' => ['required', 'numeric', 'min:0.01'],
            'awal_periode' => ['required', 'date'],
        ]);

        $barang = Barang::findOrFail($data['barang_id']);

        try {
            $hasil = (new LeadTimeCalculator())->hitung(
                $barang,
                (float) $data['jumlah_target'],
                Carbon::parse($data['awal_periode'])
            );
        } catch (RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('hasil', [
            'barang' => $barang->nama_barang,
            'jumlah_target' => (float) $data['jumlah_target'],
            'lead_time_pembelian_hari' => $hasil['lead_time_pembelian_hari'],
            'lead_time_produksi_hari' => $hasil['lead_time_produksi_hari'],
            'lead_time_total_hari' => $hasil['lead_time_total_hari'],
            'tanggal_mulai_produksi' => $hasil['tanggal_mulai_produksi']->format('Y-m-d'),
            'tanggal_pesan_bahan' => $hasil['tanggal_pesan_bahan']->format('Y-m-d'),
        ]);
    }
}
