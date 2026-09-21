<?php

namespace Tests\Unit\Arima;

use App\Services\Arima\AccuracyMetric;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Uji MAPE, RMSE, MAE dibandingkan hitungan manual (docs/01 §5.1, Tahap 4).
 */
class AccuracyMetricTest extends TestCase
{
    private AccuracyMetric $metric;

    /** @var array<int, float> */
    private array $aktual = [100, 200, 300];

    /** @var array<int, float> */
    private array $prediksi = [110, 190, 320];

    protected function setUp(): void
    {
        parent::setUp();
        $this->metric = new AccuracyMetric();
    }

    public function test_mape_sesuai_hitungan_manual(): void
    {
        // error abs: 10,10,20 -> persen: 0.1, 0.05, 0.0667 -> rata2*100 = 7.2222
        $this->assertEqualsWithDelta(7.2222, $this->metric->mape($this->aktual, $this->prediksi), 1e-3);
    }

    public function test_rmse_sesuai_hitungan_manual(): void
    {
        // error^2: 100,100,400 -> rata2=200 -> sqrt(200)=14.1421
        $this->assertEqualsWithDelta(14.1421, $this->metric->rmse($this->aktual, $this->prediksi), 1e-3);
    }

    public function test_mae_sesuai_hitungan_manual(): void
    {
        // error abs: 10,10,20 -> rata2 = 13.3333
        $this->assertEqualsWithDelta(13.3333, $this->metric->mae($this->aktual, $this->prediksi), 1e-3);
    }

    public function test_kategori_mape_sesuai_ambang_batas_docs(): void
    {
        $this->assertSame('Sangat Baik', $this->metric->kategoriMape(9.99));
        $this->assertSame('Baik', $this->metric->kategoriMape(19.99));
        $this->assertSame('Cukup', $this->metric->kategoriMape(49.99));
        $this->assertSame('Buruk', $this->metric->kategoriMape(50.01));
    }

    public function test_menolak_jumlah_data_tidak_sama(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->metric->mape([1, 2, 3], [1, 2]);
    }

    public function test_menolak_data_kosong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->metric->rmse([], []);
    }

    public function test_mape_melewati_bulan_dengan_aktual_nol_tanpa_pembagian_oleh_nol(): void
    {
        // Bulan ke-2 (aktual=0, dari TimeSeriesBuilder mengisi bulan tanpa penjualan)
        // harus dilewati, bukan menghasilkan INF/NAN. Sisa 2 bulan: persen 0.1 dan 0.0667.
        $mape = $this->metric->mape([100, 0, 300], [110, 999, 320]);

        $this->assertEqualsWithDelta((0.1 + (20 / 300)) / 2 * 100, $mape, 1e-6);
        $this->assertTrue(is_finite($mape));
    }

    public function test_mape_nol_saat_seluruh_aktual_nol(): void
    {
        $this->assertSame(0.0, $this->metric->mape([0, 0], [5, 10]));
    }
}
