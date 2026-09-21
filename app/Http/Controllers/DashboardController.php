<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\LogAktivitas;
use App\Models\MutasiStok;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Produksi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Ringkasan keadaan pabrik pada satu halaman.
 *
 * Isinya dipilih berdasarkan pertanyaan yang paling sering muncul tiap pagi:
 * apa yang perlu ditindaklanjuti hari ini (stok menipis, order menunggu
 * penerimaan, perintah produksi berjalan), dan bagaimana tren penjualannya.
 *
 * Grafik tren memakai 12 bulan terakhir karena itulah rentang yang cukup untuk
 * melihat pola musiman tanpa membuat sumbu waktunya terlalu padat.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'jumlahBarangAktif' => Barang::aktif()->count(),
            'jumlahMenipis' => Barang::aktif()->whereColumn('stok_tersedia', '<=', 'stok_minimum')->count(),
            'nilaiPersediaan' => (float) Barang::aktif()
                ->selectRaw('COALESCE(SUM(stok_tersedia * harga_beli), 0) AS nilai')
                ->value('nilai'),

            'orderMenunggu' => Pembelian::where('status', 'dipesan')->count(),
            'produksiBerjalan' => Produksi::where('status', Produksi::STATUS_PROSES)->count(),
            'bomAktif' => Bom::where('is_aktif', true)->count(),

            'penjualanBulanIni' => $this->penjualanBulanIni(),
            'trenPenjualan' => $this->trenPenjualan(),
            'barangMenipis' => $this->barangMenipis(),
            'aktivitasTerbaru' => LogAktivitas::with('user:id,name')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'mutasiTerbaru' => MutasiStok::with('barang:id,kode_barang,nama_barang,satuan')
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
        ]);
    }

    /**
     * @return array{jumlah: float, nilai: float, faktur: int}
     */
    private function penjualanBulanIni(): array
    {
        $awal = Carbon::now()->startOfMonth();
        $akhir = Carbon::now()->endOfMonth();

        $faktur = Penjualan::whereBetween('tanggal_penjualan', [$awal, $akhir]);

        return [
            'faktur' => (clone $faktur)->count(),
            'nilai' => (float) (clone $faktur)->sum('total_harga'),
            'jumlah' => (float) DB::table('detail_penjualan')
                ->join('penjualan', 'penjualan.id', '=', 'detail_penjualan.penjualan_id')
                ->whereBetween('penjualan.tanggal_penjualan', [$awal, $akhir])
                ->sum('detail_penjualan.jumlah'),
        ];
    }

    /**
     * Penjualan 12 bulan terakhir, dalam unit.
     *
     * Bulan tanpa transaksi tetap ditampilkan bernilai nol supaya bentuk
     * grafiknya jujur — deret yang bolong akan menyesatkan saat dibaca.
     *
     * @return list<array{label: string, jumlah: float}>
     */
    private function trenPenjualan(): array
    {
        $mulai = Carbon::now()->startOfMonth()->subMonths(11);

        $data = DB::table('detail_penjualan')
            ->join('penjualan', 'penjualan.id', '=', 'detail_penjualan.penjualan_id')
            ->where('penjualan.tanggal_penjualan', '>=', $mulai)
            ->selectRaw("DATE_FORMAT(penjualan.tanggal_penjualan, '%Y-%m') AS bulan, SUM(detail_penjualan.jumlah) AS jumlah")
            ->groupBy('bulan')
            ->pluck('jumlah', 'bulan');

        $hasil = [];

        for ($i = 0; $i < 12; $i++) {
            $bulan = $mulai->copy()->addMonths($i);
            $kunci = $bulan->format('Y-m');

            $hasil[] = [
                'label' => $bulan->translatedFormat('M Y'),
                'jumlah' => (float) ($data[$kunci] ?? 0),
            ];
        }

        return $hasil;
    }

    private function barangMenipis()
    {
        return Barang::aktif()
            ->with('kategori:id,nama_kategori')
            ->whereColumn('stok_tersedia', '<=', 'stok_minimum')
            ->orderByRaw('stok_tersedia - stok_minimum')
            ->limit(8)
            ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'stok_tersedia', 'stok_minimum', 'kategori_id']);
    }
}
