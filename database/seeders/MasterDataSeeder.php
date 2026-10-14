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
            ['KTG-01', 'Bahan Logam',            'Plat besi dan sekrup'],
            ['KTG-02', 'Bahan Kayu',             'Kayu gagang dan handle sekop'],
            ['KTG-03', 'Bahan Finishing',        'Powder coating dan varnish'],
            ['KTG-04', 'Bahan Kemasan',          'Plastik, karung, dan stiker'],
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
        // Nama sesuai tabel bahan CV. Pande Sejahtera. Alamat, telepon,
        // dan lead time belum tercantum di tabel itu, jadi masih ASUMSI
        // dan perlu dikonfirmasi ke perusahaan.
        // -----------------------------------------------------------
        $supplier = [
            ['SUP-01', 'PT Cahaya Mandiri Bajamas',     '031-8812340', 'Jl. Raya Gresik No. 45, Surabaya',      14],
            ['SUP-02', 'CV Samudera Barokah Baja',      '031-8812555', 'Jl. Margomulyo No. 18, Surabaya',       14],
            ['SUP-03', 'Pak Solah',                     '0321-445566', 'Jl. Mayjen Sungkono No. 3, Mojokerto',   7],
            ['SUP-04', 'CV. Sentral Fastindo',          '031-5998877', 'Jl. Kedungdoro No. 21, Surabaya',        7],
            ['SUP-05', 'Perc. Tjoeng Sie',              '031-5344120', 'Jl. Kembang Jepun No. 9, Surabaya',     10],
            ['SUP-06', 'Omega Jaya Plastik',            '031-5567123', 'Jl. Margomulyo No. 7, Surabaya',         7],
            ['SUP-07', 'UD Jaya Plastik',               '031-5567200', 'Jl. Margomulyo No. 11, Surabaya',        7],
            ['SUP-08', 'PT. Lotus Spektrum Jaya Abadi', '031-7745521', 'Jl. Kertajaya No. 88, Surabaya',         7],
            ['SUP-09', 'Albasia Sumber Jaya',           '0331-556677', 'Jl. Kalimantan No. 12, Jember',         10],
            ['SUP-10', 'PT. Pertiwimas Adi Kencana',    '031-7312288', 'Jl. Rungkut Industri No. 5, Surabaya',   7],
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
