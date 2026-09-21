<?php

namespace App\Http\Controllers\Persediaan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Master\BarangController;
use App\Models\Barang;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman "Stok Saat Ini" — potret persediaan pada detik ini.
 *
 * Halaman ini hanya membaca. Angka stok di sini berasal dari
 * `barang.stok_tersedia`, yang dijaga MutasiStokObserver agar selalu sama
 * dengan mutasi terakhir. Tidak ada tombol ubah stok di sini; koreksi stok
 * dilakukan lewat Stok Opname supaya tetap meninggalkan jejak.
 */
class StokController extends Controller
{
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $jenis = $request->query('jenis', 'semua');
        $kategoriId = $request->query('kategori', 'semua');
        $hanyaMenipis = $request->boolean('menipis');

        $daftarJenis = BarangController::JENIS;

        $barang = Barang::query()
            ->with(['kategori', 'supplier'])
            ->aktif()
            ->when($cari !== '', function ($query) use ($cari) {
                $query->where(function ($sub) use ($cari) {
                    $sub->where('kode_barang', 'like', "%{$cari}%")
                        ->orWhere('nama_barang', 'like', "%{$cari}%");
                });
            })
            ->when(array_key_exists($jenis, $daftarJenis), fn ($query) => $query->where('jenis_barang', $jenis))
            ->when(is_numeric($kategoriId), fn ($query) => $query->where('kategori_id', (int) $kategoriId))
            ->when($hanyaMenipis, fn ($query) => $query->whereColumn('stok_tersedia', '<=', 'stok_minimum'))
            ->orderBy('jenis_barang')
            ->orderBy('kode_barang')
            ->paginate(20)
            ->withQueryString();

        // Ringkasan dihitung dari SELURUH barang aktif, bukan hanya halaman ini.
        $semuaAktif = Barang::aktif()->get(['jenis_barang', 'stok_tersedia', 'stok_minimum', 'harga_beli']);

        return view('persediaan.stok.index', [
            'barang' => $barang,
            'cari' => $cari,
            'jenis' => $jenis,
            'kategoriId' => $kategoriId,
            'hanyaMenipis' => $hanyaMenipis,
            'daftarJenis' => $daftarJenis,
            'daftarKategori' => Kategori::orderBy('kode_kategori')->get(),
            'jumlahBarangAktif' => $semuaAktif->count(),
            'jumlahMenipis' => $semuaAktif->filter(fn ($b) => $b->stok_tersedia <= $b->stok_minimum)->count(),
            'jumlahKosong' => $semuaAktif->where('stok_tersedia', '<=', 0)->count(),
            // Nilai persediaan memakai harga beli: itu biaya yang benar-benar
            // tertanam di gudang, bukan harga jual yang belum tentu terwujud.
            'nilaiPersediaan' => $semuaAktif->sum(fn ($b) => $b->stok_tersedia * (float) $b->harga_beli),
        ]);
    }
}
