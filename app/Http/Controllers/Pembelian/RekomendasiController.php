<?php

namespace App\Http\Controllers\Pembelian;

use App\Models\KebutuhanBahan;
use App\Models\LogAktivitas;
use App\Models\Pembelian;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Buat Order dari Rekomendasi" — satu-satunya titik temu Modul A dan Modul B.
 *
 * Kesepakatan pembagian modul (docs/04-pembagian-modul.md bagian 5.3):
 *   Modul B hanya MENULIS rekomendasi ke tabel `kebutuhan_bahan`.
 *   Modul A menyediakan tombol ini yang MEMBACA tabel itu.
 *
 * Jadi tidak ada satu berkas pun yang dikerjakan berdua.
 *
 * Halaman ini mengelompokkan rekomendasi menurut supplier bahan, karena satu
 * order pembelian ditujukan ke satu supplier. Bahan yang belum punya supplier
 * dipisahkan dan tidak dapat diorder sebelum suppliernya ditentukan di master
 * barang.
 *
 * Order yang dihasilkan berstatus `dipesan` — sama persis dengan order yang
 * dibuat manual, termasuk dalam hal stok baru bertambah saat penerimaan.
 */
class RekomendasiController extends Controller
{
    private const MODUL = 'Pembelian';

    public function index(): View
    {
        $rekomendasi = KebutuhanBahan::query()
            ->perluBeli()
            ->where('qty_rekomendasi_beli', '>', 0)
            ->with([
                'barang:id,kode_barang,nama_barang,satuan,supplier_id,harga_beli,stok_tersedia',
                'barang.supplier:id,kode_supplier,nama_supplier,is_aktif',
                'tahapan:id,nama_tahapan',
                'targetProduksi:id,periode',
            ])
            ->orderByDesc('status')
            ->get();

        // Dikelompokkan per supplier karena satu order ditujukan ke satu pemasok.
        $perSupplier = $rekomendasi
            ->filter(fn ($r) => $r->barang?->supplier_id)
            ->groupBy(fn ($r) => $r->barang->supplier_id);

        return view('pembelian.rekomendasi', [
            'perSupplier' => $perSupplier,
            'tanpaSupplier' => $rekomendasi->filter(fn ($r) => ! $r->barang?->supplier_id),
            'total' => $rekomendasi->count(),
        ]);
    }

    /**
     * Membuat satu order pembelian dari baris rekomendasi yang dipilih.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kebutuhan' => ['required', 'array', 'min:1'],
            'kebutuhan.*' => ['integer', 'exists:kebutuhan_bahan,id'],
        ], [
            'kebutuhan.required' => 'Pilih dulu bahan yang hendak diorder.',
            'kebutuhan.min' => 'Pilih dulu bahan yang hendak diorder.',
        ]);

        $baris = KebutuhanBahan::query()
            ->whereIn('id', $data['kebutuhan'])
            ->with('barang.supplier')
            ->get();

        $supplierIds = $baris->pluck('barang.supplier_id')->unique()->filter();

        if ($supplierIds->count() !== 1) {
            return back()->with('gagal', $supplierIds->isEmpty()
                ? 'Bahan yang dipilih belum punya supplier. Tentukan suppliernya lebih dulu di master barang.'
                : 'Satu order hanya boleh untuk satu supplier. Pilih bahan dari supplier yang sama saja.');
        }

        // Satu barang bisa muncul pada beberapa baris rekomendasi (mis. dipakai
        // di dua tahapan). Kebutuhannya dijumlahkan supaya order tidak berisi
        // dua baris untuk barang yang sama.
        $perBarang = $baris->groupBy('barang_id')->map(fn ($grup) => [
            'barang' => $grup->first()->barang,
            'jumlah' => (float) $grup->sum('qty_rekomendasi_beli'),
        ]);

        $pembelian = DB::transaction(function () use ($perBarang, $supplierIds) {
            $tanggal = Carbon::now();

            $pembelian = Pembelian::create([
                'no_pembelian' => $this->nomorBerikutnya($tanggal),
                'tanggal_pembelian' => $tanggal,
                'supplier_id' => $supplierIds->first(),
                'user_id' => auth()->id(),
                'status' => 'dipesan',
                'sumber_data' => 'manual',
                'keterangan' => 'Dibuat dari rekomendasi kebutuhan bahan (Modul B)',
                'total_harga' => 0,
            ]);

            $total = 0.0;

            foreach ($perBarang as $item) {
                // Dibulatkan ke atas: memesan bahan setengah unit tidak mungkin,
                // dan kekurangan sedikit lebih merugikan daripada kelebihan sedikit.
                $jumlah = (int) ceil($item['jumlah']);
                $harga = (float) $item['barang']->harga_beli;
                $subtotal = $jumlah * $harga;
                $total += $subtotal;

                $pembelian->detail()->create([
                    'barang_id' => $item['barang']->id,
                    'jumlah' => $jumlah,
                    'harga_satuan' => $harga,
                    'subtotal' => $subtotal,
                ]);
            }

            $pembelian->update(['total_harga' => $total]);

            return $pembelian;
        });

        LogAktivitas::catat(self::MODUL, "Membuat order {$pembelian->no_pembelian} dari rekomendasi kebutuhan bahan");

        return redirect()
            ->route('pembelian.order.show', $pembelian)
            ->with('sukses', "Order {$pembelian->no_pembelian} dibuat dari rekomendasi. ".
                'Periksa jumlah dan harganya sebelum dikirim ke supplier.');
    }

    private function nomorBerikutnya(Carbon $tanggal): string
    {
        $awalan = 'PB-'.$tanggal->format('Ym').'-';

        $terakhir = Pembelian::query()
            ->where('no_pembelian', 'like', $awalan.'%')
            ->orderByDesc('no_pembelian')
            ->value('no_pembelian');

        $urutan = $terakhir ? ((int) substr($terakhir, strlen($awalan))) + 1 : 1;

        return $awalan.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
    }
}
