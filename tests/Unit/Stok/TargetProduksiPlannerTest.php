<?php

namespace Tests\Unit\Stok;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\HasilPeramalan;
use App\Models\Peramalan;
use App\Models\TahapanProduksi;
use App\Services\Stok\TargetProduksiPlanner;
use App\Support\Math\Distribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Uji rencana stok bulanan (docs/01 §6): Safety Stock, Reorder Point, Target
 * Produksi, dan ledak BOM jadi kebutuhan bahan, dibandingkan hitungan manual.
 *
 * Skenario dibuat linear (persen susut 0%, kapasitas tahapan besar supaya
 * tidak menambah hari) supaya rasio BOM (1 x 2 = 2) bisa dipakai memverifikasi
 * kebutuhan bahan & safety stock bahan tanpa pembulatan yang mengganggu.
 */
class TargetProduksiPlannerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{peramalan: Peramalan, barangJadi: Barang, bahanBaku: Barang}
     */
    private function skenario(int $stokBarangJadi = 10, int $stokMinimum = 5, int $stokBahanBaku = 5): array
    {
        $tahapan = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 1000]);

        $barangJadi = Barang::factory()->barangJadi()->create([
            'stok_tersedia' => $stokBarangJadi,
            'stok_minimum' => $stokMinimum,
            'service_level' => 95,
        ]);
        $setengahJadi = Barang::factory()->setengahJadi()->create(['stok_tersedia' => 3]);
        $bahanBaku = Barang::factory()->create(['lead_time_hari' => 7, 'stok_tersedia' => $stokBahanBaku]);

        $bomAtas = Bom::create([
            'kode_bom' => 'BOM-JADI', 'nama_bom' => 'Perakitan', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $tahapan->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bomAtas->detail()->create(['barang_id' => $setengahJadi->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $bomBawah = Bom::create([
            'kode_bom' => 'BOM-SJ', 'nama_bom' => 'Komponen', 'barang_id' => $setengahJadi->id,
            'tahapan_id' => $tahapan->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bomBawah->detail()->create(['barang_id' => $bahanBaku->id, 'jumlah_kebutuhan' => 2, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $peramalan = Peramalan::create([
            'kode_peramalan' => 'PRM-UJI-'.uniqid(),
            'barang_id' => $barangJadi->id,
            'periode_awal' => '2024-01', 'periode_akhir' => '2026-05', 'jumlah_data' => 29,
            'ordo_p' => 1, 'ordo_d' => 0, 'ordo_q' => 0,
            'sigma_kuadrat' => 100, 'horizon' => 3, 'status' => 'draft',
        ]);
        HasilPeramalan::create([
            'peramalan_id' => $peramalan->id, 'periode' => '2026-06', 'urutan_t' => 30,
            'tipe' => 'forecast', 'nilai_prediksi' => 50,
        ]);

        return compact('peramalan', 'barangJadi', 'bahanBaku');
    }

    public function test_target_produksi_sesuai_hitungan_manual(): void
    {
        ['peramalan' => $peramalan] = $this->skenario();

        $target = (new TargetProduksiPlanner())->rencanakan($peramalan, '2026-06');

        // Tahapan kapasitas 1000 tidak menambah hari: L_produksi=1 (waktu_proses saja,
        // dua level BOM sama-sama pakai tahapan ini jadi terakumulasi jadi satu baris).
        // L_beli=7 (lead time bahan baku) -> L_total=8.
        $sigmaError = sqrt(100.0);
        $leadTimeTotal = 1.0 + 7.0;
        $ltPeriode = $leadTimeTotal / 30;
        $nilaiZ = Distribution::zScore(0.95);
        $safetyStock = $nilaiZ * $sigmaError * sqrt($ltPeriode);
        $reorderPoint = (50 / 30 * $leadTimeTotal) + $safetyStock;
        $targetMentah = 50 + $safetyStock - 10 - 3; // prediksi + SS - stok barang jadi - stok setengah jadi

        $this->assertEqualsWithDelta($sigmaError, (float) $target->standar_deviasi_error, 1e-4);
        $this->assertEqualsWithDelta($leadTimeTotal, (float) $target->lead_time_total_hari, 1e-6);
        $this->assertEqualsWithDelta($nilaiZ, (float) $target->nilai_z, 1e-3);
        $this->assertEqualsWithDelta($safetyStock, (float) $target->safety_stock, 0.01);
        $this->assertEqualsWithDelta($reorderPoint, (float) $target->reorder_point, 0.01);
        $this->assertEqualsWithDelta($targetMentah, (float) $target->jumlah_target_produksi, 0.01);
        $this->assertSame('segera_produksi', $target->status_stok);
        $this->assertSame(3.0, (float) $target->stok_setengah_jadi);
    }

    public function test_kebutuhan_bahan_mengikuti_rasio_bom_dan_menandai_perlu_beli(): void
    {
        ['peramalan' => $peramalan] = $this->skenario(stokBahanBaku: 5);

        $target = (new TargetProduksiPlanner())->rencanakan($peramalan, '2026-06');
        $kebutuhan = $target->kebutuhanBahan;

        $this->assertCount(1, $kebutuhan);
        $baris = $kebutuhan->first();

        // Rasio BOM 1 (atas) x 2 (bawah) = 2, susut 0% -> kebutuhan = 2 x target produksi.
        $kebutuhanHarusnya = 2 * (float) $target->jumlah_target_produksi;
        $kekuranganHarusnya = $kebutuhanHarusnya - 5; // stok bahan baku = 5
        $ssBahanHarusnya = 2 * (float) $target->safety_stock;

        $this->assertEqualsWithDelta($kebutuhanHarusnya, (float) $baris->jumlah_kebutuhan, 0.02);
        $this->assertEqualsWithDelta($kekuranganHarusnya, (float) $baris->kekurangan, 0.02);
        $this->assertEqualsWithDelta($ssBahanHarusnya, (float) $baris->safety_stock_bahan, 0.05);
        $this->assertEqualsWithDelta($kekuranganHarusnya + $ssBahanHarusnya, (float) $baris->qty_rekomendasi_beli, 0.05);
        $this->assertSame('perlu_beli', $baris->status);
    }

    public function test_kebutuhan_bahan_cukup_tidak_ada_rekomendasi_beli(): void
    {
        // Stok bahan baku dibuat sangat besar supaya kebutuhan pasti tercukupi.
        ['peramalan' => $peramalan] = $this->skenario(stokBahanBaku: 1_000_000);

        $target = (new TargetProduksiPlanner())->rencanakan($peramalan, '2026-06');
        $baris = $target->kebutuhanBahan->first();

        $this->assertSame('cukup', $baris->status);
        $this->assertEqualsWithDelta(0.0, (float) $baris->kekurangan, 1e-9);
        $this->assertEqualsWithDelta(0.0, (float) $baris->qty_rekomendasi_beli, 1e-9);
    }

    public function test_status_aman_tanpa_kebutuhan_bahan_saat_stok_sudah_cukup(): void
    {
        // Stok barang jadi dibuat sangat besar supaya target produksi <= 0.
        ['peramalan' => $peramalan] = $this->skenario(stokBarangJadi: 100000);

        $target = (new TargetProduksiPlanner())->rencanakan($peramalan, '2026-06');

        $this->assertSame('aman', $target->status_stok);
        $this->assertEqualsWithDelta(0.0, (float) $target->jumlah_target_produksi, 1e-9);
        $this->assertCount(0, $target->kebutuhanBahan);
    }

    public function test_status_kritis_saat_stok_barang_jadi_di_bawah_minimum(): void
    {
        ['peramalan' => $peramalan] = $this->skenario(stokBarangJadi: 2, stokMinimum: 5);

        $target = (new TargetProduksiPlanner())->rencanakan($peramalan, '2026-06');

        $this->assertSame('kritis', $target->status_stok);
    }

    public function test_menolak_periode_tanpa_hasil_forecast(): void
    {
        ['peramalan' => $peramalan] = $this->skenario();

        $this->expectException(RuntimeException::class);
        (new TargetProduksiPlanner())->rencanakan($peramalan, '2099-01');
    }

    public function test_jalankan_ulang_memperbarui_bukan_menggandakan(): void
    {
        ['peramalan' => $peramalan] = $this->skenario();

        $planner = new TargetProduksiPlanner();
        $pertama = $planner->rencanakan($peramalan, '2026-06');
        $kedua = $planner->rencanakan($peramalan, '2026-06');

        $this->assertSame($pertama->id, $kedua->id);
        $this->assertDatabaseCount('target_produksi', 1);
        $this->assertCount(1, $kedua->kebutuhanBahan);
    }
}
