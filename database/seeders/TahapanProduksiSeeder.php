<?php

namespace Database\Seeders;

use App\Models\TahapanProduksi;
use Illuminate\Database\Seeder;

class TahapanProduksiSeeder extends Seeder
{
    /**
     * RINCIAN WAKTU TUNGGU OPERASIONAL PABRIK.
     *
     * Waktu proses dan kapasitas dari wawancara bagian produksi CV. Pande
     * Sejahtera (14 sampai 15 Oktober 2026, docs/07). Dari nol: hari 1 cetak
     * kepala dan handle berjalan bersamaan, hari 2 coating (dioven), hari 3
     * rakit lalu langsung dikemas di hari yang sama. Jadi pengemasan diberi
     * waktu proses 0 hari.
     *
     * Satuan kapasitas mengikuti barang hasil tahapannya: kepala, handle, dan
     * rakitan dalam pcs, sekop jadi dalam dz. Kapasitas coating 780 pcs adalah
     * nilai tengah dari 720 sampai 840 pcs per hari, gabungan semua jenis.
     */
    public function run(): void
    {
        $tahapan = [
            ['TP-01', 'Produksi Kepala',   1, 1, 1200, 'Potong plat, cetak kepala papak atau lancip (1.200 pcs per hari per jenis)'],
            ['TP-02', 'Produksi Handle',   2, 1, 1200, 'Potong kayu gagang, serut, amplas, varnish (1.200 pcs per hari)'],
            ['TP-03', 'Proses Coating',    3, 1,  780, 'Powder coating dan oven, Hammerton dan biru bergantian (720 sampai 840 pcs per hari)'],
            ['TP-04', 'Perakitan Sekop',   4, 1,  960, 'Pasang kepala ke gagang dan handle, kunci dengan sekrup (80 dz = 960 pcs per hari)'],
            ['TP-05', 'Proses Pengemasan', 5, 0,   80, 'Stiker, plastik, kemas per lusin ke karung, di hari yang sama dengan perakitan (dz)'],
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
