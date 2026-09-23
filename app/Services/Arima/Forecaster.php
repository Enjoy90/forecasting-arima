<?php

namespace App\Services\Arima;

use App\Support\Math\Distribution;

/**
 * Forecast h-langkah ke depan + interval kepercayaan (docs/01 §5, Tahap 4).
 *
 * Model diestimasi ArimaEstimator pada deret W = (1-B)^d Z (sudah stasioner),
 * tapi forecast di sini langsung dihitung pada skala Z asli lewat representasi
 * gabungan phi(B)(1-B)^d, sehingga tidak perlu langkah "un-differencing"
 * terpisah: differencing dan AR digabung jadi satu polinomial (phi*),
 * lalu dipakai langsung sebagai persamaan rekursif atas Z.
 */
class Forecaster
{
    /**
     * @param  array<int, float>  $dataAsli  deret Z asli (belum differencing), histori penuh
     * @param  array<string, mixed>  $model  hasil ArimaEstimator::estimasi() pada deret hasil differencing d kali
     * @return array{forecast: array<int, float>, interval: array<int, array{batas_bawah: float, batas_atas: float}>, fitted_in_sample: array<int, float>, residual_in_sample: array<int, float>}
     */
    public function forecast(array $dataAsli, int $d, array $model, int $horizon = 6, float $tingkatKepercayaan = 0.95): array
    {
        [$konstanta, $phi, $theta] = $this->uraikanParameter($model['parameter']);
        $phiBintang = $this->kombinasikanAr($phi, $model['ordo_p'], $d);
        $orderAr = count($phiBintang);

        $n = count($dataAsli);
        $mulai = $orderAr + 1;
        if ($mulai > $n) {
            throw new \InvalidArgumentException('Data terlalu sedikit untuk orde model ini.');
        }

        [$fitted, $residual] = $this->rekonstruksiDalamSampel($dataAsli, $mulai, $n, $konstanta, $phiBintang, $theta);
        $forecast = $this->forecastKeDepan($dataAsli, $residual, $n, $horizon, $konstanta, $phiBintang, $theta);
        $interval = $this->hitungInterval($forecast, $phiBintang, $theta, $model['sigma_kuadrat'], $horizon, $tingkatKepercayaan);

        return [
            'forecast' => $forecast,
            'interval' => $interval,
            'fitted_in_sample' => $fitted,
            'residual_in_sample' => $residual,
        ];
    }

    /**
     * @return array{0: float, 1: array<int, float>, 2: array<int, float>}
     */
    private function uraikanParameter(array $parameter): array
    {
        $konstanta = 0.0;
        $phi = [];
        $theta = [];

        foreach ($parameter as $par) {
            match ($par['jenis']) {
                'KONSTANTA' => $konstanta = $par['koefisien'],
                'AR' => $phi[$par['lag']] = $par['koefisien'],
                'MA' => $theta[$par['lag']] = $par['koefisien'],
            };
        }

        return [$konstanta, $phi, $theta];
    }

    /**
     * Gabungkan phi(B) dan (1-B)^d jadi satu polinomial phi*(B) berorde p+d,
     * supaya persamaan ARIMA bisa langsung dipakai atas Z (bukan W).
     *
     * @param  array<int, float>  $phi
     * @return array<int, float> lag => koefisien phi*
     */
    private function kombinasikanAr(array $phi, int $p, int $d): array
    {
        $binomial = [];
        for ($k = 0; $k <= $d; $k++) {
            $binomial[$k] = $this->kombinasi($d, $k) * ((-1) ** $k);
        }

        $a = [0 => 1.0];
        for ($i = 1; $i <= $p; $i++) {
            $a[$i] = -($phi[$i] ?? 0.0);
        }

        $c = array_fill(0, $p + $d + 1, 0.0);
        foreach ($a as $i => $ai) {
            foreach ($binomial as $k => $bk) {
                $c[$i + $k] += $ai * $bk;
            }
        }

        $phiBintang = [];
        for ($i = 1; $i <= $p + $d; $i++) {
            $phiBintang[$i] = -$c[$i];
        }

        return $phiBintang;
    }

    private function kombinasi(int $n, int $k): float
    {
        if ($k === 0 || $k === $n) {
            return 1.0;
        }

        $hasil = 1.0;
        for ($i = 0; $i < $k; $i++) {
            $hasil *= ($n - $i) / ($i + 1);
        }

        return $hasil;
    }

    /**
     * @param  array<int, float>  $dataAsli
     * @param  array<int, float>  $phiBintang
     * @param  array<int, float>  $theta
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    private function rekonstruksiDalamSampel(array $dataAsli, int $mulai, int $n, float $konstanta, array $phiBintang, array $theta): array
    {
        $fitted = [];
        $residual = [];

        for ($t = $mulai; $t <= $n; $t++) {
            $prediksi = $konstanta;
            foreach ($phiBintang as $i => $koef) {
                $prediksi += $koef * $dataAsli[$t - $i - 1];
            }
            foreach ($theta as $j => $koef) {
                $prediksi -= $koef * ($residual[$t - $j] ?? 0.0);
            }

            $fitted[$t] = $prediksi;
            $residual[$t] = $dataAsli[$t - 1] - $prediksi;
        }

        return [$fitted, $residual];
    }

    /**
     * @param  array<int, float>  $dataAsli
     * @param  array<int, float>  $residual
     * @param  array<int, float>  $phiBintang
     * @param  array<int, float>  $theta
     * @return array<int, float> 0-based: index 0 = forecast h=1, dst
     */
    private function forecastKeDepan(array $dataAsli, array $residual, int $n, int $horizon, float $konstanta, array $phiBintang, array $theta): array
    {
        $forecast = [];

        for ($h = 1; $h <= $horizon; $h++) {
            $t = $n + $h;
            $prediksi = $konstanta;

            foreach ($phiBintang as $i => $koef) {
                $indeks = $t - $i;
                $nilai = $indeks <= $n ? $dataAsli[$indeks - 1] : $forecast[$indeks - $n - 1];
                $prediksi += $koef * $nilai;
            }
            foreach ($theta as $j => $koef) {
                $prediksi -= $koef * ($residual[$t - $j] ?? 0.0);
            }

            $forecast[$h - 1] = $prediksi;
        }

        return $forecast;
    }

    /**
     * Interval kepercayaan lewat psi-weight: Yhat +/- Z(alpha/2) * sigma * sqrt(SUM psi_j^2).
     *
     * @param  array<int, float>  $forecast
     * @param  array<int, float>  $phiBintang
     * @param  array<int, float>  $theta
     * @return array<int, array{batas_bawah: float, batas_atas: float}>
     */
    private function hitungInterval(array $forecast, array $phiBintang, array $theta, float $sigmaKuadrat, int $horizon, float $tingkatKepercayaan): array
    {
        $psi = $this->psiWeights($phiBintang, $theta, $horizon);
        $sigma = sqrt($sigmaKuadrat);
        $zAlpha = Distribution::zScore(1 - (1 - $tingkatKepercayaan) / 2);

        $interval = [];
        // psi_0 = 1 selalu (definisi representasi MA-tak-hingga, Wei), jadi
        // dijumlahkan lebih dulu sebelum psi_1. Tanpa ini, se(h=1) salah
        // dihitung seolah cuma psi_1 (dan untuk model tanpa AR/MA sama
        // sekali, psi_1..psi_h semuanya nol, jadi intervalnya kolaps jadi
        // nol persis alih-alih sebesar sigma).
        $sumPsiKuadrat = 1.0;
        for ($h = 1; $h <= $horizon; $h++) {
            $se = $sigma * sqrt($sumPsiKuadrat);

            $interval[$h - 1] = [
                'batas_bawah' => $forecast[$h - 1] - $zAlpha * $se,
                'batas_atas' => $forecast[$h - 1] + $zAlpha * $se,
            ];

            $sumPsiKuadrat += $psi[$h] ** 2;
        }

        return $interval;
    }

    /**
     * psi_0=1 ; psi_j = SUM phi*_i psi_(j-i) - theta_j (Wei, "Time Series Analysis").
     *
     * @param  array<int, float>  $phiBintang
     * @param  array<int, float>  $theta
     * @return array<int, float> lag 1..horizon
     */
    private function psiWeights(array $phiBintang, array $theta, int $horizon): array
    {
        $orderAr = count($phiBintang);
        $psi = [0 => 1.0];

        for ($j = 1; $j <= $horizon; $j++) {
            $nilai = 0.0;
            for ($i = 1; $i <= $orderAr; $i++) {
                $nilai += ($phiBintang[$i] ?? 0.0) * ($psi[$j - $i] ?? 0.0);
            }
            $nilai -= $theta[$j] ?? 0.0;
            $psi[$j] = $nilai;
        }

        unset($psi[0]);

        return $psi;
    }
}
