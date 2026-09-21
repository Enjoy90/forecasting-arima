<?php

namespace Database\Seeders;

use App\Models\TahapanProduksi;
use Illuminate\Database\Seeder;

class TahapanProduksiSeeder extends Seeder
{
    /**
     * RINCIAN WAKTU TUNGGU OPERASIONAL PABRIK (revisi sidang proposal).
     *
     * Angka waktu proses dan kapasitas di bawah ini masih ASUMSI.
     * Wajib dikonfirmasi ke CV. Pande Sejahtera sebelum dipakai di laporan akhir.
     *
     * Total waktu produksi dengan angka ini = 1+1+2+1+1 = 6 hari.
     *
     * Catatan: di lapangan Produksi Handle biasanya dikerjakan PARALEL dengan
     * Produksi Kepala, sehingga nyatanya bisa 5 hari. Sistem menjumlahkan
     * seluruh tahapan secara berurutan -- sengaja konservatif, agar rencana
     * stok cenderung aman daripada terlambat.
     */
    public function run(): void
    {
        $tahapan = [
            ['TP-01', 'Produksi Kepala',   1, 1, 150, 'Potong plat besi, press bentuk kepala, las penguat'],
            ['TP-02', 'Produksi Handle',   2, 1, 200, 'Potong kayu gagang, serut, amplas halus'],
            ['TP-03', 'Proses Coating',    3, 2, 200, 'Pengecatan kepala sekop + waktu kering 1 hari'],
            ['TP-04', 'Perakitan Sekop',   4, 1, 180, 'Pasang kepala ke gagang, kunci dengan paku keling'],
            ['TP-05', 'Proses Pengemasan', 5, 1, 300, 'Bungkus plastik, pasang label, siap kirim'],
        ];

        foreach ($tahapan as [$kode, $nama, $urutan, $waktu, $kapasitas, $deskripsi]) {
            TahapanProduksi::updateOrCreate(
                ['kode_tahapan' => $kode],
                [
                    'nama_tahapan' => $nama,
                    'urutan' => $urutan,
                    'waktu_proses_hari' => $waktu,
                    'kapasitas_per_hari' => $kapasitas,
                    'deskripsi' => $deskripsi,
                    'is_aktif' => true,
                ]
            );
        }
    }
}
