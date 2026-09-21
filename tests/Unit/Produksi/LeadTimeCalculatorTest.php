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
 * Uji waktu tunggu operasional pabrik (docs/01 §4): L_beli + L_produksi
 * dari rantai BOM dua level, dibandingkan hitungan manual.
 */
class LeadTimeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private LeadTimeCalculator $calculator;

    /** @return array{barangJadi: Barang} */
    private function rantaiBom(): array
    {
        $t1 = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 100]);
        $t2 = TahapanProduksi::factory()->create(['waktu_proses_hari' => 2, 'kapasitas_per_hari' => 1000]);

        $barangJadi = Barang::factory()->barangJadi()->create();
        $setengahJadi = Barang::factory()->setengahJadi()->create();
        $bahanBaku1 = Barang::factory()->create(['lead_time_hari' => 14]);
        $bahanBaku2 = Barang::factory()->create(['lead_time_hari' => 5]);

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

        return compact('barangJadi');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LeadTimeCalculator();
    }

    public function test_lead_time_sesuai_hitungan_manual(): void
    {
        ['barangJadi' => $barangJadi] = $this->rantaiBom();

        // t2: hari=2+(ceil(50/1000)-1)=2 ; t1: hari=1+(ceil(50/100)-1)=1 -> L_produksi=3
        // L_beli=MAX(14,5)=14 -> L_total=17
        $hasil = $this->calculator->hitung($barangJadi, 50);

        $this->assertEqualsWithDelta(14.0, $hasil['lead_time_pembelian_hari'], 1e-9);
        $this->assertEqualsWithDelta(3.0, $hasil['lead_time_produksi_hari'], 1e-9);
        $this->assertEqualsWithDelta(17.0, $hasil['lead_time_total_hari'], 1e-9);
    }

    public function test_tanggal_mulai_produksi_dan_pesan_bahan_mundur_dari_awal_periode(): void
    {
        ['barangJadi' => $barangJadi] = $this->rantaiBom();

        $hasil = $this->calculator->hitung($barangJadi, 50, Carbon::parse('2026-12-01'));

        // mulai produksi = 2026-12-01 - 3 hari ; pesan bahan = mulai produksi - 14 hari
        $this->assertSame('2026-11-28', $hasil['tanggal_mulai_produksi']->format('Y-m-d'));
        $this->assertSame('2026-11-14', $hasil['tanggal_pesan_bahan']->format('Y-m-d'));
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

        // t1 kapasitas=100: target=250 di t1 -> hari=1+(ceil(250/100)-1)=1+2=3 (naik dari 1 hari)
        $hasilKecil = $this->calculator->hitung($barangJadi, 50);
        $hasilBesar = $this->calculator->hitung($barangJadi, 250);

        $this->assertGreaterThan($hasilKecil['lead_time_produksi_hari'], $hasilBesar['lead_time_produksi_hari']);
    }
}
