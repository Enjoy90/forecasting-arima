<?php

namespace App\Services\Arima;

use App\Support\Math\Distribution;
use App\Support\Math\Matrix;
use RuntimeException;
use Throwable;

/**
 * Estimasi ARIMA(p,d,q) pada deret yang SUDAH stasioner (differencing
 * dilakukan di luar kelas ini oleh StationarityTest) memakai Conditional
 * Least Squares dua-langkah gaya Hannan-Rissanen: langkah 1 fit AR orde
 * tinggi untuk memperoleh proksi residual white-noise, langkah 2 regresi
 * akhir Z_t atas lag Z (AR) dan lag residual proksi (MA) (docs/01 §5, Tahap 2).
 *
 * CLS penuh untuk model dengan komponen MA sebetulnya nonlinear (residual
 * MA saling bergantung), jadi dua-langkah Hannan-Rissanen dipakai sebagai
 * pendekatan yang bisa diimplementasikan murni lewat regresi OLS berulang
 * (Matrix::ols), tanpa solver nonlinear.
 */
class ArimaEstimator
{
    /**
     * @param  array<int, float>  $data  deret yang sudah stasioner (sudah di-differencing)
     * @return array{ordo_p: int, ordo_q: int, parameter: array<int, array<string, mixed>>, aic: float, bic: float, sse: float, sigma_kuadrat: float, semua_signifikan: bool, n: int, k: int}
     */
    public function estimasi(array $data, int $p, int $q): array
    {
        $n = count($data);
        $z = fn (int $t): float => $data[$t - 1];

        $residualProksi = $q > 0 ? $this->residualProksi($data, $p, $q) : [];

        $mulai = $p + 1;
        if ($mulai > $n) {
            throw new RuntimeException('Data terlalu sedikit untuk orde p yang diminta.');
        }

        $x = [];
        $y = [];
        for ($t = $mulai; $t <= $n; $t++) {
            $baris = [1.0];
            for ($i = 1; $i <= $p; $i++) {
                $baris[] = $z($t - $i);
            }
            for ($j = 1; $j <= $q; $j++) {
                $baris[] = $residualProksi[$t - $j] ?? 0.0;
            }
            $x[] = $baris;
            $y[] = $z($t);
        }

        $hasil = Matrix::ols($x, $y);
        $nObs = $hasil['n'];
        $k = $hasil['k'];
        $derajatBebas = $nObs - $k;

        $sse = 0.0;
        foreach ($hasil['residual'] as $e) {
            $sse += $e ** 2;
        }
        $sigmaKuadratMle = $sse / $nObs;

        $parameter = [];
        $semuaSignifikan = true;

        foreach ($hasil['koefisien'] as $idx => $koef) {
            if ($idx === 0) {
                $jenis = 'KONSTANTA';
                $lag = 0;
                $nilaiKoef = $koef;
            } elseif ($idx <= $p) {
                $jenis = 'AR';
                $lag = $idx;
                $nilaiKoef = $koef;
            } else {
                // Konvensi docs: theta(B) = 1 - theta_1 B - ... - theta_q B^q,
                // sedangkan regresi ini memakai +koefisien pada residual proksi,
                // jadi theta_j = -koefisien hasil regresi.
                $jenis = 'MA';
                $lag = $idx - $p;
                $nilaiKoef = -$koef;
            }

            $se = $hasil['standard_error'][$idx];
            $tHitung = $koef / $se;
            $tTabel = Distribution::tTabel($derajatBebas);
            $pValue = Distribution::pValueT($derajatBebas, $tHitung);
            $signifikan = $pValue < 0.05;
            $semuaSignifikan = $semuaSignifikan && $signifikan;

            $parameter[] = [
                'jenis' => $jenis,
                'lag' => $lag,
                'koefisien' => $nilaiKoef,
                'standard_error' => $se,
                't_hitung' => $tHitung,
                't_tabel' => $tTabel,
                'p_value' => $pValue,
                'is_signifikan' => $signifikan,
            ];
        }

        return [
            'ordo_p' => $p,
            'ordo_q' => $q,
            'parameter' => $parameter,
            'aic' => $nObs * log($sigmaKuadratMle) + 2 * $k,
            'bic' => $nObs * log($sigmaKuadratMle) + $k * log($nObs),
            'sse' => $sse,
            'sigma_kuadrat' => $sigmaKuadratMle,
            'semua_signifikan' => $semuaSignifikan,
            'n' => $nObs,
            'k' => $k,
        ];
    }

    /**
     * Grid search p=0..pMax, q=0..qMax. Kombinasi yang datanya tidak cukup
     * (n observasi <= jumlah parameter) dilewati. Model terpilih = AIC
     * terkecil di antara yang seluruh parameternya signifikan; bila tidak
     * ada satu pun yang seluruhnya signifikan, dipilih AIC terkecil keseluruhan.
     *
     * @param  array<int, float>  $data  deret yang sudah stasioner
     * @return array<int, array<string, mixed>>
     */
    public function gridSearch(array $data, int $pMax = 3, int $qMax = 3): array
    {
        $kandidat = [];

        for ($p = 0; $p <= $pMax; $p++) {
            for ($q = 0; $q <= $qMax; $q++) {
                try {
                    $kandidat[] = $this->estimasi($data, $p, $q);
                } catch (Throwable) {
                    continue;
                }
            }
        }

        if ($kandidat === []) {
            return [];
        }

        $kandidatSignifikan = array_filter($kandidat, fn ($k) => $k['semua_signifikan']);
        $sumberIndex = array_keys($kandidatSignifikan !== [] ? $kandidatSignifikan : $kandidat);

        $terpilihIndex = null;
        $aicTerkecil = null;
        foreach ($sumberIndex as $i) {
            if ($aicTerkecil === null || $kandidat[$i]['aic'] < $aicTerkecil) {
                $aicTerkecil = $kandidat[$i]['aic'];
                $terpilihIndex = $i;
            }
        }

        foreach ($kandidat as $i => &$k) {
            $k['is_terpilih'] = $i === $terpilihIndex;
        }
        unset($k);

        return array_values($kandidat);
    }

    /**
     * Langkah 1 Hannan-Rissanen: fit AR orde tinggi untuk memperoleh residual
     * sebagai proksi white noise, dipakai sebagai regressor lag MA di langkah 2.
     * Residual sebelum data cukup (presample) dianggap 0, sesuai konvensi CLS.
     *
     * @param  array<int, float>  $data
     * @return array<int, float> t (1-based) => residual
     */
    private function residualProksi(array $data, int $p, int $q): array
    {
        $n = count($data);
        $m = max($p, $q) + 2;
        $m = min($m, intdiv($n - 2, 2));
        $m = max($m, 1);

        $z = fn (int $t): float => $data[$t - 1];

        $x = [];
        $y = [];
        $daftarT = [];
        for ($t = $m + 1; $t <= $n; $t++) {
            $baris = [1.0];
            for ($i = 1; $i <= $m; $i++) {
                $baris[] = $z($t - $i);
            }
            $x[] = $baris;
            $y[] = $z($t);
            $daftarT[] = $t;
        }

        $hasil = Matrix::ols($x, $y);

        $residual = [];
        foreach ($daftarT as $idx => $t) {
            $residual[$t] = $hasil['residual'][$idx];
        }

        return $residual;
    }
}
