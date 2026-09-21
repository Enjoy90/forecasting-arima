<?php

namespace App\Http\Controllers\Penjualan;

use App\Exceptions\StokTidakCukupException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Penjualan\PenjualanRequest;
use App\Models\Barang;
use App\Models\LogAktivitas;
use App\Models\MutasiStok;
use App\Models\Pelanggan;
use App\Models\Penjualan;
use App\Services\Stok\StockMutator;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Transaksi penjualan.
 *
 * Berbeda dari pembelian, penjualan tidak punya tahap "dipesan": begitu faktur
 * dibuat, barang dianggap sudah keluar gudang. Karena itu mutasi KELUAR dicatat
 * langsung pada saat penyimpanan, dan faktur akan ditolak bila stok tidak cukup.
 *
 * Tabel penjualan adalah sumber deret waktu yang diramalkan ARIMA
 * (SUM(detail_penjualan.jumlah) per bulan per barang jadi), jadi isinya dijaga
 * agar selalu mencerminkan penjualan yang benar-benar terjadi:
 *
 *   - Baris barang tidak dapat disunting setelah faktur tersimpan. Yang boleh
 *     diubah hanya bagian kepala (tanggal, pembeli, keterangan).
 *   - Penghapusan faktur mengembalikan stok lewat mutasi MASUK pengimbang,
 *     bukan dengan menghapus jejak mutasi lamanya.
 */
class PenjualanController extends Controller
{
    private const MODUL = 'Penjualan';

    public function __construct(private readonly StockMutator $stockMutator)
    {
    }

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $pelangganId = $request->query('pelanggan', 'semua');
        $sumber = $request->query('sumber', 'semua');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $penjualan = Penjualan::query()
            ->with(['pelanggan:id,nama_pelanggan'])
            ->withCount('detail')
            ->when($cari !== '', fn ($q) => $q->where('no_faktur', 'like', "%{$cari}%"))
            ->when(is_numeric($pelangganId), fn ($q) => $q->where('pelanggan_id', (int) $pelangganId))
            ->when(in_array($sumber, ['manual', 'import'], true), fn ($q) => $q->where('sumber_data', $sumber))
            ->when($dari, fn ($q) => $q->whereDate('tanggal_penjualan', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('tanggal_penjualan', '<=', $sampai))
            ->orderByDesc('tanggal_penjualan')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('penjualan.index', [
            'penjualan' => $penjualan,
            'cari' => $cari,
            'pelangganId' => $pelangganId,
            'sumber' => $sumber,
            'dari' => $dari,
            'sampai' => $sampai,
            'daftarPelanggan' => Pelanggan::orderBy('kode_pelanggan')->get(['id', 'kode_pelanggan', 'nama_pelanggan']),
        ]);
    }

    public function create(): View
    {
        return view('penjualan.create', [
            'penjualan' => new Penjualan(['tanggal_penjualan' => now()]),
            'baris' => old('detail', [['barang_id' => '', 'jumlah' => 1, 'harga_satuan' => 0]]),
        ] + $this->pilihanForm());
    }

    public function store(PenjualanRequest $request): RedirectResponse
    {
        try {
            $penjualan = DB::transaction(function () use ($request) {
                $tanggal = Carbon::parse($request->input('tanggal_penjualan'));

                $penjualan = Penjualan::create([
                    'no_faktur' => $this->nomorBerikutnya($tanggal),
                    'tanggal_penjualan' => $tanggal,
                    'pelanggan_id' => $request->input('pelanggan_id'),
                    'nama_pelanggan_manual' => $request->input('nama_pelanggan_manual'),
                    'user_id' => auth()->id(),
                    'sumber_data' => 'manual',
                    'keterangan' => $request->input('keterangan'),
                    'total_harga' => 0,
                ]);

                $total = 0.0;

                foreach ($request->input('detail') as $baris) {
                    $barang = Barang::findOrFail((int) $baris['barang_id']);
                    $jumlah = (int) $baris['jumlah'];
                    $harga = (float) $baris['harga_satuan'];
                    $subtotal = $jumlah * $harga;
                    $total += $subtotal;

                    $penjualan->detail()->create([
                        'barang_id' => $barang->id,
                        'jumlah' => $jumlah,
                        'harga_satuan' => $harga,
                        'subtotal' => $subtotal,
                    ]);

                    // Stok keluar seketika. Bila salah satu baris kekurangan
                    // stok, seluruh faktur dibatalkan oleh transaksi.
                    $this->stockMutator->catat(
                        barang: $barang,
                        jenis: MutasiStok::KELUAR,
                        sumber: 'penjualan',
                        jumlah: (float) $jumlah,
                        tanggal: $tanggal,
                        referensi: $penjualan,
                        keterangan: "Penjualan {$penjualan->no_faktur}",
                    );
                }

                $penjualan->update(['total_harga' => $total]);

                return $penjualan;
            });
        } catch (StokTidakCukupException $e) {
            return back()
                ->withInput()
                ->with('gagal', $e->getMessage().' Faktur tidak disimpan.');
        }

        LogAktivitas::catat(self::MODUL, "Membuat faktur penjualan {$penjualan->no_faktur}");

        return redirect()
            ->route('penjualan.faktur.show', $penjualan)
            ->with('sukses', "Faktur {$penjualan->no_faktur} tersimpan dan stok barang sudah berkurang.");
    }

    public function show(Penjualan $faktur): View
    {
        $faktur->load(['pelanggan', 'user', 'detail.barang']);

        return view('penjualan.show', ['penjualan' => $faktur]);
    }

    public function edit(Penjualan $faktur): View
    {
        $faktur->load('detail.barang');

        return view('penjualan.edit', [
            'penjualan' => $faktur,
            'daftarPelanggan' => Pelanggan::where('is_aktif', true)
                ->orderBy('kode_pelanggan')
                ->get(['id', 'kode_pelanggan', 'nama_pelanggan']),
        ]);
    }

    /**
     * Hanya bagian kepala faktur yang diperbarui. Baris barang sengaja tidak
     * ikut diubah karena stoknya sudah bergerak saat faktur dibuat.
     */
    public function update(PenjualanRequest $request, Penjualan $faktur): RedirectResponse
    {
        $faktur->update([
            'tanggal_penjualan' => Carbon::parse($request->input('tanggal_penjualan')),
            'pelanggan_id' => $request->input('pelanggan_id'),
            'nama_pelanggan_manual' => $request->input('nama_pelanggan_manual'),
            'keterangan' => $request->input('keterangan'),
        ]);

        LogAktivitas::catat(self::MODUL, "Mengubah keterangan faktur {$faktur->no_faktur}");

        return redirect()
            ->route('penjualan.faktur.show', $faktur)
            ->with('sukses', "Faktur {$faktur->no_faktur} berhasil diperbarui.");
    }

    /**
     * Menghapus faktur sekaligus mengembalikan stok yang pernah dikeluarkannya.
     *
     * Pengembalian dilakukan dengan mencatat mutasi MASUK pengimbang, bukan
     * dengan menghapus mutasi lama, supaya riwayat gudang tetap menunjukkan
     * bahwa barang sempat keluar lalu dikembalikan.
     *
     * Faktur hasil seeder atau import lama yang belum punya mutasi tidak
     * diberi pengimbang, karena stoknya memang tidak pernah dikurangi.
     */
    public function destroy(Penjualan $faktur): RedirectResponse
    {
        $nomor = $faktur->no_faktur;

        DB::transaction(function () use ($faktur, $nomor) {
            $mutasiKeluar = MutasiStok::query()
                ->where('referensi_tipe', Penjualan::class)
                ->where('referensi_id', $faktur->getKey())
                ->where('jenis_mutasi', MutasiStok::KELUAR)
                ->get();

            foreach ($mutasiKeluar as $mutasi) {
                $this->stockMutator->catat(
                    barang: $mutasi->barang,
                    jenis: MutasiStok::MASUK,
                    sumber: 'penjualan',
                    jumlah: (float) $mutasi->jumlah,
                    tanggal: now(),
                    keterangan: "Pembatalan faktur {$nomor}",
                );
            }

            // detail_penjualan memakai cascadeOnDelete.
            $faktur->delete();
        });

        LogAktivitas::catat(self::MODUL, "Menghapus faktur penjualan {$nomor}");

        return redirect()
            ->route('penjualan.faktur.index')
            ->with('sukses', "Faktur {$nomor} dihapus dan stok barangnya dikembalikan.");
    }

    /**
     * Nomor faktur berpola FJ-YYYYMM-0001, diurutkan per bulan.
     */
    private function nomorBerikutnya(CarbonInterface $tanggal): string
    {
        $awalan = 'FJ-'.$tanggal->format('Ym').'-';

        $terakhir = Penjualan::query()
            ->where('no_faktur', 'like', $awalan.'%')
            ->orderByDesc('no_faktur')
            ->value('no_faktur');

        $urutan = $terakhir ? ((int) substr($terakhir, strlen($awalan))) + 1 : 1;

        return $awalan.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function pilihanForm(): array
    {
        return [
            'daftarPelanggan' => Pelanggan::where('is_aktif', true)
                ->orderBy('kode_pelanggan')
                ->get(['id', 'kode_pelanggan', 'nama_pelanggan']),
            // Barang jadi didahulukan karena itulah yang biasanya dijual,
            // tetapi barang lain tetap tersedia bila memang ada penjualan sisa.
            'daftarBarang' => Barang::aktif()
                ->orderByRaw("FIELD(jenis_barang, 'barang_jadi', 'setengah_jadi', 'bahan_baku')")
                ->orderBy('kode_barang')
                ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'harga_jual', 'stok_tersedia']),
        ];
    }
}
