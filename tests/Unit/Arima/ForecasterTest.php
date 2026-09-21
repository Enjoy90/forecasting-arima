<?php

namespace Tests\Unit\Arima;

use App\Services\Arima\Forecaster;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Uji Forecaster (docs/01 §5, Tahap 4) dengan parameter model yang dikonstruksi
 * manual (bukan hasil ArimaEstimator) supaya forecast-nya bisa dihitung tangan
 * persis, sekaligus memverifikasi rekursi ARIMA dan penggabungan polinomial phi(B)(1-B)^d.
 */
class ForecasterTest extends TestCase
{
    private Forecaster $forecaster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forecaster = new Forecaster();
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameter
     */
    private function model(int $p, int $q, array $parameter, float $sigmaKuadrat = 1.0): array
    {
        return [
            'ordo_p' => $p,
            'ordo_q' => $q,
            'parameter' => $parameter,
            'sigma_kuadrat' => $sigmaKuadrat,
        ];
    }

    public function test_forecast_ar1_sesuai_hitungan_manual(): void
    {
        // Z_t = 0.5*Z_(t-1), data=[10,20], d=0.
        // fitted(2) = 0.5*Z(1) = 0.5*10 = 5 ; residual(2) = 20-5 = 15
        // forecast h1 = 0.5*Z(2) = 0.5*20 = 10 ; forecast h2 = 0.5*10 = 5
        $model = $this->model(1, 0, [
            ['jenis' => 'KONSTANTA', 'lag' => 0, 'koefisien' => 0.0],
            ['jenis' => 'AR', 'lag' => 1, 'koefisien' => 0.5],
        ]);

        $hasil = $this->forecaster->forecast([10.0, 20.0], 0, $model, horizon: 2);

        $this->assertEqualsWithDelta(5.0, $hasil['fitted_in_sample'][2], 1e-9);
        $this->assertEqualsWithDelta(15.0, $hasil['residual_in_sample'][2], 1e-9);
        $this->assertEqualsWithDelta(10.0, $hasil['forecast'][0], 1e-9);
        $this->assertEqualsWithDelta(5.0, $hasil['forecast'][1], 1e-9);
    }

    public function test_forecast_ar1_interval_kepercayaan_sesuai_psi_weight_ar(): void
    {
        // psi_j = phi^j utk AR(1) murni: psi1=0.5, psi2=0.25. sigma=1, Z(0.975)=1.959964.
        // se(h=1)=sqrt(0.25)=0.5 ; se(h=2)=sqrt(0.25+0.0625)=0.559017
        $model = $this->model(1, 0, [
            ['jenis' => 'KONSTANTA', 'lag' => 0, 'koefisien' => 0.0],
            ['jenis' => 'AR', 'lag' => 1, 'koefisien' => 0.5],
        ], sigmaKuadrat: 1.0);

        $hasil = $this->forecaster->forecast([10.0, 20.0], 0, $model, horizon: 2);

        $this->assertEqualsWithDelta(10.0 - 1.959964 * 0.5, $hasil['interval'][0]['batas_bawah'], 1e-3);
        $this->assertEqualsWithDelta(10.0 + 1.959964 * 0.5, $hasil['interval'][0]['batas_atas'], 1e-3);
        $this->assertEqualsWithDelta(5.0 - 1.959964 * 0.559017, $hasil['interval'][1]['batas_bawah'], 1e-3);
        $this->assertEqualsWithDelta(5.0 + 1.959964 * 0.559017, $hasil['interval'][1]['batas_atas'], 1e-3);
    }

    public function test_forecast_arima_0_1_0_menghasilkan_forecast_naif(): void
    {
        // p=0,d=1,q=0 (murni differencing, tanpa AR/MA) -> teori Box-Jenkins: forecast
        // = nilai aktual terakhir diulang terus (naive forecast / random walk).
        $model = $this->model(0, 0, [
            ['jenis' => 'KONSTANTA', 'lag' => 0, 'koefisien' => 0.0],
        ]);

        $hasil = $this->forecaster->forecast([10.0, 15.0, 13.0, 18.0], 1, $model, horizon: 2);

        $this->assertEqualsWithDelta(18.0, $hasil['forecast'][0], 1e-9);
        $this->assertEqualsWithDelta(18.0, $hasil['forecast'][1], 1e-9);

        // fitted(t) = Z(t-1) persis (naive one-step-ahead).
        $this->assertEqualsWithDelta(10.0, $hasil['fitted_in_sample'][2], 1e-9);
        $this->assertEqualsWithDelta(15.0, $hasil['fitted_in_sample'][3], 1e-9);
        $this->assertEqualsWithDelta(13.0, $hasil['fitted_in_sample'][4], 1e-9);
    }

    public function test_interval_kepercayaan_melebar_seiring_horizon(): void
    {
        $model = $this->model(1, 0, [
            ['jenis' => 'KONSTANTA', 'lag' => 0, 'koefisien' => 0.0],
            ['jenis' => 'AR', 'lag' => 1, 'koefisien' => 0.5],
        ]);

        $hasil = $this->forecaster->forecast([10.0, 20.0, 15.0, 25.0], 0, $model, horizon: 4);

        $lebar = fn ($h) => $hasil['interval'][$h]['batas_atas'] - $hasil['interval'][$h]['batas_bawah'];

        $this->assertGreaterThan($lebar(0), $lebar(1));
        $this->assertGreaterThan($lebar(1), $lebar(2));
    }

    public function test_menolak_data_lebih_sedikit_dari_orde_model(): void
    {
        $model = $this->model(3, 0, [
            ['jenis' => 'KONSTANTA', 'lag' => 0, 'koefisien' => 0.0],
            ['jenis' => 'AR', 'lag' => 1, 'koefisien' => 0.1],
            ['jenis' => 'AR', 'lag' => 2, 'koefisien' => 0.1],
            ['jenis' => 'AR', 'lag' => 3, 'koefisien' => 0.1],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->forecaster->forecast([1.0, 2.0], 0, $model, horizon: 1);
    }
}
