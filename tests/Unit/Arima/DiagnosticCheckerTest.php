<?php

namespace Tests\Unit\Arima;

use App\Services\Arima\DiagnosticChecker;
use Tests\TestCase;

/**
 * Uji diagnostic checking (docs/01 §5, Tahap 3): Ljung-Box harus lolos untuk
 * residual acak (white noise) dan gagal untuk deret yang jelas berkorelasi.
 */
class DiagnosticCheckerTest extends TestCase
{
    private DiagnosticChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new DiagnosticChecker();
    }

    /**
     * Pembangkit deterministik (LCG, seed tetap), dipakai sebagai pengganti
     * angka acak sungguhan supaya hasil test bisa direproduksi persis, tapi
     * tetap punya autokorelasi rendah seperti white noise asli.
     *
     * @return array<int, float>
     */
    private function noiseLcg(int $n, int $seed = 42): array
    {
        $hasil = [];
        $state = $seed;
        for ($i = 0; $i < $n; $i++) {
            $state = (1103515245 * $state + 12345) % 2147483648;
            $hasil[] = ($state / 2147483648 - 0.5) * 10;
        }

        return $hasil;
    }

    public function test_ljung_box_lolos_untuk_residual_white_noise(): void
    {
        $hasil = $this->checker->ljungBox($this->noiseLcg(40));

        $this->assertTrue($hasil['lolos']);
        $this->assertGreaterThan(0.05, $hasil['p_value']);
    }

    public function test_ljung_box_gagal_untuk_deret_yang_jelas_berkorelasi(): void
    {
        // Data penjualan riil BJ-01: sangat trending/berkorelasi, bukan white noise.
        $bj01 = [1044, 1228, 1266, 1222, 1146, 1132, 1068, 987, 951, 906, 1008, 1053,
            1180, 1405, 1533, 1452, 1487, 1421, 1217, 1271, 1203, 1106, 1274, 1205,
            1475, 1709, 1684, 1668, 1808, 1604, 1475, 1392, 1416, 1468, 1447, 1485];

        $hasil = $this->checker->ljungBox($bj01);

        $this->assertFalse($hasil['lolos']);
        $this->assertLessThan(0.05, $hasil['p_value']);
    }

    public function test_ljung_box_derajat_bebas_dikurangi_orde_p_dan_q(): void
    {
        $hasil = $this->checker->ljungBox($this->noiseLcg(40), p: 1, q: 1, jumlahLag: 10);

        $this->assertSame(8, $hasil['derajat_bebas']); // m=10, p=1, q=1 -> 10-1-1=8
    }

    public function test_normalitas_lolos_untuk_residual_simetris(): void
    {
        $hasil = $this->checker->normalitas($this->noiseLcg(40));

        $this->assertTrue($hasil['is_normal']);
        $this->assertGreaterThan(0.05, $hasil['p_value']);
    }

    public function test_normalitas_menghitung_skewness_dan_kurtosis(): void
    {
        // Residual simetris sempurna: skewness harus mendekati 0.
        $simetris = [-3.0, -2.0, -1.0, 0.0, 1.0, 2.0, 3.0];
        $hasil = $this->checker->normalitas($simetris);

        $this->assertEqualsWithDelta(0.0, $hasil['skewness'], 1e-9);
    }
}
