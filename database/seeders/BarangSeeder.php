<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class BarangSeeder extends Seeder
{
    /**
     * Harga dan lead time di bawah ini masih ASUMSI, disusun agar masuk akal
     * untuk CV manufaktur sekop skala kecil. Wajib dikonfirmasi ke perusahaan.
     *
     * Lead time terbesar ada pada Plat Besi (14 hari) -- inilah yang menentukan
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
            ['BB-01', 'Plat Besi 1,2 mm',        'Lembar', 185000,  120,  40, 14, 'KTG-01', 'SUP-01'],
            ['BB-02', 'Kawat Las',               'Kg',      28000,   45,  15,  7, 'KTG-01', 'SUP-01'],
            ['BB-03', 'Kayu Gagang Sekop',       'Batang',  12000, 1400, 400, 10, 'KTG-02', 'SUP-02'],
            ['BB-04', 'Pipa Besi Gagang',        'Batang',  28000,  300, 100, 14, 'KTG-01', 'SUP-05'],
            ['BB-05', 'Cat Coating',             'Liter',   65000,   60,  20,  7, 'KTG-03', 'SUP-03'],
            ['BB-06', 'Paku Keling',             'Pcs',       500, 5200, 1500, 5, 'KTG-01', 'SUP-05'],
            ['BB-07', 'Plastik Kemasan + Label', 'Set',      1500, 1800, 500,  7, 'KTG-04', 'SUP-04'],
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
        // SETENGAH JADI - output tahapan antara
        // -----------------------------------------------------------
        $setengahJadi = [
            ['SJ-01', 'Kepala Sekop Mentah',      'Pcs', 320,  80],
            ['SJ-02', 'Kepala Sekop Ter-coating', 'Pcs', 260,  80],
            ['SJ-03', 'Handle Sekop Kayu',        'Pcs', 410, 100],
            ['SJ-04', 'Sekop Rakitan',            'Pcs', 150,  50],
        ];

        foreach ($setengahJadi as [$kode, $nama, $satuan, $stok, $min]) {
            Barang::updateOrCreate(
                ['kode_barang' => $kode],
                [
                    'nama_barang' => $nama,
                    'jenis_barang' => Barang::JENIS_SETENGAH_JADI,
                    'kategori_id' => $kat['KTG-05'],
                    'supplier_id' => null,
                    'satuan' => $satuan,
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
        // BARANG JADI - yang penjualannya diramalkan dengan ARIMA
        // -----------------------------------------------------------
        Barang::updateOrCreate(
            ['kode_barang' => 'BJ-01'],
            [
                'nama_barang' => 'Sekop Tanah Gagang Kayu',
                'jenis_barang' => Barang::JENIS_BARANG_JADI,
                'kategori_id' => $kat['KTG-06'],
                'supplier_id' => null,
                'satuan' => 'Pcs',
                'harga_beli' => 55000,     // HPP
                'harga_jual' => 85000,
                'stok_tersedia' => 620,
                'stok_minimum' => 200,
                'lead_time_hari' => 14,    // mengikuti lead time bahan terlama
                'service_level' => 95,
                'is_diramalkan' => true,
                'is_aktif' => true,
            ]
        );
    }
}
