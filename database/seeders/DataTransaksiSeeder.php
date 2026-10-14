<?php

namespace Database\Seeders;

use App\Exceptions\StokTidakCukupException;
use App\Imports\PembelianImport;
use App\Imports\PenjualanImport;
use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\MutasiStok;
use App\Models\Pembelian;
use App\Models\User;
use App\Services\Arima\TimeSeriesBuilder;
use App\Services\Produksi\BomExploder;
use App\Services\Stok\StockMutator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class DataTransaksiSeeder extends Seeder
{
    /**
     * Penjualan dan pembelian Jan 2023 - Sep 2026 untuk empat produk sekop,
     * dari berkas di database/data. Ini DATA SIMULASI yang disusun dari
     * kalibrasi nota CV. Pande Sejahtera, bukan penjualan asli perusahaan.
     *
     * Data masuk lewat kelas Import yang sama dengan menu Import. Sesudah itu
     * order pembelian diterima dan pemakaian bahan untuk produksi dicatat
     * bulan demi bulan, supaya stok bahan baku bergerak sesuai tanggalnya.
     * Pemakaian bahan dihitung dari penjualan bulan itu lewat BOM tiap produk.
     */
    public function run(): void
    {
        Auth::login(User::where('role', User::ROLE_ADMIN)->firstOrFail());

        $this->impor(new PenjualanImport(), database_path('data/penjualan-jan2023-sep2026.xlsx'));
        $this->impor(new PembelianImport(), database_path('data/pembelian-jan2023-sep2026.xlsx'));

        $produk = Barang::where('jenis_barang', Barang::JENIS_BARANG_JADI)->get();

        // Pemakaian bahan baku per 1 unit tiap produk: [produk_id][bahan_id] => jumlah
        $rasio = [];
        foreach ($produk as $p) {
            foreach ((new BomExploder())->ledakkan($p, 1200.0)['bahan_baku'] as $baris) {
                $rasio[$p->id][$baris['barang_id']] = $baris['jumlah'] / 1200;
            }
        }

        $penjualanBulanan = DetailPenjualan::query()
            ->join('penjualan', 'penjualan.id', '=', 'detail_penjualan.penjualan_id')
            ->selectRaw("DATE_FORMAT(penjualan.tanggal_penjualan, '%Y-%m') as bln, detail_penjualan.barang_id, SUM(detail_penjualan.jumlah) as total")
            ->groupBy('bln', 'detail_penjualan.barang_id')
            ->get()
            ->groupBy('bln');

        $orderBulanan = Pembelian::with('detail.barang')
            ->orderBy('tanggal_pembelian')
            ->orderBy('no_pembelian')
            ->get()
            ->groupBy(fn (Pembelian $p) => $p->tanggal_pembelian->format('Y-m'));

        $bulan = $penjualanBulanan->keys()->merge($orderBulanan->keys())->unique()->sort()->values();
        $bahan = Barang::where('jenis_barang', Barang::JENIS_BAHAN_BAKU)->orderBy('kode_barang')->get();
        $mutator = app(StockMutator::class);

        DB::transaction(function () use ($bulan, $orderBulanan, $penjualanBulanan, $rasio, $bahan, $mutator) {
            foreach ($bulan as $bln) {
                foreach ($orderBulanan[$bln] ?? [] as $order) {
                    foreach ($order->detail as $baris) {
                        $mutator->catat($baris->barang, MutasiStok::MASUK, 'pembelian', (float) $baris->jumlah, $order->tanggal_pembelian, $order, "Penerimaan order {$order->no_pembelian}");
                    }
                    $order->update(['status' => 'diterima', 'tanggal_terima' => $order->tanggal_pembelian]);
                }

                $tanggal = Carbon::parse($bln.'-25');
                while ($tanggal->isWeekend()) {
                    $tanggal->subDay();
                }

                foreach ($bahan as $b) {
                    $jumlah = 0.0;
                    foreach ($penjualanBulanan[$bln] ?? [] as $jual) {
                        $jumlah += (float) $jual->total * ($rasio[$jual->barang_id][$b->id] ?? 0.0);
                    }
                    if ($jumlah <= 0.0001) {
                        continue;
                    }

                    $ket = "Pemakaian produksi {$bln}";
                    try {
                        $mutator->catat($b, MutasiStok::KELUAR, 'produksi', $jumlah, $tanggal, null, $ket);
                    } catch (StokTidakCukupException) {
                        $tersedia = $mutator->stokAwal($b);
                        if ($tersedia > 0.0001) {
                            $mutator->catat($b, MutasiStok::KELUAR, 'produksi', $tersedia, $tanggal, null, $ket.' (stok habis)');
                        }
                    }
                }
            }
        });

        foreach (Barang::diramalkan()->get() as $p) {
            (new TimeSeriesBuilder())->bangun($p);
        }
    }

    private function impor(PenjualanImport|PembelianImport $import, string $path): void
    {
        Excel::import($import, $path);

        if (! $import->berhasil()) {
            throw new RuntimeException('Impor '.basename($path).' gagal: '.implode(' ', $import->galat));
        }
    }
}
