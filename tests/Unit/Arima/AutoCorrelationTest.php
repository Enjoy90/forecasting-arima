<?php

namespace Tests\Unit\Arima;

use App\Services\Arima\AutoCorrelation;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Uji ACF & PACF (Durbin-Levinson) dibandingkan hitungan manual, tahap
 * identifikasi model Box-Jenkins (docs/01 §5, Tahap 1: A7-A8).
 */
class AutoCorrelationTest extends TestCase
{
    private AutoCorrelation $ac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ac = new AutoCorrelation();
    }

    public function test_acf_sesuai_hitungan_manual(): void
    {
        // data=[1,3,2,5,4], rata-rata=3, deviasi=[-2,0,-1,2,1], penyebut=SUM dev^2=10
        // r1 pembilang (t=0..3): (-2*0)+(0*-1)+(-1*2)+(2*1) = 0        -> r1 = 0/10 = 0
        // r2 pembilang (t=0..2): (-2*-1)+(0*2)+(-1*1) = 2+0-1 = 1      -> r2 = 1/10 = 0.1
        $acf = $this->ac->acf([1, 3, 2, 5, 4], 2);

        $this->assertEqualsWithDelta(0.0, $acf[1], 1e-9);
        $this->assertEqualsWithDelta(0.1, $acf[2], 1e-9);
    }

    public function test_pacf_sesuai_hitungan_manual_durbin_levinson(): void
    {
        // phi11 = r1 = 0
        // phi22 = (r2 - phi11*r1) / (1 - phi11*r1) = (0.1-0)/(1-0) = 0.1
        $pacf = $this->ac->pacf([1, 3, 2, 5, 4], 2);

        $this->assertEqualsWithDelta(0.0, $pacf[1], 1e-9);
        $this->assertEqualsWithDelta(0.1, $pacf[2], 1e-9);
    }

    public function test_pacf_lag_1_selalu_sama_dengan_acf_lag_1(): void
    {
        // Identitas Durbin-Levinson: phi_11 = r_1, berlaku untuk deret apa pun.
        $data = [10, 12, 9, 11, 10, 13, 8, 12, 10, 11, 9, 12];

        $acf = $this->ac->acf($data, 3);
        $pacf = $this->ac->pacf($data, 3);

        $this->assertEqualsWithDelta($acf[1], $pacf[1], 1e-9);
    }

    public function test_acf_data_tren_naik_berkorelasi_kuat_dan_meluruh(): void
    {
        // Data penjualan riil BJ-01 (36 bulan, trending): ACF lag pendek harus
        // tinggi (khas data belum stasioner) dan meluruh seiring lag membesar.
        $data = [1044, 1228, 1266, 1222, 1146, 1132, 1068, 987, 951, 906, 1008, 1053,
            1180, 1405, 1533, 1452, 1487, 1421, 1217, 1271, 1203, 1106, 1274, 1205,
            1475, 1709, 1684, 1668, 1808, 1604, 1475, 1392, 1416, 1468, 1447, 1485];

        $acf = $this->ac->acf($data, 5);

        $this->assertGreaterThan(0.7, $acf[1]);
        $this->assertGreaterThan($acf[2], $acf[1]);
        $this->assertGreaterThan($acf[5], $acf[1]);
    }

    public function test_batas_signifikansi_mengecil_seiring_n_membesar(): void
    {
        $batasKecil = $this->ac->batasSignifikansi(10);
        $batasBesar = $this->ac->batasSignifikansi(100);

        $this->assertEqualsWithDelta(1.959964 / sqrt(10), $batasKecil, 1e-4);
        $this->assertGreaterThan($batasBesar, $batasKecil);
    }

    public function test_is_signifikan_membandingkan_terhadap_batas(): void
    {
        // batas untuk n=5 = 1.959964/sqrt(5) = 0.8764
        $this->assertFalse($this->ac->isSignifikan(0.2, 5));
        $this->assertTrue($this->ac->isSignifikan(0.9, 5));
    }

    public function test_max_lag_tidak_boleh_lebih_besar_atau_sama_dengan_jumlah_data(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ac->acf([1, 2, 3], 3);
    }
}
