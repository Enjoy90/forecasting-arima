<?php

namespace App\Http\Controllers\Peramalan;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Services\Produksi\LeadTimeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

/**
 * Kalkulator waktu tunggu operasional pabrik (docs/01 §4, direvisi docs/07):
 * L_beli + L_produksi untuk pesanan beberapa produk jadi yang dikerjakan
 * bersamaan, karena kapasitas tahapan dipakai bersama.
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
            'jumlah' => ['required', 'array'],
            'jumlah.*' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'awal_periode' => ['required', 'date'],
            'periksa_stok' => ['required', 'boolean'],
        ], [
            'jumlah.*.integer' => 'Jumlah pesanan harus bilangan bulat.',
            'jumlah.*.min' => 'Jumlah pesanan paling sedikit 1.',
            'jumlah.*.max' => 'Jumlah pesanan paling banyak 100.000.',
        ]);

        $pesanan = collect($data['jumlah'])
            ->map(fn ($nilai) => (float) $nilai)
            ->filter(fn ($nilai) => $nilai > 0);

        if ($pesanan->isEmpty()) {
            return back()->withInput()->with('gagal', 'Isi jumlah minimal untuk satu produk.');
        }

        $barang = Barang::diramalkan()->whereIn('id', $pesanan->keys())->get()->keyBy('id');
        if ($barang->count() !== $pesanan->count()) {
            return back()->withInput()->with('gagal', 'Ada produk yang tidak dikenal.');
        }

        try {
            $hasil = (new LeadTimeCalculator())->hitungGabungan(
                $pesanan->all(),
                Carbon::parse($data['awal_periode']),
                (bool) $data['periksa_stok']
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return back()->withInput()->with('hasil', [
            'pesanan' => $pesanan->map(fn ($jumlah, $id) => [
                'kode_barang' => $barang[$id]->kode_barang,
                'nama_barang' => $barang[$id]->nama_barang,
                'jumlah' => $jumlah,
                'satuan' => $barang[$id]->satuan,
            ])->values()->all(),
            'periksa_stok' => (bool) $data['periksa_stok'],
            'lead_time_pembelian_hari' => $hasil['lead_time_pembelian_hari'],
            'lead_time_produksi_hari' => $hasil['lead_time_produksi_hari'],
            'lead_time_total_hari' => $hasil['lead_time_total_hari'],
            'jalur_kritis_hari' => $hasil['jalur_kritis_hari'],
            'hari_tahap_terlama' => $hasil['hari_tahap_terlama'],
            'tumpang_tindih_hari' => $hasil['tumpang_tindih_hari'],
            'rincian_tahapan' => $hasil['rincian_tahapan'],
            'bahan_kurang' => $hasil['bahan_kurang'],
            'tanggal_mulai_produksi' => $hasil['tanggal_mulai_produksi']->format('Y-m-d'),
            'tanggal_pesan_bahan' => $hasil['tanggal_pesan_bahan']->format('Y-m-d'),
        ]);
    }
}
