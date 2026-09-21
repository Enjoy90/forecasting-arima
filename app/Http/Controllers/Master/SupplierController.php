<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\SupplierRequest;
use App\Models\LogAktivitas;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD master data supplier (pemasok bahan baku).
 *
 * Catatan penghapusan:
 * Berbeda dengan kategori, foreign key barang.supplier_id dan
 * pembelian.supplier_id memakai nullOnDelete(). Artinya database TIDAK
 * menghalangi penghapusan, melainkan diam-diam mengosongkan supplier pada
 * barang dan riwayat pembelian yang bersangkutan. Karena riwayat pembelian
 * adalah bukti transaksi dan supplier barang menjadi acuan lead time pembelian
 * (dipakai Modul B), penghapusan semacam itu dicegah di sini. Supplier yang
 * sudah tidak dipakai lagi cukup dinonaktifkan lewat kolom is_aktif.
 */
class SupplierController extends Controller
{
    private const MODUL = 'Master Supplier';

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $status = $request->query('status', 'semua');

        $supplier = Supplier::query()
            ->withCount(['barang', 'pembelian'])
            ->when($cari !== '', function ($query) use ($cari) {
                $query->where(function ($sub) use ($cari) {
                    $sub->where('kode_supplier', 'like', "%{$cari}%")
                        ->orWhere('nama_supplier', 'like', "%{$cari}%")
                        ->orWhere('telepon', 'like', "%{$cari}%");
                });
            })
            ->when($status === 'aktif', fn ($query) => $query->where('is_aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_aktif', false))
            ->orderBy('kode_supplier')
            ->paginate(10)
            ->withQueryString();

        return view('master.supplier.index', compact('supplier', 'cari', 'status'));
    }

    public function create(): View
    {
        return view('master.supplier.create', [
            'supplier' => new Supplier(['lead_time_default' => 7, 'is_aktif' => true]),
            'kodeUsulan' => $this->kodeBerikutnya(),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        LogAktivitas::catat(self::MODUL, "Menambah supplier {$supplier->kode_supplier} - {$supplier->nama_supplier}");

        return redirect()
            ->route('master.supplier.index')
            ->with('sukses', "Supplier {$supplier->nama_supplier} berhasil ditambahkan.");
    }

    public function show(Supplier $supplier): RedirectResponse
    {
        // Tidak ada halaman detail terpisah; data sudah lengkap di tabel daftar.
        return redirect()->route('master.supplier.edit', $supplier);
    }

    public function edit(Supplier $supplier): View
    {
        return view('master.supplier.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        LogAktivitas::catat(self::MODUL, "Mengubah supplier {$supplier->kode_supplier} - {$supplier->nama_supplier}");

        return redirect()
            ->route('master.supplier.index')
            ->with('sukses', "Supplier {$supplier->nama_supplier} berhasil diperbarui.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $jumlahBarang = $supplier->barang()->count();
        $jumlahPembelian = $supplier->pembelian()->count();

        if ($jumlahBarang > 0 || $jumlahPembelian > 0) {
            $dipakai = [];

            if ($jumlahBarang > 0) {
                $dipakai[] = "{$jumlahBarang} data barang";
            }

            if ($jumlahPembelian > 0) {
                $dipakai[] = "{$jumlahPembelian} transaksi pembelian";
            }

            return redirect()
                ->route('master.supplier.index')
                ->with('gagal', "Supplier {$supplier->nama_supplier} tidak dapat dihapus karena masih terpakai pada ".
                    implode(' dan ', $dipakai).'. Nonaktifkan supplier ini lewat tombol Ubah bila sudah tidak dipakai lagi.');
        }

        $nama = $supplier->nama_supplier;
        $kode = $supplier->kode_supplier;

        $supplier->delete();

        LogAktivitas::catat(self::MODUL, "Menghapus supplier {$kode} - {$nama}");

        return redirect()
            ->route('master.supplier.index')
            ->with('sukses', "Supplier {$nama} berhasil dihapus.");
    }

    /**
     * Usulan kode berikutnya mengikuti pola seeder: SUP-01, SUP-02, ...
     * Hanya sebagai nilai awal form, pengguna tetap boleh menggantinya.
     */
    private function kodeBerikutnya(): string
    {
        $terakhir = Supplier::query()
            ->where('kode_supplier', 'like', 'SUP-%')
            ->orderByDesc('kode_supplier')
            ->value('kode_supplier');

        $urutan = $terakhir ? ((int) substr($terakhir, 4)) + 1 : 1;

        return 'SUP-'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
    }
}
