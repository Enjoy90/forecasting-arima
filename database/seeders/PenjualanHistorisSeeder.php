<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Pelanggan;
use App\Models\Penjualan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PenjualanHistorisSeeder extends Seeder
{
    /**
     * Data penjualan 36 bulan (Sep 2023 - Agu 2026) untuk uji coba ARIMA.
     *
     * Deret sengaja dibentuk dari tiga komponen supaya Box-Jenkins punya
     * sesuatu untuk ditemukan:
     *   1. TREN     : naik 1,5% per bulan
     *   2. MUSIMAN  : puncak Okt-Jan (musim hujan/tanam), lembah Jun-Agu
     *   3. ACAK     : noise +/- 7%
     *
     * PENTING: memakai benih acak tetap (mt_srand) supaya kedua anggota tim
     * mendapat data yang PERSIS SAMA. Kalau datanya berbeda, hasil peramalan
     * dan simulasi tidak bisa dibandingkan satu sama lain.
     *
     * Catatan: seeder ini TIDAK membuat mutasi stok, karena data ini mewakili
     * riwayat masa lalu yang diimpor, bukan transaksi yang berjalan di sistem.
     * Stok berjalan sudah diisi langsung oleh BarangSeeder.
     */
    public function run(): void
    {
        mt_srand(20260918);

        $barang = Barang::where('kode_barang', 'BJ-01')->firstOrFail();
        $pelangganIds = Pelanggan::pluck('id')->all();
        $userId = User::where('role', User::ROLE_ADMIN)->value('id');

        // indeks musiman per bulan (1 = Januari)
        $musiman = [
            1 => 1.15, 2 => 1.05, 3 => 0.98, 4 => 0.92,
            5 => 0.88, 6 => 0.85, 7 => 0.86, 8 => 0.90,
            9 => 1.00, 10 => 1.18, 11 => 1.22, 12 => 1.20,
        ];

        $basis = 1000;
        $mulai = Carbon::create(2023, 9, 1);
        $harga = (float) $barang->harga_jual;

        DB::transaction(function () use ($barang, $pelangganIds, $userId, $musiman, $basis, $mulai, $harga) {

            for ($t = 0; $t < 36; $t++) {
                $bulanIni = $mulai->copy()->addMonths($t);

                $tren = pow(1.015, $t);
                $indeks = $musiman[(int) $bulanIni->month];
                $noise = 1 + (mt_rand(-70, 70) / 1000);

                $totalBulan = (int) round($basis * $tren * $indeks * $noise);

                // pecah menjadi 4-7 faktur dalam bulan tersebut
                $jumlahFaktur = mt_rand(4, 7);
                $sisa = $totalBulan;

                for ($f = 1; $f <= $jumlahFaktur; $f++) {
                    $qty = $f === $jumlahFaktur
                        ? $sisa
                        : (int) round($sisa / ($jumlahFaktur - $f + 1) * (mt_rand(70, 130) / 100));

                    $qty = max(1, min($qty, $sisa - ($jumlahFaktur - $f)));
                    $sisa -= $qty;

                    $tanggal = $bulanIni->copy()->addDays(mt_rand(0, $bulanIni->daysInMonth - 1));
                    $subtotal = $qty * $harga;

                    $penjualan = Penjualan::create([
                        'no_faktur' => sprintf('FJ-%s-%03d', $bulanIni->format('Ym'), $f),
                        'tanggal_penjualan' => $tanggal,
                        'pelanggan_id' => $pelangganIds[array_rand($pelangganIds)],
                        'user_id' => $userId,
                        'total_harga' => $subtotal,
                        'sumber_data' => 'import',
                        'keterangan' => 'Data historis hasil import',
                    ]);

                    DetailPenjualan::create([
                        'penjualan_id' => $penjualan->id,
                        'barang_id' => $barang->id,
                        'jumlah' => $qty,
                        'harga_satuan' => $harga,
                        'subtotal' => $subtotal,
                    ]);
                }
            }
        });
    }
}
