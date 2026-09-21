<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\DetailBom;
use App\Models\TahapanProduksi;
use Illuminate\Database\Seeder;

class BomSeeder extends Seeder
{
    /**
     * Komposisi bahan tiap tahapan produksi sekop.
     *
     * Rantai BOM (dibaca dari bawah ke atas saat BOM explosion):
     *   BOM-05 Sekop jadi        <- Sekop rakitan + kemasan
     *   BOM-04 Sekop rakitan     <- Kepala ter-coating + Handle + paku keling
     *   BOM-03 Kepala ter-coating<- Kepala mentah + cat
     *   BOM-02 Handle            <- Kayu gagang
     *   BOM-01 Kepala mentah     <- Plat besi + kawat las
     *
     * Angka pemakaian dan persen susut masih ASUMSI.
     */
    public function run(): void
    {
        $barang = Barang::pluck('id', 'kode_barang');
        $tahapan = TahapanProduksi::pluck('id', 'kode_tahapan');

        $resep = [
            [
                'kode' => 'BOM-01',
                'nama' => 'Kepala Sekop Mentah',
                'output' => 'SJ-01',
                'tahapan' => 'TP-01',
                'jumlah_output' => 12,   // 1 lembar plat menghasilkan 12 kepala
                'keterangan' => 'Satu lembar plat besi dipotong menjadi 12 kepala sekop',
                'komponen' => [
                    // [kode barang, jumlah, satuan, persen susut]
                    ['BB-01', 1,    'Lembar', 5],
                    ['BB-02', 0.15, 'Kg',     3],
                ],
            ],
            [
                'kode' => 'BOM-02',
                'nama' => 'Handle Sekop Kayu',
                'output' => 'SJ-03',
                'tahapan' => 'TP-02',
                'jumlah_output' => 1,
                'keterangan' => 'Kayu gagang dipotong, diserut, dan diamplas',
                'komponen' => [
                    ['BB-03', 1, 'Batang', 3],
                ],
            ],
            [
                'kode' => 'BOM-03',
                'nama' => 'Kepala Sekop Ter-coating',
                'output' => 'SJ-02',
                'tahapan' => 'TP-03',
                'jumlah_output' => 1,
                'keterangan' => 'Satu liter cat kurang lebih cukup untuk 40 kepala',
                'komponen' => [
                    ['SJ-01', 1,     'Pcs',   2],
                    ['BB-05', 0.025, 'Liter', 5],
                ],
            ],
            [
                'kode' => 'BOM-04',
                'nama' => 'Sekop Rakitan',
                'output' => 'SJ-04',
                'tahapan' => 'TP-04',
                'jumlah_output' => 1,
                'keterangan' => 'Kepala dipasang ke gagang, dikunci tiga paku keling',
                'komponen' => [
                    ['SJ-02', 1, 'Pcs', 1],
                    ['SJ-03', 1, 'Pcs', 1],
                    ['BB-06', 3, 'Pcs', 2],
                ],
            ],
            [
                'kode' => 'BOM-05',
                'nama' => 'Sekop Tanah Gagang Kayu',
                'output' => 'BJ-01',
                'tahapan' => 'TP-05',
                'jumlah_output' => 1,
                'keterangan' => 'Pembungkusan plastik dan pemasangan label',
                'komponen' => [
                    ['SJ-04', 1, 'Pcs', 1],
                    ['BB-07', 1, 'Set', 2],
                ],
            ],
        ];

        foreach ($resep as $r) {
            $bom = Bom::updateOrCreate(
                ['kode_bom' => $r['kode']],
                [
                    'nama_bom' => $r['nama'],
                    'barang_id' => $barang[$r['output']],
                    'tahapan_id' => $tahapan[$r['tahapan']],
                    'jumlah_output' => $r['jumlah_output'],
                    'is_aktif' => true,
                    'keterangan' => $r['keterangan'],
                ]
            );

            foreach ($r['komponen'] as [$kode, $jumlah, $satuan, $susut]) {
                DetailBom::updateOrCreate(
                    ['bom_id' => $bom->id, 'barang_id' => $barang[$kode]],
                    [
                        'jumlah_kebutuhan' => $jumlah,
                        'satuan' => $satuan,
                        'persen_susut' => $susut,
                    ]
                );
            }
        }
    }
}
