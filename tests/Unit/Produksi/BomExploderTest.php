<?php

namespace Tests\Unit\Produksi;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\TahapanProduksi;
use App\Services\Produksi\BomExploder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Uji ledak BOM rekursif (docs/01 §1.3: BOM bertingkat) dibandingkan
 * hitungan manual. Rumus satu level (Bom::kebutuhanUntuk()) sudah ada dari
 * fondasi Fase 0; yang diuji di sini murni jalur rekursif & akumulasinya.
 */
class BomExploderTest extends TestCase
{
    use RefreshDatabase;

    private BomExploder $exploder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exploder = new BomExploder();
    }

    public function test_ledak_satu_level_sesuai_hitungan_manual(): void
    {
        $tahapan = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 100]);
        $barangJadi = Barang::factory()->barangJadi()->create();
        $bahanA = Barang::factory()->create();
        $bahanB = Barang::factory()->create();

        $bom = Bom::create([
            'kode_bom' => 'BOM-1', 'nama_bom' => 'Resep', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $tahapan->id, 'jumlah_output' => 2, 'is_aktif' => true,
        ]);
        $bom->detail()->create(['barang_id' => $bahanA->id, 'jumlah_kebutuhan' => 10, 'persen_susut' => 0, 'satuan' => 'Lembar']);
        $bom->detail()->create(['barang_id' => $bahanB->id, 'jumlah_kebutuhan' => 4, 'persen_susut' => 25, 'satuan' => 'Kg']);

        // target=20, jumlah_output=2 -> rasio=10. A: 10*10*1=100 ; B: 4*10*1.25=50
        $hasil = $this->exploder->ledakkan($barangJadi, 20);

        $bahan = collect($hasil['bahan_baku'])->keyBy('barang_id');
        $this->assertEqualsWithDelta(100.0, $bahan[$bahanA->id]['jumlah'], 1e-9);
        $this->assertEqualsWithDelta(50.0, $bahan[$bahanB->id]['jumlah'], 1e-9);

        $tahapanHasil = collect($hasil['tahapan'])->keyBy('tahapan_id');
        $this->assertEqualsWithDelta(20.0, $tahapanHasil[$tahapan->id]['jumlah'], 1e-9);
    }

    public function test_ledak_dua_level_menjalar_ke_setengah_jadi(): void
    {
        $t1 = TahapanProduksi::factory()->create(['waktu_proses_hari' => 2, 'kapasitas_per_hari' => 50]);
        $t2 = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 1000]);

        $barangJadi = Barang::factory()->barangJadi()->create();
        $setengahJadi = Barang::factory()->setengahJadi()->create();
        $bahanBaku = Barang::factory()->create(['lead_time_hari' => 9]);

        $bomAtas = Bom::create([
            'kode_bom' => 'BOM-ATAS', 'nama_bom' => 'Perakitan', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $t2->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bomAtas->detail()->create(['barang_id' => $setengahJadi->id, 'jumlah_kebutuhan' => 3, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $bomBawah = Bom::create([
            'kode_bom' => 'BOM-BAWAH', 'nama_bom' => 'Komponen', 'barang_id' => $setengahJadi->id,
            'tahapan_id' => $t1->id, 'jumlah_output' => 5, 'is_aktif' => true,
        ]);
        $bomBawah->detail()->create(['barang_id' => $bahanBaku->id, 'jumlah_kebutuhan' => 2, 'persen_susut' => 10, 'satuan' => 'Kg']);

        // target=10 di BJ -> SJ butuh 3*10=30 -> bahanBaku: rasio=30/5=6 -> 2*6*1.1=13.2
        $hasil = $this->exploder->ledakkan($barangJadi, 10);

        $bahan = collect($hasil['bahan_baku'])->keyBy('barang_id');
        $this->assertEqualsWithDelta(13.2, $bahan[$bahanBaku->id]['jumlah'], 1e-9);
        $this->assertSame($t1->id, $bahan[$bahanBaku->id]['tahapan_id']);

        $tahapanHasil = collect($hasil['tahapan'])->keyBy('tahapan_id');
        $this->assertEqualsWithDelta(10.0, $tahapanHasil[$t2->id]['jumlah'], 1e-9);
        $this->assertEqualsWithDelta(30.0, $tahapanHasil[$t1->id]['jumlah'], 1e-9);
    }

    public function test_bahan_dan_tahapan_yang_sama_muncul_dua_jalur_terakumulasi(): void
    {
        $t1 = TahapanProduksi::factory()->create();
        $t2 = TahapanProduksi::factory()->create();

        $barangJadi = Barang::factory()->barangJadi()->create();
        $sj1 = Barang::factory()->setengahJadi()->create();
        $sj2 = Barang::factory()->setengahJadi()->create();
        $bahanX = Barang::factory()->create();

        $bomAtas = Bom::create([
            'kode_bom' => 'BOM-TOP', 'nama_bom' => 'Gabungan', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $t1->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bomAtas->detail()->create(['barang_id' => $sj1->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);
        $bomAtas->detail()->create(['barang_id' => $sj2->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $bomSj1 = Bom::create(['kode_bom' => 'BOM-SJ1', 'nama_bom' => 'SJ1', 'barang_id' => $sj1->id, 'tahapan_id' => $t2->id, 'jumlah_output' => 1, 'is_aktif' => true]);
        $bomSj1->detail()->create(['barang_id' => $bahanX->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $bomSj2 = Bom::create(['kode_bom' => 'BOM-SJ2', 'nama_bom' => 'SJ2', 'barang_id' => $sj2->id, 'tahapan_id' => $t2->id, 'jumlah_output' => 1, 'is_aktif' => true]);
        $bomSj2->detail()->create(['barang_id' => $bahanX->id, 'jumlah_kebutuhan' => 2, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        // target=5: SJ1=5, SJ2=5 -> bahanX = 1*5 (via SJ1) + 2*5 (via SJ2) = 15 ; T2 = 5+5=10
        $hasil = $this->exploder->ledakkan($barangJadi, 5);

        $bahan = collect($hasil['bahan_baku'])->keyBy('barang_id');
        $this->assertEqualsWithDelta(15.0, $bahan[$bahanX->id]['jumlah'], 1e-9);

        $tahapanHasil = collect($hasil['tahapan'])->keyBy('tahapan_id');
        $this->assertEqualsWithDelta(5.0, $tahapanHasil[$t1->id]['jumlah'], 1e-9);
        $this->assertEqualsWithDelta(10.0, $tahapanHasil[$t2->id]['jumlah'], 1e-9);
    }

    public function test_barang_tanpa_bom_aktif_melempar_exception(): void
    {
        $barangJadi = Barang::factory()->barangJadi()->create();

        $this->expectException(RuntimeException::class);
        $this->exploder->ledakkan($barangJadi, 10);
    }

    public function test_bom_berputar_melempar_exception_bukan_infinite_loop(): void
    {
        $tahapan = TahapanProduksi::factory()->create();
        $a = Barang::factory()->setengahJadi()->create();
        $b = Barang::factory()->setengahJadi()->create();

        // A butuh B, dan B (di jalur BOM terpisah) butuh A lagi -> berputar.
        $bomA = Bom::create(['kode_bom' => 'BOM-A', 'nama_bom' => 'A', 'barang_id' => $a->id, 'tahapan_id' => $tahapan->id, 'jumlah_output' => 1, 'is_aktif' => true]);
        $bomA->detail()->create(['barang_id' => $b->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $bomB = Bom::create(['kode_bom' => 'BOM-B', 'nama_bom' => 'B', 'barang_id' => $b->id, 'tahapan_id' => $tahapan->id, 'jumlah_output' => 1, 'is_aktif' => true]);
        $bomB->detail()->create(['barang_id' => $a->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BOM berputar');
        $this->exploder->ledakkan($a, 10);
    }
}
