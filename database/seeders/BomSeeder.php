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
     * Komposisi bahan tiap tahapan produksi, per produk sekop.
     *
     * Rantai BOM tiap produk (dibaca dari bawah ke atas saat BOM explosion):
     *   Sekop jadi         <- rakitan + stiker + plastik + karung (per lusin)
     *   Rakitan            <- kepala ter-coating + handle kayu + handle + sekrup
     *   Kepala ter-coating <- kepala mentah + powder coating
     *   Kepala mentah      <- plat
     *   Handle kayu        <- kayu + varnish (dipakai bersama keempat produk)
     *
     * Bahan per merek mengikuti tabel resep perusahaan:
     *   Crocodille: plat 1,2 ml, coating hammerton, handle hitam, stiker Crocodille
     *   REES Q    : plat 1,6 ml, coating biru, handle biru, stiker REES Q
     *
     * Berat plat, handle, powder coating, varnish, dan isi satu roll karung
     * (100 karung, satu karung per lusin) adalah ASUMSI, wajib dikonfirmasi
     * ke perusahaan. Persen susut juga masih asumsi.
     */
    public function run(): void
    {
        $barang = Barang::pluck('id', 'kode_barang');
        $tahapan = TahapanProduksi::pluck('id', 'kode_tahapan');

        $resep = [[
            'kode' => 'BOM-01',
            'nama' => 'Handle Sekop Kayu',
            'output' => 'SJ-01',
            'tahapan' => 'TP-02',
            'jumlah_output' => 1,
            'keterangan' => 'Kayu dipotong, diserut, diamplas, lalu divarnish',
            'komponen' => [
                // [kode barang, jumlah, satuan, persen susut]
                ['BB-13', 1,    'Pcs',   3],
                ['BB-14', 0.02, 'Liter', 5],
            ],
        ]];

        $merek = [
            'Crocodille' => ['plat' => ['BB-01', 0.55], 'coating' => 'BB-12', 'handle' => 'BB-04', 'stiker' => 'BB-08'],
            'Rees Q' => ['plat' => ['BB-02', 0.75], 'coating' => 'BB-11', 'handle' => 'BB-03', 'stiker' => 'BB-07'],
        ];

        // [produk jadi, bentuk, merek, kepala mentah, kepala ter-coating, rakitan]
        $produk = [
            ['BJ-01', 'Papak',  'Crocodille', 'SJ-02', 'SJ-06', 'SJ-10'],
            ['BJ-02', 'Lancip', 'Crocodille', 'SJ-03', 'SJ-07', 'SJ-11'],
            ['BJ-03', 'Papak',  'Rees Q',     'SJ-04', 'SJ-08', 'SJ-12'],
            ['BJ-04', 'Lancip', 'Rees Q',     'SJ-05', 'SJ-09', 'SJ-13'],
        ];

        foreach ($produk as $i => [$jadi, $bentuk, $namaMerek, $kepala, $coating, $rakitan]) {
            $m = $merek[$namaMerek];
            $label = "{$bentuk} {$namaMerek}";

            $resep[] = [
                'kode' => sprintf('BOM-%02d', 2 + $i),
                'nama' => "Kepala {$label} Mentah",
                'output' => $kepala,
                'tahapan' => 'TP-01',
                'jumlah_output' => 1,
                'keterangan' => "Plat dipotong dan dipress menjadi kepala {$label}",
                'komponen' => [
                    [$m['plat'][0], $m['plat'][1], 'Kg', 5],
                ],
            ];
            $resep[] = [
                'kode' => sprintf('BOM-%02d', 6 + $i),
                'nama' => "Kepala {$label} Ter-coating",
                'output' => $coating,
                'tahapan' => 'TP-03',
                'jumlah_output' => 1,
                'keterangan' => "Kepala {$label} dilapisi powder coating",
                'komponen' => [
                    [$kepala,       1,    'Pcs', 2],
                    [$m['coating'], 0.05, 'Kg',  5],
                ],
            ];
            $resep[] = [
                'kode' => sprintf('BOM-%02d', 10 + $i),
                'nama' => "Rakitan Sekop {$label}",
                'output' => $rakitan,
                'tahapan' => 'TP-04',
                'jumlah_output' => 1,
                'keterangan' => 'Kepala dipasang ke gagang kayu dengan handle, dikunci 2 sekrup 5/8 dan 3 sekrup 3/4',
                'komponen' => [
                    [$coating,     1,    'Pcs', 1],
                    ['SJ-01',      1,    'Pcs', 1],
                    [$m['handle'], 0.12, 'Kg',  2],
                    ['BB-05',      0.04, 'Box', 2],
                    ['BB-06',      0.06, 'Box', 2],
                ],
            ];
            $resep[] = [
                'kode' => sprintf('BOM-%02d', 14 + $i),
                'nama' => "Sekop {$label} GG.KY",
                'output' => $jadi,
                'tahapan' => 'TP-05',
                'jumlah_output' => 12,   // satu lusin dikemas dan dimasukkan satu karung
                'keterangan' => 'Pemasangan stiker, pembungkusan plastik, dan pengemasan satu lusin ke dalam karung',
                'komponen' => [
                    [$rakitan,     12,   'Pcs',  1],
                    [$m['stiker'], 12,   'Pcs',  2],
                    ['BB-09',      0.24, 'Pack', 2],
                    ['BB-10',      0.01, 'Roll', 0],
                ],
            ];
        }

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
