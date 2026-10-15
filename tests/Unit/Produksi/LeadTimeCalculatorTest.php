<?php

namespace Tests\Unit\Produksi;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\TahapanProduksi;
use App\Services\Produksi\LeadTimeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Uji waktu tunggu operasional pabrik (docs/01 §4, direvisi docs/07):
 * L_produksi = jalur kritis + (hari tahap terlama - 1) - tumpang tindih,
 * L_beli hanya dari bahan yang stoknya kurang.
 */
class LeadTimeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private LeadTimeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LeadTimeCalculator();
    }

    /**
     * Rantai dua level: bahan1 -> setengah jadi (t1) ; setengah jadi + bahan2 -> barang jadi (t2).
     *
     * @return array{barangJadi: Barang, bahanBaku1: Barang, bahanBaku2: Barang}
     */
    private function rantaiBom(float $stokBahan = 0): array
    {
        $t1 = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 100]);
        $t2 = TahapanProduksi::factory()->create(['waktu_proses_hari' => 2, 'kapasitas_per_hari' => 1000]);

        $barangJadi = Barang::factory()->barangJadi()->create();
        $setengahJadi = Barang::factory()->setengahJadi()->create();
        $bahanBaku1 = Barang::factory()->create(['lead_time_hari' => 14, 'stok_tersedia' => $stokBahan]);
        $bahanBaku2 = Barang::factory()->create(['lead_time_hari' => 5, 'stok_tersedia' => $stokBahan]);

        $bomAtas = Bom::create([
            'kode_bom' => 'BOM-JADI', 'nama_bom' => 'Perakitan', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $t2->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bomAtas->detail()->create(['barang_id' => $setengahJadi->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);
        $bomAtas->detail()->create(['barang_id' => $bahanBaku2->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $bomBawah = Bom::create([
            'kode_bom' => 'BOM-SJ', 'nama_bom' => 'Komponen', 'barang_id' => $setengahJadi->id,
            'tahapan_id' => $t1->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bomBawah->detail()->create(['barang_id' => $bahanBaku1->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        return compact('barangJadi', 'bahanBaku1', 'bahanBaku2');
    }

    public function test_lead_time_sesuai_hitungan_manual(): void
    {
        ['barangJadi' => $barangJadi] = $this->rantaiBom();

        // jalur kritis = t1 (1) + t2 (2) = 3 ; hari tahap terlama = ceil(50/100) = 1 ;
        // tumpang tindih t1 -> t2 tidak ada (kapasitas 100 < 1000) -> L_produksi = 3
        // stok bahan 0 -> L_beli = MAX(14, 5) = 14 -> L_total = 17
        $hasil = $this->calculator->hitung($barangJadi, 50);

        $this->assertEqualsWithDelta(14.0, $hasil['lead_time_pembelian_hari'], 1e-9);
        $this->assertEqualsWithDelta(3.0, $hasil['lead_time_produksi_hari'], 1e-9);
        $this->assertEqualsWithDelta(17.0, $hasil['lead_time_total_hari'], 1e-9);
    }

    public function test_tanggal_mundur_hari_kerja_untuk_produksi_dan_hari_kalender_untuk_beli_bahan(): void
    {
        ['barangJadi' => $barangJadi] = $this->rantaiBom();

        $hasil = $this->calculator->hitung($barangJadi, 50, Carbon::parse('2026-12-01'));

        // 2026-12-01 Selasa, mundur 3 hari kerja = Kamis 2026-11-26 ; pesan bahan 14 hari sebelumnya
        $this->assertSame('2026-11-26', $hasil['tanggal_mulai_produksi']->format('Y-m-d'));
        $this->assertSame('2026-11-12', $hasil['tanggal_pesan_bahan']->format('Y-m-d'));
    }

    public function test_tanggal_null_bila_awal_periode_tidak_diberikan(): void
    {
        ['barangJadi' => $barangJadi] = $this->rantaiBom();

        $hasil = $this->calculator->hitung($barangJadi, 50);

        $this->assertNull($hasil['tanggal_mulai_produksi']);
        $this->assertNull($hasil['tanggal_pesan_bahan']);
    }

    public function test_lead_time_meningkat_saat_target_melebihi_kapasitas_tahapan(): void
    {
        ['barangJadi' => $barangJadi] = $this->rantaiBom();

        // t1 kapasitas 100: target 250 -> 3 hari menurut kapasitas -> L_produksi = 3 + (3 - 1) = 5
        $hasilKecil = $this->calculator->hitung($barangJadi, 50);
        $hasilBesar = $this->calculator->hitung($barangJadi, 250);

        $this->assertEqualsWithDelta(5.0, $hasilBesar['lead_time_produksi_hari'], 1e-9);
        $this->assertGreaterThan($hasilKecil['lead_time_produksi_hari'], $hasilBesar['lead_time_produksi_hari']);
    }

    public function test_tanpa_beli_bahan_bila_stok_semua_bahan_cukup(): void
    {
        ['barangJadi' => $barangJadi] = $this->rantaiBom(stokBahan: 1000);

        $hasil = $this->calculator->hitung($barangJadi, 50);

        $this->assertEqualsWithDelta(0.0, $hasil['lead_time_pembelian_hari'], 1e-9);
        $this->assertEqualsWithDelta(3.0, $hasil['lead_time_total_hari'], 1e-9);
        $this->assertSame([], $hasil['bahan_kurang']);
    }

    public function test_lama_beli_hanya_dari_bahan_yang_kurang(): void
    {
        ['barangJadi' => $barangJadi, 'bahanBaku1' => $bahan1] = $this->rantaiBom();
        $bahan1->update(['stok_tersedia' => 1000]);

        $hasil = $this->calculator->hitung($barangJadi, 50);

        // bahan1 (14 hari) cukup, bahan2 (5 hari) kurang -> L_beli = 5
        $this->assertEqualsWithDelta(5.0, $hasil['lead_time_pembelian_hari'], 1e-9);
        $this->assertCount(1, $hasil['bahan_kurang']);
    }

    public function test_tanpa_periksa_stok_semua_bahan_dianggap_harus_dibeli(): void
    {
        ['barangJadi' => $barangJadi] = $this->rantaiBom(stokBahan: 1000);

        $hasil = $this->calculator->hitung($barangJadi, 50, null, [], false);

        $this->assertEqualsWithDelta(14.0, $hasil['lead_time_pembelian_hari'], 1e-9);
    }

    /**
     * Struktur pabrik (docs/07): kepala (1 hari, 1.200) -> coating (1 hari, 780) ->
     * rakit (1 hari, 960 pcs) <- handle (1 hari, 1.200) ; kemas (0 hari, 80 dz),
     * 1 dz = 12 rakitan. Handle dipakai bersama semua produk. Tanpa susut.
     *
     * @return array<string, Barang>
     */
    private function pabrikSekop(): array
    {
        $tp = fn ($w, $k) => TahapanProduksi::factory()->create(['waktu_proses_hari' => $w, 'kapasitas_per_hari' => $k]);
        $cetak = $tp(1, 1200);
        $tpHandle = $tp(1, 1200);
        $coating = $tp(1, 780);
        $rakit = $tp(1, 960);
        $kemas = $tp(0, 80);

        $plat = Barang::factory()->create(['lead_time_hari' => 14, 'stok_tersedia' => 0]);
        $kayu = Barang::factory()->create(['lead_time_hari' => 10, 'stok_tersedia' => 0]);
        $handle = Barang::factory()->setengahJadi()->create();
        $nomor = 0;
        $resep = function ($hasil, $tahapan, $output, array $komponen) use (&$nomor) {
            $bom = Bom::create([
                'kode_bom' => 'BOM-T'.(++$nomor), 'nama_bom' => 'Resep', 'barang_id' => $hasil->id,
                'tahapan_id' => $tahapan->id, 'jumlah_output' => $output, 'is_aktif' => true,
            ]);
            foreach ($komponen as [$barang, $jumlah]) {
                $bom->detail()->create(['barang_id' => $barang->id, 'jumlah_kebutuhan' => $jumlah, 'persen_susut' => 0, 'satuan' => 'Pcs']);
            }
        };
        $resep($handle, $tpHandle, 1, [[$kayu, 1]]);

        $produk = [];
        foreach (['papak_croc', 'lancip_croc', 'papak_rees', 'lancip_rees'] as $nama) {
            $kepala = Barang::factory()->setengahJadi()->create();
            $kepalaCoating = Barang::factory()->setengahJadi()->create();
            $rakitan = Barang::factory()->setengahJadi()->create();
            $jadi = Barang::factory()->barangJadi()->create(['satuan' => 'Dz']);
            $resep($kepala, $cetak, 1, [[$plat, 1]]);
            $resep($kepalaCoating, $coating, 1, [[$kepala, 1]]);
            $resep($rakitan, $rakit, 1, [[$kepalaCoating, 1], [$handle, 1]]);
            $resep($jadi, $kemas, 1, [[$rakitan, 12]]);
            $produk[$nama] = $jadi;
        }

        return $produk;
    }

    public function test_contoh_pabrik_2_dz_selesai_3_hari(): void
    {
        $p = $this->pabrikSekop();

        $hasil = $this->calculator->hitung($p['papak_croc'], 2);

        $this->assertEqualsWithDelta(3.0, $hasil['lead_time_produksi_hari'], 1e-9);
        $this->assertEqualsWithDelta(17.0, $hasil['lead_time_total_hari'], 1e-9);
        $this->assertSame(0, $hasil['tumpang_tindih_hari']);
    }

    public function test_contoh_pabrik_56_dz_empat_produk_selesai_3_hari(): void
    {
        $p = $this->pabrikSekop();

        // 672 pcs < 780: coating baru mulai hari 2, jadi tidak ada tumpang tindih
        $hasil = $this->calculator->hitungGabungan([
            $p['papak_croc']->id => 15, $p['lancip_croc']->id => 6,
            $p['papak_rees']->id => 20, $p['lancip_rees']->id => 15,
        ]);

        $this->assertEqualsWithDelta(3.0, $hasil['lead_time_produksi_hari'], 1e-9);
        $this->assertEqualsWithDelta(17.0, $hasil['lead_time_total_hari'], 1e-9);
    }

    public function test_contoh_pabrik_1000_dz_selesai_17_hari(): void
    {
        $p = $this->pabrikSekop();

        // coating 12.000 / 780 = 16 hari (terlama). Cetak 1.200 >= 780 dan jumlah >= 780,
        // jadi coating mulai di hari yang sama dengan cetak -> 3 + (16 - 1) - 1 = 17
        $hasil = $this->calculator->hitungGabungan([
            $p['papak_croc']->id => 250, $p['lancip_croc']->id => 125,
            $p['papak_rees']->id => 375, $p['lancip_rees']->id => 250,
        ]);

        $this->assertEqualsWithDelta(3.0, $hasil['jalur_kritis_hari'], 1e-9);
        $this->assertSame(16, $hasil['hari_tahap_terlama']);
        $this->assertSame(1, $hasil['tumpang_tindih_hari']);
        $this->assertEqualsWithDelta(17.0, $hasil['lead_time_produksi_hari'], 1e-9);
        $this->assertEqualsWithDelta(31.0, $hasil['lead_time_total_hari'], 1e-9);
    }

    public function test_beban_produk_lain_ikut_memakai_kapasitas(): void
    {
        $p = $this->pabrikSekop();

        $sendiri = $this->calculator->hitung($p['papak_croc'], 250);
        $bersama = $this->calculator->hitung($p['papak_croc'], 250, null, [$p['papak_rees']->id => 750]);

        $this->assertGreaterThan($sendiri['lead_time_produksi_hari'], $bersama['lead_time_produksi_hari']);
    }
}
