<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\KategoriRequest;
use App\Models\Kategori;
use App\Models\LogAktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD master data kategori barang.
 *
 * Kategori dipakai sebagai pengelompokan barang (bahan baku, setengah jadi,
 * dan barang jadi). Karena barang.kategori_id memakai restrictOnDelete,
 * kategori yang masih dipakai barang tidak boleh dihapus.
 */
class KategoriController extends Controller
{
    private const MODUL = 'Master Kategori';

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));

        $kategori = Kategori::query()
            ->withCount('barang')
            ->when($cari !== '', function ($query) use ($cari) {
                $query->where(function ($sub) use ($cari) {
                    $sub->where('kode_kategori', 'like', "%{$cari}%")
                        ->orWhere('nama_kategori', 'like', "%{$cari}%");
                });
            })
            ->orderBy('kode_kategori')
            ->paginate(10)
            ->withQueryString();

        return view('master.kategori.index', compact('kategori', 'cari'));
    }

    public function create(): View
    {
        return view('master.kategori.create', [
            'kategori' => new Kategori(),
            'kodeUsulan' => $this->kodeBerikutnya(),
        ]);
    }

    public function store(KategoriRequest $request): RedirectResponse
    {
        $kategori = Kategori::create($request->validated());

        LogAktivitas::catat(self::MODUL, "Menambah kategori {$kategori->kode_kategori} - {$kategori->nama_kategori}");

        return redirect()
            ->route('master.kategori.index')
            ->with('sukses', "Kategori {$kategori->nama_kategori} berhasil ditambahkan.");
    }

    public function show(Kategori $kategori): RedirectResponse
    {
        // Tidak ada halaman detail terpisah; data sudah lengkap di tabel daftar.
        return redirect()->route('master.kategori.edit', $kategori);
    }

    public function edit(Kategori $kategori): View
    {
        return view('master.kategori.edit', compact('kategori'));
    }

    public function update(KategoriRequest $request, Kategori $kategori): RedirectResponse
    {
        $kategori->update($request->validated());

        LogAktivitas::catat(self::MODUL, "Mengubah kategori {$kategori->kode_kategori} - {$kategori->nama_kategori}");

        return redirect()
            ->route('master.kategori.index')
            ->with('sukses', "Kategori {$kategori->nama_kategori} berhasil diperbarui.");
    }

    public function destroy(Kategori $kategori): RedirectResponse
    {
        if ($kategori->barang()->exists()) {
            return redirect()
                ->route('master.kategori.index')
                ->with('gagal', "Kategori {$kategori->nama_kategori} tidak dapat dihapus karena masih dipakai oleh data barang.");
        }

        $nama = $kategori->nama_kategori;
        $kode = $kategori->kode_kategori;

        $kategori->delete();

        LogAktivitas::catat(self::MODUL, "Menghapus kategori {$kode} - {$nama}");

        return redirect()
            ->route('master.kategori.index')
            ->with('sukses', "Kategori {$nama} berhasil dihapus.");
    }

    /**
     * Usulan kode berikutnya mengikuti pola seeder: KTG-01, KTG-02, ...
     * Hanya sebagai nilai awal form, pengguna tetap boleh menggantinya.
     */
    private function kodeBerikutnya(): string
    {
        $terakhir = Kategori::query()
            ->where('kode_kategori', 'like', 'KTG-%')
            ->orderByDesc('kode_kategori')
            ->value('kode_kategori');

        $urutan = $terakhir ? ((int) substr($terakhir, 4)) + 1 : 1;

        return 'KTG-'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
    }
}
