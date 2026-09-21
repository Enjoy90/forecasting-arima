<?php

namespace App\Services\Arima;

use App\Support\Math\Distribution;

/**
 * Diagnostic checking (docs/01 §5, Tahap 3): uji Ljung-Box (residual harus
 * white noise) dan uji normalitas Jarque-Bera pada residual model.
 */
class DiagnosticChecker
{
    /**
     * Uji Ljung-Box: Q = n(n+2) * SUM( r_k^2 / (n-k) ), k=1..m.
     * H0: residual white noise. Model layak bila p_value > 0.05.
     *
     * @param  array<int, float>  $residual
     */
    public function ljungBox(array $residual, int $p = 0, int $q = 0, ?int $jumlahLag = null): array
    {
        $n = count($residual);
        $m = max($jumlahLag ?? min(20, intdiv($n, 4)), 1);

        $acf = (new AutoCorrelation())->acf($residual, $m);

        $statistikQ = 0.0;
        foreach ($acf as $k => $r) {
            $statistikQ += ($r ** 2) / ($n - $k);
        }
        $statistikQ *= $n * ($n + 2);

        $derajatBebas = max($m - $p - $q, 1);
        $nilaiKritis = Distribution::chiSquareTabel($derajatBebas);
        $pValue = Distribution::pValueChiSquare($derajatBebas, $statistikQ);
        $lolos = $pValue > 0.05;

        return [
            'statistik_q' => $statistikQ,
            'derajat_bebas' => $derajatBebas,
            'nilai_kritis' => $nilaiKritis,
            'p_value' => $pValue,
            'lolos' => $lolos,
            'kesimpulan' => $lolos
                ? sprintf('Residual white noise (Q=%.4f, p-value=%.4f).', $statistikQ, $pValue)
                : sprintf('Residual belum white noise (Q=%.4f, p-value=%.4f), model perlu ditinjau ulang.', $statistikQ, $pValue),
        ];
    }

    /**
     * Uji normalitas Jarque-Bera: JB = (n/6) * (S^2 + (K-3)^2/4), dibandingkan
     * chi-square(2). H0: residual berdistribusi normal.
     *
     * @param  array<int, float>  $residual
     */
    public function normalitas(array $residual): array
    {
        $n = count($residual);
        $rataRata = array_sum($residual) / $n;

        $m2 = 0.0;
        $m3 = 0.0;
        $m4 = 0.0;
        foreach ($residual as $e) {
            $selisih = $e - $rataRata;
            $m2 += $selisih ** 2;
            $m3 += $selisih ** 3;
            $m4 += $selisih ** 4;
        }
        $m2 /= $n;
        $m3 /= $n;
        $m4 /= $n;

        $skewness = $m3 / ($m2 ** 1.5);
        $kurtosis = $m4 / ($m2 ** 2);

        $statistikJb = ($n / 6) * ($skewness ** 2 + (($kurtosis - 3) ** 2) / 4);
        $pValue = Distribution::pValueChiSquare(2, $statistikJb);
        $normal = $pValue > 0.05;

        return [
            'statistik_jb' => $statistikJb,
            'skewness' => $skewness,
            'kurtosis' => $kurtosis,
            'nilai_kritis' => Distribution::chiSquareTabel(2),
            'p_value' => $pValue,
            'is_normal' => $normal,
        ];
    }
}
