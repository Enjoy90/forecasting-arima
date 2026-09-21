<?php

namespace App\Http\Controllers\Pembelian;

use App\Exceptions\StokTidakCukupException;
use App\Http\Controllers\Controller;
use App\Models\LogAktivitas;
use App\Models\MutasiStok;
use App\Models\Pembelian;
use App\Services\Stok\StockMutator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penerimaan barang dari order pembelian.
 *
 * Inilah satu-satunya titik pada modul pembelian yang menyentuh stok. Saat
 * order dinyatakan diterima, setiap baris barang dicatat sebagai mutasi MASUK
 * lewat StockMutator, dengan referensi menunjuk ke order pembeliannya — jadi
 * setiap tambahan stok selalu bisa ditelusuri kembali ke notanya.
 *
 * Seluruh baris dicatat di dalam satu transaksi. Bila satu baris gagal, tidak
 * ada baris lain yang tertinggal setengah jalan.
 */
class PenerimaanController extends Controller
{
    private const MODUL = 'Penerimaan Pembelian';

    public function __construct(private readonly StockMutator $stockMutator)
    {
    }

    public function store(Request $request, Pembelian $order): RedirectResponse
    {
        if ($order->status !== 'dipesan') {
            $status = PembelianController::STATUS[$order->status] ?? $order->status;

            return redirect()
                ->route('pembelian.order.show', $order)
                ->with('gagal', "Order {$order->no_pembelian} sudah berstatus {$status}, jadi tidak dapat diterima lagi.");
        }

        $data = $request->validate([
            'tanggal_terima' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'tanggal_terima.before_or_equal' => 'Tanggal terima tidak boleh di masa depan.',
        ]);

        $order->load('detail.barang');

        if ($order->detail->isEmpty()) {
            return redirect()
                ->route('pembelian.order.show', $order)
                ->with('gagal', "Order {$order->no_pembelian} tidak punya baris barang sehingga tidak ada yang bisa diterima.");
        }

        $tanggalTerima = Carbon::parse($data['tanggal_terima']);

        try {
            DB::transaction(function () use ($order, $tanggalTerima) {
                foreach ($order->detail as $baris) {
                    $this->stockMutator->catat(
                        barang: $baris->barang,
                        jenis: MutasiStok::MASUK,
                        sumber: 'pembelian',
                        jumlah: (float) $baris->jumlah,
                        tanggal: $tanggalTerima,
                        referensi: $order,
                        keterangan: "Penerimaan {$order->no_pembelian}".
                            ($order->supplier ? " dari {$order->supplier->nama_supplier}" : ''),
                    );
                }

                $order->update([
                    'status' => 'diterima',
                    'tanggal_terima' => $tanggalTerima,
                ]);
            });
        } catch (StokTidakCukupException $e) {
            // Secara teori tidak terjadi pada mutasi masuk, tetapi ditangkap
            // agar kegagalan tak terduga tidak muncul sebagai layar error.
            return redirect()
                ->route('pembelian.order.show', $order)
                ->with('gagal', $e->getMessage());
        }

        $jumlahBaris = $order->detail->count();

        LogAktivitas::catat(
            self::MODUL,
            "Menerima order {$order->no_pembelian} ({$jumlahBaris} baris barang) pada {$tanggalTerima->format('d/m/Y')}"
        );

        return redirect()
            ->route('pembelian.order.show', $order)
            ->with('sukses', "Order {$order->no_pembelian} diterima. Stok {$jumlahBaris} barang sudah bertambah dan tercatat di mutasi stok.");
    }
}
