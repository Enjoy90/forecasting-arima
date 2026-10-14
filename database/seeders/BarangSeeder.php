<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class BarangSeeder extends Seeder
{
    /**
     * Bahan baku mengikuti tabel bahan CV. Pande Sejahtera (nama, supplier,
     * satuan). Harga beli dan lead time masih ASUMSI dan wajib dikonfirmasi ke
     * perusahaan. Stok di sini = stok awal Januari 2023 (sekitar kebutuhan
     * satu minggu), karena DataTransaksiSeeder memutar ulang pembelian dan
     * pemakaian sejak bulan itu. Stok minimum = setengah pemakaian bulanan 2026.
     *
     * Lead time terbesar ada pada plat (14 hari), inilah yang menentukan
     * L_beli pada perhitungan waktu tunggu total.
     */
    public function run(): void
    {
        $kat = Kategori::pluck('id', 'kode_kategori');
        $sup = Supplier::pluck('id', 'kode_supplier');

        // -----------------------------------------------------------
        // BAHAN BAKU
        // [kode, nama, satuan, harga_beli, stok, stok_min, lead_time, kategori, supplier]
        // -----------------------------------------------------------
        $bahanBaku = [
            ['BB-01', 'Plat 1,2 ml',              'Kg',    14500,   900,  2600, 14, 'KTG-01', 'SUP-01'],
            ['BB-02', 'Plat 1,6 ml',              'Kg',    14800,  1100,  2500, 14, 'KTG-01', 'SUP-02'],
            ['BB-03', 'Handle Biru',              'Kg',    26000,   170,   380,  7, 'KTG-02', 'SUP-03'],
            ['BB-04', 'Handle Hitam',             'Kg',    25000,   180,   540,  7, 'KTG-02', 'SUP-03'],
            ['BB-05', 'Sekrup Gypsum 5/8"',       'Box',    6000,   115,   300,  7, 'KTG-01', 'SUP-04'],
            ['BB-06', 'Sekrup Gypsum 3/4"',       'Box',    7000,   170,   460,  7, 'KTG-01', 'SUP-04'],
            ['BB-07', 'Stiker REES Q',            'Pcs',     180,  1400,  3100, 10, 'KTG-04', 'SUP-05'],
            ['BB-08', 'Stiker Crocodille',        'Pcs',     180,  1500,  4500, 10, 'KTG-04', 'SUP-05'],
            ['BB-09', 'Plastik',                  'Pack',  42000,    60,   150,  7, 'KTG-04', 'SUP-06'],
            ['BB-10', 'Karung',                   'Roll', 480000,     3,     6,  7, 'KTG-04', 'SUP-07'],
            ['BB-11', 'Powder Coating Biru',      'Kg',    58000,    70,   165,  7, 'KTG-03', 'SUP-08'],
            ['BB-12', 'Powder Coating Hammerton', 'Kg',    62000,    80,   235,  7, 'KTG-03', 'SUP-08'],
            ['BB-13', 'Kayu',                     'Pcs',    4800,  2900,  7800, 10, 'KTG-02', 'SUP-09'],
            ['BB-14', 'Varnish',                  'Liter', 42000,    60,   160,  7, 'KTG-03', 'SUP-10'],
        ];

        foreach ($bahanBaku as [$kode, $nama, $satuan, $harga, $stok, $min, $lead, $kKat, $kSup]) {
            Barang::updateOrCreate(
                ['kode_barang' => $kode],
                [
                    'nama_barang' => $nama,
                    'jenis_barang' => Barang::JENIS_BAHAN_BAKU,
                    'kategori_id' => $kat[$kKat],
                    'supplier_id' => $sup[$kSup],
                    'satuan' => $satuan,
                    'harga_beli' => $harga,
                    'harga_jual' => 0,
                    'stok_tersedia' => $stok,
                    'stok_minimum' => $min,
                    'lead_time_hari' => $lead,
                    'service_level' => 95,
                    'is_diramalkan' => false,
                    'is_aktif' => true,
                ]
            );
        }

        // -----------------------------------------------------------
        // SETENGAH JADI - output tahapan antara. Handle kayu dipakai
        // bersama; kepala dan rakitan dibedakan per produk karena bentuk
        // (papak/lancip) dan bahannya (Crocodille/Rees Q) berbeda.
        // [kode, nama, stok, stok_min]
        // -----------------------------------------------------------
        $setengahJadi = [
            ['SJ-01', 'Handle Sekop Kayu',                    3000, 1000],
            ['SJ-02', 'Kepala Papak Crocodille Mentah',        600,  200],
            ['SJ-03', 'Kepala Lancip Crocodille Mentah',       300,  100],
            ['SJ-04', 'Kepala Papak Rees Q Mentah',            450,  150],
            ['SJ-05', 'Kepala Lancip Rees Q Mentah',           300,  100],
            ['SJ-06', 'Kepala Papak Crocodille Ter-coating',   500,  150],
            ['SJ-07', 'Kepala Lancip Crocodille Ter-coating',  250,   80],
            ['SJ-08', 'Kepala Papak Rees Q Ter-coating',       400,  120],
            ['SJ-09', 'Kepala Lancip Rees Q Ter-coating',      250,   80],
            ['SJ-10', 'Rakitan Sekop Papak Crocodille',        400,  120],
            ['SJ-11', 'Rakitan Sekop Lancip Crocodille',       200,   60],
            ['SJ-12', 'Rakitan Sekop Papak Rees Q',            300,  100],
            ['SJ-13', 'Rakitan Sekop Lancip Rees Q',           200,   60],
        ];

        foreach ($setengahJadi as [$kode, $nama, $stok, $min]) {
            Barang::updateOrCreate(
                ['kode_barang' => $kode],
                [
                    'nama_barang' => $nama,
                    'jenis_barang' => Barang::JENIS_SETENGAH_JADI,
                    'kategori_id' => $kat['KTG-05'],
                    'supplier_id' => null,
                    'satuan' => 'Pcs',
                    'harga_beli' => 0,
                    'harga_jual' => 0,
                    'stok_tersedia' => $stok,
                    'stok_minimum' => $min,
                    'lead_time_hari' => 0,
                    'service_level' => 95,
                    'is_diramalkan' => false,
                    'is_aktif' => true,
                ]
            );
        }

        // -----------------------------------------------------------
        // BARANG JADI - penjualannya diramalkan ARIMA per produk.
        // Harga jual = harga nota terakhir (Sep 2026). HPP masih ASUMSI dari
        // perkiraan biaya bahan; Rees Q lebih mahal karena platnya 1,6 ml.
        // [kode, nama, hpp, harga_jual, stok, stok_min]
        // -----------------------------------------------------------
        $barangJadi = [
            ['BJ-01', 'Sekop Papak Crocodille GG.KY', 25000, 34000, 3000, 1500],
            ['BJ-02', 'Sekop Lancip Crocodille GG.KY', 25000, 34125,  900,  450],
            ['BJ-03', 'Sekop Papak Rees Q GG.KY',      28000, 34125, 2000, 1000],
            ['BJ-04', 'Sekop Lancip Rees Q GG.KY',     28000, 34125,  900,  450],
        ];

        foreach ($barangJadi as [$kode, $nama, $hpp, $hargaJual, $stok, $min]) {
            Barang::updateOrCreate(
                ['kode_barang' => $kode],
                [
                    'nama_barang' => $nama,
                    'jenis_barang' => Barang::JENIS_BARANG_JADI,
                    'kategori_id' => $kat['KTG-06'],
                    'supplier_id' => null,
                    'satuan' => 'Pcs',
                    'harga_beli' => $hpp,
                    'harga_jual' => $hargaJual,
                    'stok_tersedia' => $stok,
                    'stok_minimum' => $min,
                    'lead_time_hari' => 14,    // mengikuti lead time bahan terlama
                    'service_level' => 95,
                    'is_diramalkan' => true,
                    'is_aktif' => true,
                ]
            );
        }
    }
}
