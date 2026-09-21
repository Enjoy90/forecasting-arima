<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------------------------------------
        // KATEGORI
        // -----------------------------------------------------------
        $kategori = [
            ['KTG-01', 'Bahan Logam',            'Plat besi, pipa, kawat las, paku keling'],
            ['KTG-02', 'Bahan Kayu',             'Kayu gagang sekop'],
            ['KTG-03', 'Bahan Finishing',        'Cat coating dan bahan pelapis'],
            ['KTG-04', 'Bahan Kemasan',          'Plastik pembungkus dan label'],
            ['KTG-05', 'Komponen Setengah Jadi', 'Hasil antar tahapan produksi'],
            ['KTG-06', 'Produk Jadi',            'Sekop siap jual'],
        ];

        foreach ($kategori as [$kode, $nama, $ket]) {
            Kategori::updateOrCreate(
                ['kode_kategori' => $kode],
                ['nama_kategori' => $nama, 'keterangan' => $ket]
            );
        }

        // -----------------------------------------------------------
        // SUPPLIER
        // Lead time di sini adalah ASUMSI. Ganti dengan data asli
        // CV. Pande Sejahtera bila sudah tersedia.
        // -----------------------------------------------------------
        $supplier = [
            ['SUP-01', 'UD Baja Perkasa',        '031-8812340', 'Jl. Raya Gresik No. 45, Surabaya', 14],
            ['SUP-02', 'CV Kayu Jati Makmur',    '0331-556677', 'Jl. Kalimantan No. 12, Jember',    10],
            ['SUP-03', 'Toko Cat Warna Indah',   '031-7745521', 'Jl. Kertajaya No. 88, Surabaya',    7],
            ['SUP-04', 'UD Plastik Sejahtera',   '031-5567123', 'Jl. Margomulyo No. 7, Surabaya',    7],
            ['SUP-05', 'Toko Besi Jaya Abadi',   '0321-445566', 'Jl. Mayjen Sungkono No. 3, Mojokerto', 14],
        ];

        foreach ($supplier as [$kode, $nama, $telp, $alamat, $lead]) {
            Supplier::updateOrCreate(
                ['kode_supplier' => $kode],
                [
                    'nama_supplier' => $nama,
                    'telepon' => $telp,
                    'alamat' => $alamat,
                    'lead_time_default' => $lead,
                    'is_aktif' => true,
                ]
            );
        }

        // -----------------------------------------------------------
        // PELANGGAN
        // -----------------------------------------------------------
        $pelanggan = [
            ['PLG-01', 'Toko Bangunan Sumber Rejeki', 'toko',        'Mojokerto'],
            ['PLG-02', 'UD Tani Makmur',              'distributor', 'Jombang'],
            ['PLG-03', 'Toko Besi Anugerah',          'toko',        'Sidoarjo'],
            ['PLG-04', 'CV Agro Sentosa',             'distributor', 'Malang'],
            ['PLG-05', 'Toko Bangunan Jaya Abadi',    'toko',        'Surabaya'],
            ['PLG-06', 'Dinas Pertanian Kabupaten',   'instansi',    'Mojokerto'],
            ['PLG-07', 'Toko Tani Subur',             'toko',        'Kediri'],
            ['PLG-08', 'UD Mitra Bangunan',           'distributor', 'Pasuruan'],
        ];

        foreach ($pelanggan as [$kode, $nama, $jenis, $kota]) {
            Pelanggan::updateOrCreate(
                ['kode_pelanggan' => $kode],
                [
                    'nama_pelanggan' => $nama,
                    'jenis' => $jenis,
                    'kota' => $kota,
                    'is_aktif' => true,
                ]
            );
        }
    }
}
