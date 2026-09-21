<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pembelian\PembelianRequest;
use App\Models\Barang;
use App\Models\DetailPembelian;
use App\Models\LogAktivitas;
use App\Models\Pembelian;
use App\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Order pembelian bahan ke supplier.
 *
 * Satu order melewati tiga keadaan:
 *
 *   dipesan  -> masih boleh diubah & dihapus, BELUM menyentuh stok
 *   diterima -> barang sudah masuk gudang, stok bertambah, dokumen dikunci
 *   batal    -> order tidak jadi, dokumen dikunci, stok tidak pernah tersentuh
 *
 * Stok baru berubah pada saat penerimaan, bukan saat order dibuat — karena
 * memesan barang tidak sama dengan barangnya sudah ada di gudang. Penerimaan
 * ditangani PenerimaanController.
 */
class PembelianController extends Controller
{
    private const MODUL = 'Pembelian';

    /** @var array<string, string> */
    public const STATUS = [
        'dipesan' => 'Dipesan',
        'diterima' => 'Diterima',
        'batal' => 'Batal',
    ];

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $status = $request->query('status', 'semua');
        $supplierId = $request->query('supplier', 'semua');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $pembelian = Pembelian::query()
            ->with(['supplier:id,kode_supplier,nama_supplier'])
            ->withCount('detail')
            ->when($cari !== '', fn ($q) => $q->where('no_pembelian', 'like', "%{$cari}%"))
            ->when(array_key_exists($status, self::STATUS), fn ($q) => $q->where('status', $status))
            ->when(is_numeric($supplierId), fn ($q) => $q->where('supplier_id', (int) $supplierId))
            ->when($dari, fn ($q) => $q->whereDate('tanggal_pembelian', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('tanggal_pembelian', '<=', $sampai))
            ->orderByDesc('tanggal_pembelian')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('pembelian.index', [
            'pembelian' => $pembelian,
            'cari' => $cari,
            'status' => $status,
            'supplierId' => $supplierId,
            'dari' => $dari,
            'sampai' => $sampai,
            'daftarStatus' => self::STATUS,
            'daftarSupplier' => Supplier::orderBy('kode_supplier')->get(['id', 'kode_supplier', 'nama_supplier']),
            'jumlahDipesan' => Pembelian::where('status', 'dipesan')->count(),
        ]);
    }

    public function create(): View
    {
        return view('pembelian.create', [
            'pembelian' => new Pembelian(['tanggal_pembelian' => now()]),
            'baris' => old('detail', [['barang_id' => '', 'jumlah' => 1, 'harga_satuan' => 0]]),
        ] + $this->pilihanForm());
    }

    public function store(PembelianRequest $request): RedirectResponse
    {
        $pembelian = DB::transaction(function () use ($request) {
            $tanggal = Carbon::parse($request->input('tanggal_pembelian'));

            $pembelian = Pembelian::create([
                'no_pembelian' => $this->nomorBerikutnya($tanggal),
                'tanggal_pembelian' => $tanggal,
                'supplier_id' => $request->integer('supplier_id'),
                'user_id' => auth()->id(),
                'status' => 'dipesan',
                'sumber_data' => 'manual',
                'keterangan' => $request->input('keterangan'),
                'total_harga' => 0,
            ]);

            $this->simpanDetail($pembelian, $request->input('detail'));

            return $pembelian;
        });

        LogAktivitas::catat(self::MODUL, "Membuat order pembelian {$pembelian->no_pembelian}");

        return redirect()
            ->route('pembelian.order.show', $pembelian)
            ->with('sukses', "Order pembelian {$pembelian->no_pembelian} berhasil dibuat. Stok belum bertambah sampai barang diterima.");
    }

    public function show(Pembelian $order): View
    {
        $order->load(['supplier', 'user', 'detail.barang']);

        return view('pembelian.show', [
            'pembelian' => $order,
            'daftarStatus' => self::STATUS,
        ]);
    }

    public function edit(Pembelian $order): View|RedirectResponse
    {
        if ($order->status !== 'dipesan') {
            return $this->tolakKarenaTerkunci($order);
        }

        $order->load('detail');

        return view('pembelian.edit', [
            'pembelian' => $order,
            'baris' => old('detail', $order->detail->map(fn ($d) => [
                'barang_id' => $d->barang_id,
                'jumlah' => $d->jumlah,
                'harga_satuan' => (float) $d->harga_satuan,
            ])->all()),
        ] + $this->pilihanForm());
    }

    public function update(PembelianRequest $request, Pembelian $order): RedirectResponse
    {
        if ($order->status !== 'dipesan') {
            return $this->tolakKarenaTerkunci($order);
        }

        DB::transaction(function () use ($request, $order) {
            $order->update([
                'tanggal_pembelian' => Carbon::parse($request->input('tanggal_pembelian')),
                'supplier_id' => $request->integer('supplier_id'),
                'keterangan' => $request->input('keterangan'),
            ]);

            // Baris lama dihapus lalu ditulis ulang. Aman dilakukan karena
            // order berstatus dipesan belum pernah menyentuh stok sama sekali.
            $order->detail()->delete();

            $this->simpanDetail($order, $request->input('detail'));
        });

        LogAktivitas::catat(self::MODUL, "Mengubah order pembelian {$order->no_pembelian}");

        return redirect()
            ->route('pembelian.order.show', $order)
            ->with('sukses', "Order pembelian {$order->no_pembelian} berhasil diperbarui.");
    }

    public function destroy(Pembelian $order): RedirectResponse
    {
        if ($order->status !== 'dipesan') {
            return $this->tolakKarenaTerkunci($order);
        }

        $nomor = $order->no_pembelian;

        // detail_pembelian memakai cascadeOnDelete, jadi barisnya ikut terhapus.
        $order->delete();

        LogAktivitas::catat(self::MODUL, "Menghapus order pembelian {$nomor}");

        return redirect()
            ->route('pembelian.order.index')
            ->with('sukses', "Order pembelian {$nomor} berhasil dihapus.");
    }

    /**
     * Membatalkan order yang belum diterima. Dokumennya tetap disimpan
     * sebagai jejak bahwa pernah ada rencana pembelian yang tidak jadi.
     */
    public function batal(Pembelian $order): RedirectResponse
    {
        if ($order->status !== 'dipesan') {
            return $this->tolakKarenaTerkunci($order);
        }

        $order->update(['status' => 'batal']);

        LogAktivitas::catat(self::MODUL, "Membatalkan order pembelian {$order->no_pembelian}");

        return redirect()
            ->route('pembelian.order.show', $order)
            ->with('sukses', "Order pembelian {$order->no_pembelian} dibatalkan.");
    }

    /**
     * Riwayat Pembelian Bahan — dilihat per baris barang, bukan per nota.
     *
     * Susunan ini yang dibutuhkan saat menelusuri harga beli dan waktu tunggu
     * sebuah bahan dari waktu ke waktu; tampilan per nota tidak menjawab itu.
     */
    public function riwayat(Request $request): View
    {
        $barangId = $request->query('barang', 'semua');
        $supplierId = $request->query('supplier', 'semua');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $baris = DetailPembelian::query()
            ->with(['barang:id,kode_barang,nama_barang,satuan', 'pembelian.supplier:id,nama_supplier'])
            // Hanya pembelian yang benar-benar diterima yang mencerminkan
            // pengadaan bahan; order batal tidak pernah terwujud.
            ->whereHas('pembelian', fn ($q) => $q->where('status', 'diterima'))
            ->when(is_numeric($barangId), fn ($q) => $q->where('barang_id', (int) $barangId))
            ->when(is_numeric($supplierId), fn ($q) => $q->whereHas('pembelian', fn ($p) => $p->where('supplier_id', (int) $supplierId)))
            ->when($dari, fn ($q) => $q->whereHas('pembelian', fn ($p) => $p->whereDate('tanggal_terima', '>=', $dari)))
            ->when($sampai, fn ($q) => $q->whereHas('pembelian', fn ($p) => $p->whereDate('tanggal_terima', '<=', $sampai)))
            ->join('pembelian', 'pembelian.id', '=', 'detail_pembelian.pembelian_id')
            ->orderByDesc('pembelian.tanggal_terima')
            ->orderByDesc('detail_pembelian.id')
            ->select('detail_pembelian.*')
            ->paginate(25)
            ->withQueryString();

        return view('pembelian.riwayat', [
            'baris' => $baris,
            'barangId' => $barangId,
            'supplierId' => $supplierId,
            'dari' => $dari,
            'sampai' => $sampai,
            'daftarBarang' => Barang::orderBy('kode_barang')->get(['id', 'kode_barang', 'nama_barang']),
            'daftarSupplier' => Supplier::orderBy('kode_supplier')->get(['id', 'kode_supplier', 'nama_supplier']),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $detail
     */
    private function simpanDetail(Pembelian $pembelian, array $detail): void
    {
        $total = 0.0;

        foreach ($detail as $baris) {
            $jumlah = (int) $baris['jumlah'];
            $harga = (float) $baris['harga_satuan'];
            $subtotal = $jumlah * $harga;
            $total += $subtotal;

            $pembelian->detail()->create([
                'barang_id' => (int) $baris['barang_id'],
                'jumlah' => $jumlah,
                'harga_satuan' => $harga,
                'subtotal' => $subtotal,
            ]);
        }

        $pembelian->update(['total_harga' => $total]);
    }

    private function tolakKarenaTerkunci(Pembelian $order): RedirectResponse
    {
        $status = self::STATUS[$order->status] ?? $order->status;

        return redirect()
            ->route('pembelian.order.show', $order)
            ->with('gagal', "Order {$order->no_pembelian} berstatus {$status} sehingga tidak dapat diubah lagi. ".
                'Hanya order berstatus Dipesan yang masih boleh diubah.');
    }

    /**
     * Nomor dokumen berpola PB-YYYYMM-0001, diurutkan per bulan.
     */
    private function nomorBerikutnya(CarbonInterface $tanggal): string
    {
        $awalan = 'PB-'.$tanggal->format('Ym').'-';

        $terakhir = Pembelian::query()
            ->where('no_pembelian', 'like', $awalan.'%')
            ->orderByDesc('no_pembelian')
            ->value('no_pembelian');

        $urutan = $terakhir ? ((int) substr($terakhir, strlen($awalan))) + 1 : 1;

        return $awalan.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function pilihanForm(): array
    {
        return [
            'daftarSupplier' => Supplier::where('is_aktif', true)
                ->orderBy('kode_supplier')
                ->get(['id', 'kode_supplier', 'nama_supplier']),
            'daftarBarang' => Barang::aktif()
                ->orderBy('jenis_barang')
                ->orderBy('kode_barang')
                ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'harga_beli', 'jenis_barang']),
        ];
    }
}
