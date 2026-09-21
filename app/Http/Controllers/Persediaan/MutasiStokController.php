<?php

namespace App\Http\Controllers\Persediaan;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\MutasiStok;
use App\Services\Stok\StockMutator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman "Mutasi Stok" — kartu stok / riwayat keluar-masuk barang.
 *
 * Halaman ini hanya membaca dan tidak menyediakan tombol ubah maupun hapus.
 * Mutasi stok adalah catatan yang bersifat catat-tambah: koreksi dilakukan
 * dengan mencatat penyesuaian baru lewat Stok Opname, bukan dengan mengubah
 * baris lama. Dengan begitu riwayatnya tetap bisa dipertanggungjawabkan.
 */
class MutasiStokController extends Controller
{
    /** @var array<string, string> */
    public const JENIS = [
        MutasiStok::MASUK => 'Masuk',
        MutasiStok::KELUAR => 'Keluar',
        MutasiStok::PENYESUAIAN => 'Penyesuaian',
    ];

    public function index(Request $request): View
    {
        $barangId = $request->query('barang', 'semua');
        $jenis = $request->query('jenis', 'semua');
        $sumber = $request->query('sumber', 'semua');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $mutasi = MutasiStok::query()
            ->with(['barang:id,kode_barang,nama_barang,satuan', 'user:id,name'])
            ->when(is_numeric($barangId), fn ($query) => $query->where('barang_id', (int) $barangId))
            ->when(array_key_exists($jenis, self::JENIS), fn ($query) => $query->where('jenis_mutasi', $jenis))
            ->when(in_array($sumber, StockMutator::SUMBER, true), fn ($query) => $query->where('sumber', $sumber))
            ->when($dari, fn ($query) => $query->whereDate('tanggal', '>=', $dari))
            ->when($sampai, fn ($query) => $query->whereDate('tanggal', '<=', $sampai))
            // Urut menurun: tanggal dulu, lalu id, supaya beberapa mutasi pada
            // tanggal yang sama tetap tampil sesuai urutan pencatatannya.
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('persediaan.mutasi.index', [
            'mutasi' => $mutasi,
            'barangId' => $barangId,
            'jenis' => $jenis,
            'sumber' => $sumber,
            'dari' => $dari,
            'sampai' => $sampai,
            'daftarJenis' => self::JENIS,
            'daftarSumber' => StockMutator::SUMBER,
            'daftarBarang' => Barang::orderBy('kode_barang')->get(['id', 'kode_barang', 'nama_barang']),
        ]);
    }
}
