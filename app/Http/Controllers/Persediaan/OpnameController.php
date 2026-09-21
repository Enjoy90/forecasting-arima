<?php

namespace App\Http\Controllers\Persediaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Persediaan\OpnameRequest;
use App\Models\Barang;
use App\Models\LogAktivitas;
use App\Models\MutasiStok;
use App\Services\Stok\StockMutator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Stok opname — menyelaraskan catatan sistem dengan hitungan fisik gudang.
 *
 * Tidak ada tabel khusus untuk opname. Hasil opname tersimpan sebagai mutasi
 * PENYESUAIAN dengan sumber 'opname', sehingga koreksi stok muncul di kartu
 * stok yang sama dengan pembelian, produksi, dan penjualan. Riwayat stok
 * dengan begitu tetap satu alur dan tidak terpecah dua tempat.
 *
 * Hanya tersedia index/create/store: catatan opname tidak boleh diubah atau
 * dihapus. Salah hitung diperbaiki dengan opname baru.
 */
class OpnameController extends Controller
{
    private const MODUL = 'Stok Opname';

    public function __construct(private readonly StockMutator $stockMutator)
    {
    }

    public function index(): View
    {
        $opname = MutasiStok::query()
            ->with(['barang:id,kode_barang,nama_barang,satuan', 'user:id,name'])
            ->where('sumber', 'opname')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20);

        return view('persediaan.opname.index', compact('opname'));
    }

    public function create(): View
    {
        return view('persediaan.opname.create', [
            'daftarBarang' => Barang::aktif()
                ->orderBy('kode_barang')
                ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'stok_tersedia']),
        ]);
    }

    public function store(OpnameRequest $request): RedirectResponse
    {
        $barang = Barang::findOrFail($request->integer('barang_id'));
        $stokSistem = $this->stockMutator->stokAwal($barang);
        $stokFisik = (float) $request->input('stok_fisik');

        $mutasi = $this->stockMutator->sesuaikanKe(
            barang: $barang,
            stokFisik: $stokFisik,
            tanggal: $request->date('tanggal'),
            keterangan: $request->input('keterangan') ?: 'Stok opname',
        );

        if ($mutasi === null) {
            return redirect()
                ->route('persediaan.opname.index')
                ->with('info', "Stok {$barang->nama_barang} sudah sesuai dengan catatan ({$stokSistem} {$barang->satuan}). Tidak ada penyesuaian yang dicatat.");
        }

        $selisih = (float) $mutasi->jumlah;
        $arah = $selisih > 0 ? 'lebih' : 'kurang';

        LogAktivitas::catat(
            self::MODUL,
            "Opname {$barang->kode_barang}: catatan {$stokSistem} -> fisik {$stokFisik} (selisih {$selisih})"
        );

        return redirect()
            ->route('persediaan.opname.index')
            ->with('sukses', sprintf(
                'Stok %s disesuaikan dari %s menjadi %s %s — %s %s dari catatan.',
                $barang->nama_barang,
                $this->angka($stokSistem),
                $this->angka($stokFisik),
                $barang->satuan,
                $this->angka(abs($selisih)),
                $arah,
            ));
    }

    private function angka(float $nilai): string
    {
        return rtrim(rtrim(number_format($nilai, 4, ',', '.'), '0'), ',');
    }
}
