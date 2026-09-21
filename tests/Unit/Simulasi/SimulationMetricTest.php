<?php

namespace Tests\Unit\Simulasi;

use App\Services\Simulasi\SimulationMetric;
use Tests\TestCase;

/**
 * Uji metrik perbandingan skenario (docs/01 §7.3) dibandingkan hitungan manual.
 */
class SimulationMetricTest extends TestCase
{
    private SimulationMetric $metric;

    /** @var array<int, array<string, float>> */
    private array $detailBulanan = [
        ['permintaan_aktual' => 25, 'terpenuhi' => 20, 'stockout_unit' => 5, 'stok_akhir' => 10, 'overstock_unit' => 0, 'biaya_simpan' => 100, 'biaya_stockout' => 50],
        ['permintaan_aktual' => 30, 'terpenuhi' => 30, 'stockout_unit' => 0, 'stok_akhir' => 20, 'overstock_unit' => 8, 'biaya_simpan' => 200, 'biaya_stockout' => 0],
        ['permintaan_aktual' => 18, 'terpenuhi' => 15, 'stockout_unit' => 3, 'stok_akhir' => 5, 'overstock_unit' => 0, 'biaya_simpan' => 50, 'biaya_stockout' => 30],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->metric = new SimulationMetric();
    }

    public function test_metrik_sesuai_hitungan_manual(): void
    {
        $hasil = $this->metric->hitung($this->detailBulanan);

        $this->assertEqualsWithDelta(8.0, $hasil['total_stockout_unit'], 1e-9);
        $this->assertSame(2, $hasil['bulan_stockout']);
        $this->assertEqualsWithDelta(35 / 3, $hasil['rata_stok_akhir'], 1e-9);
        $this->assertEqualsWithDelta(8.0, $hasil['total_overstock_unit'], 1e-9);
        $this->assertEqualsWithDelta(65 / 73 * 100, $hasil['service_level_tercapai'], 1e-9);
        $this->assertEqualsWithDelta(73 / (35 / 3), $hasil['perputaran_persediaan'], 1e-9);
        $this->assertEqualsWithDelta(430.0, $hasil['total_biaya'], 1e-9);
    }

    public function test_service_level_100_persen_saat_tanpa_permintaan(): void
    {
        $hasil = $this->metric->hitung([
            ['permintaan_aktual' => 0, 'terpenuhi' => 0, 'stockout_unit' => 0, 'stok_akhir' => 0, 'overstock_unit' => 0, 'biaya_simpan' => 0, 'biaya_stockout' => 0],
        ]);

        $this->assertEqualsWithDelta(100.0, $hasil['service_level_tercapai'], 1e-9);
    }

    public function test_perputaran_nol_saat_rata_stok_akhir_nol(): void
    {
        $hasil = $this->metric->hitung([
            ['permintaan_aktual' => 10, 'terpenuhi' => 10, 'stockout_unit' => 0, 'stok_akhir' => 0, 'overstock_unit' => 0, 'biaya_simpan' => 0, 'biaya_stockout' => 0],
        ]);

        $this->assertEqualsWithDelta(0.0, $hasil['perputaran_persediaan'], 1e-9);
    }
}
