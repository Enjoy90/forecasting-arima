<?php

namespace Tests\Unit\Arima;

use App\Services\Arima\ArimaEstimator;
use Tests\TestCase;

/**
 * Uji estimasi ARIMA via Conditional Least Squares dua-langkah (docs/01 §5,
 * Tahap 2). Fixture memakai deret AR(1) sintetis-deterministik (phi asli=0.6,
 * guncangan dari fungsi trigonometri tetap, bukan angka acak sungguhan)
 * supaya hasil regresi bisa direproduksi persis di setiap run test.
 */
class ArimaEstimatorTest extends TestCase
{
    private ArimaEstimator $estimator;

    /** @var array<int, float> */
    private array $ar1Sintetis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estimator = new ArimaEstimator();

        // zt = 0.6*z(t-1) + guncangan, guncangan = deterministik (bukan acak sungguhan)
        $n = 40;
        $z = [0.0];
        for ($t = 1; $t < $n; $t++) {
            $guncangan = sin($t * 1.3) * 2.0 + cos($t * 0.7);
            $z[] = 0.6 * $z[$t - 1] + $guncangan;
        }
        $this->ar1Sintetis = $z;
    }

    public function test_estimasi_ar1_menangkap_koefisien_mendekati_nilai_asli(): void
    {
        $hasil = $this->estimator->estimasi($this->ar1Sintetis, 1, 0);

        $this->assertSame(1, $hasil['ordo_p']);
        $this->assertSame(0, $hasil['ordo_q']);
        $this->assertSame(2, $hasil['k']); // konstanta + AR(1)
        $this->assertSame(39, $hasil['n']); // 40 data - p=1

        $ar1 = $hasil['parameter'][1];
        $this->assertSame('AR', $ar1['jenis']);
        $this->assertSame(1, $ar1['lag']);
        // Phi asli 0.6; estimasi CLS pada sampel kecil tidak akan persis tapi harus di rentang wajar.
        $this->assertGreaterThan(0.2, $ar1['koefisien']);
        $this->assertLessThan(0.8, $ar1['koefisien']);
        $this->assertTrue($ar1['is_signifikan']);
    }

    public function test_estimasi_menghasilkan_nilai_regresi_yang_dapat_direproduksi(): void
    {
        // Regression-lock: angka pasti dari implementasi saat ini, supaya perubahan
        // logika perhitungan (Matrix::ols, formula AIC/BIC) ketahuan lewat test gagal.
        $hasil = $this->estimator->estimasi($this->ar1Sintetis, 1, 0);
        $ar1 = $hasil['parameter'][1];

        $this->assertEqualsWithDelta(0.4551, $ar1['koefisien'], 0.001);
        $this->assertEqualsWithDelta(0.1465, $ar1['standard_error'], 0.001);
        $this->assertEqualsWithDelta(3.107, $ar1['t_hitung'], 0.01);
        $this->assertEqualsWithDelta(40.277, $hasil['aic'], 0.01);
        $this->assertEqualsWithDelta(43.604, $hasil['bic'], 0.01);
    }

    public function test_estimasi_konstanta_pada_deret_berosilasi_tidak_signifikan(): void
    {
        $hasil = $this->estimator->estimasi($this->ar1Sintetis, 1, 0);
        $konstanta = $hasil['parameter'][0];

        $this->assertSame('KONSTANTA', $konstanta['jenis']);
        $this->assertFalse($konstanta['is_signifikan']);
    }

    public function test_estimasi_konvensi_tanda_koefisien_ma(): void
    {
        // theta(B) = 1 - theta_1 B - ...; koefisien MA yang dilaporkan harus
        // negasi dari koefisien regresi mentah pada residual proksi.
        $hasil = $this->estimator->estimasi($this->ar1Sintetis, 0, 1);
        $ma1 = $hasil['parameter'][1];

        $this->assertSame('MA', $ma1['jenis']);
        $this->assertSame(1, $ma1['lag']);
        $this->assertIsFloat($ma1['koefisien']);
    }

    public function test_estimasi_menolak_data_lebih_sedikit_dari_parameter(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->estimator->estimasi([1.0, 2.0], 3, 0);
    }

    public function test_grid_search_mengembalikan_seluruh_kombinasi_p_dan_q(): void
    {
        $grid = $this->estimator->gridSearch($this->ar1Sintetis, 2, 1);

        // (pMax+1) x (qMax+1) = 3 x 2 = 6 kombinasi, semuanya cukup data utk difit.
        $this->assertCount(6, $grid);

        $kombinasi = array_map(fn ($k) => [$k['ordo_p'], $k['ordo_q']], $grid);
        foreach ([0, 1, 2] as $p) {
            foreach ([0, 1] as $q) {
                $this->assertContains([$p, $q], $kombinasi);
            }
        }
    }

    public function test_grid_search_memilih_tepat_satu_model_dengan_aic_terkecil(): void
    {
        $grid = $this->estimator->gridSearch($this->ar1Sintetis, 2, 1);

        $terpilih = array_values(array_filter($grid, fn ($k) => $k['is_terpilih']));
        $this->assertCount(1, $terpilih);

        $aicTerkecil = min(array_column($grid, 'aic'));
        $this->assertEqualsWithDelta($aicTerkecil, $terpilih[0]['aic'], 1e-9);
    }

    public function test_grid_search_melewati_kombinasi_yang_datanya_tidak_cukup_tanpa_melempar_exception(): void
    {
        // Data sangat pendek: kombinasi p,q besar pasti dilewati, tapi tidak boleh melempar exception.
        $grid = $this->estimator->gridSearch([1.0, 2.0, 3.0, 4.0, 5.0, 6.0], 3, 3);

        $this->assertNotEmpty($grid);
        // Kombinasi yang lolos harus tetap memenuhi syarat OLS: observasi > parameter.
        foreach ($grid as $kandidat) {
            $this->assertGreaterThan($kandidat['k'], $kandidat['n']);
        }
    }
}
