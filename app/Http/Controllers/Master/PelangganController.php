<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\PelangganRequest;
use App\Models\LogAktivitas;
use App\Models\Pelanggan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD master data pelanggan (pembeli sekop).
 *
 * Catatan penghapusan:
 * Sama seperti supplier, foreign key penjualan.pelanggan_id memakai
 * nullOnDelete(). Menghapus pelanggan tidak ditolak database, melainkan
 * mengosongkan pelanggan pada faktur penjualannya. Karena penjualan adalah
 * sumber deret waktu yang diramalkan ARIMA, faktur tanpa pemilik akan
 * mengaburkan riwayat transaksi. Penghapusan semacam itu dicegah di sini;
 * pelanggan yang sudah tidak berlangganan cukup dinonaktifkan.
 */
class PelangganController extends Controller
{
    private const MODUL = 'Master Pelanggan';

    /**
     * Pilihan kolom enum `jenis` pada tabel pelanggan, beserta label tampilannya.
     * Dipakai juga oleh PelangganRequest sebagai sumber aturan validasi.
     *
     * @var array<string, string>
     */
    public const JENIS = [
        'toko' => 'Toko',
        'distributor' => 'Distributor',
        'perorangan' => 'Perorangan',
        'instansi' => 'Instansi',
    ];

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $jenis = $request->query('jenis', 'semua');
        $status = $request->query('status', 'semua');

        $pelanggan = Pelanggan::query()
            ->withCount('penjualan')
            ->when($cari !== '', function ($query) use ($cari) {
                $query->where(function ($sub) use ($cari) {
                    $sub->where('kode_pelanggan', 'like', "%{$cari}%")
                        ->orWhere('nama_pelanggan', 'like', "%{$cari}%")
                        ->orWhere('kota', 'like', "%{$cari}%")
                        ->orWhere('telepon', 'like', "%{$cari}%");
                });
            })
            ->when(array_key_exists($jenis, self::JENIS), fn ($query) => $query->where('jenis', $jenis))
            ->when($status === 'aktif', fn ($query) => $query->where('is_aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_aktif', false))
            ->orderBy('kode_pelanggan')
            ->paginate(10)
            ->withQueryString();

        return view('master.pelanggan.index', [
            'pelanggan' => $pelanggan,
            'cari' => $cari,
            'jenis' => $jenis,
            'status' => $status,
            'daftarJenis' => self::JENIS,
        ]);
    }

    public function create(): View
    {
        return view('master.pelanggan.create', [
            'pelanggan' => new Pelanggan(['jenis' => 'toko', 'is_aktif' => true]),
            'kodeUsulan' => $this->kodeBerikutnya(),
            'daftarJenis' => self::JENIS,
        ]);
    }

    public function store(PelangganRequest $request): RedirectResponse
    {
        $pelanggan = Pelanggan::create($request->validated());

        LogAktivitas::catat(self::MODUL, "Menambah pelanggan {$pelanggan->kode_pelanggan} - {$pelanggan->nama_pelanggan}");

        return redirect()
            ->route('master.pelanggan.index')
            ->with('sukses', "Pelanggan {$pelanggan->nama_pelanggan} berhasil ditambahkan.");
    }

    public function show(Pelanggan $pelanggan): RedirectResponse
    {
        // Tidak ada halaman detail terpisah; data sudah lengkap di tabel daftar.
        return redirect()->route('master.pelanggan.edit', $pelanggan);
    }

    public function edit(Pelanggan $pelanggan): View
    {
        return view('master.pelanggan.edit', [
            'pelanggan' => $pelanggan,
            'daftarJenis' => self::JENIS,
        ]);
    }

    public function update(PelangganRequest $request, Pelanggan $pelanggan): RedirectResponse
    {
        $pelanggan->update($request->validated());

        LogAktivitas::catat(self::MODUL, "Mengubah pelanggan {$pelanggan->kode_pelanggan} - {$pelanggan->nama_pelanggan}");

        return redirect()
            ->route('master.pelanggan.index')
            ->with('sukses', "Pelanggan {$pelanggan->nama_pelanggan} berhasil diperbarui.");
    }

    public function destroy(Pelanggan $pelanggan): RedirectResponse
    {
        $jumlahPenjualan = $pelanggan->penjualan()->count();

        if ($jumlahPenjualan > 0) {
            return redirect()
                ->route('master.pelanggan.index')
                ->with('gagal', "Pelanggan {$pelanggan->nama_pelanggan} tidak dapat dihapus karena masih terpakai pada ".
                    "{$jumlahPenjualan} transaksi penjualan. Nonaktifkan pelanggan ini lewat tombol Ubah bila sudah tidak berlangganan.");
        }

        $nama = $pelanggan->nama_pelanggan;
        $kode = $pelanggan->kode_pelanggan;

        $pelanggan->delete();

        LogAktivitas::catat(self::MODUL, "Menghapus pelanggan {$kode} - {$nama}");

        return redirect()
            ->route('master.pelanggan.index')
            ->with('sukses', "Pelanggan {$nama} berhasil dihapus.");
    }

    /**
     * Usulan kode berikutnya mengikuti pola seeder: PLG-01, PLG-02, ...
     * Hanya sebagai nilai awal form, pengguna tetap boleh menggantinya.
     */
    private function kodeBerikutnya(): string
    {
        $terakhir = Pelanggan::query()
            ->where('kode_pelanggan', 'like', 'PLG-%')
            ->orderByDesc('kode_pelanggan')
            ->value('kode_pelanggan');

        $urutan = $terakhir ? ((int) substr($terakhir, 4)) + 1 : 1;

        return 'PLG-'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
    }
}
