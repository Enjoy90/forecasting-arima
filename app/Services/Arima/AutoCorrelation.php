<?php

namespace App\Services\Arima;

use App\Support\Math\Distribution;

/**
 * ACF (autokorelasi) & PACF (autokorelasi parsial via rekursi Durbin-Levinson)
 * untuk tahap identifikasi Box-Jenkins (docs/01 §5, Tahap 1: A7).
 */
class AutoCorrelation
{
    /**
     * ACF lag 1..maxLag: r_k = SUM (Zt-Zbar)(Z(t+k)-Zbar) / SUM (Zt-Zbar)^2.
     *
     * @param  array<int, float>  $data
     * @return array<int, float> lag => nilai ACF
     */
    public function acf(array $data, int $maxLag): array
    {
        $n = count($data);
        if ($maxLag >= $n) {
            throw new \InvalidArgumentException('maxLag harus lebih kecil dari jumlah data.');
        }

        $rataRata = array_sum($data) / $n;

        $penyebut = 0.0;
        foreach ($data as $z) {
            $penyebut += ($z - $rataRata) ** 2;
        }

        $hasil = [];
        for ($k = 1; $k <= $maxLag; $k++) {
            $pembilang = 0.0;
            for ($t = 0; $t < $n - $k; $t++) {
                $pembilang += ($data[$t] - $rataRata) * ($data[$t + $k] - $rataRata);
            }
            $hasil[$k] = $penyebut > 0.0 ? $pembilang / $penyebut : 0.0;
        }

        return $hasil;
    }

    /**
     * PACF lag 1..maxLag lewat rekursi Durbin-Levinson dari nilai ACF.
     *
     * phi_kk = (r_k - SUM phi_(k-1,j) r_(k-j)) / (1 - SUM phi_(k-1,j) r_j)
     * phi_kj = phi_(k-1,j) - phi_kk * phi_(k-1,k-j)   untuk j=1..k-1
     *
     * @param  array<int, float>  $data
     * @return array<int, float> lag => nilai PACF (phi_kk)
     */
    public function pacf(array $data, int $maxLag): array
    {
        $acf = $this->acf($data, $maxLag);
        $r = [0 => 1.0] + $acf;

        $phi = [];
        $pacf = [];

        for ($k = 1; $k <= $maxLag; $k++) {
            if ($k === 1) {
                $phi[1][1] = $r[1];
                $pacf[1] = $phi[1][1];

                continue;
            }

            $pembilang = $r[$k];
            $penyebut = 1.0;
            for ($j = 1; $j <= $k - 1; $j++) {
                $pembilang -= $phi[$k - 1][$j] * $r[$k - $j];
                $penyebut -= $phi[$k - 1][$j] * $r[$j];
            }

            $phi[$k][$k] = $penyebut != 0.0 ? $pembilang / $penyebut : 0.0;

            for ($j = 1; $j <= $k - 1; $j++) {
                $phi[$k][$j] = $phi[$k - 1][$j] - $phi[$k][$k] * $phi[$k - 1][$k - $j];
            }

            $pacf[$k] = $phi[$k][$k];
        }

        return $pacf;
    }

    /**
     * Batas signifikansi ACF/PACF: +/- Z(0.975) / sqrt(n) (docs/01 §5.1).
     */
    public function batasSignifikansi(int $n): float
    {
        return Distribution::zScore(0.975) / sqrt($n);
    }

    public function isSignifikan(float $nilai, int $n): bool
    {
        return abs($nilai) > $this->batasSignifikansi($n);
    }
}
