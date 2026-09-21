<?php

namespace App\Services\Arima;

use App\Support\Math\Distribution;
use App\Support\Math\Matrix;

/**
 * Uji stasioneritas Augmented Dickey-Fuller + differencing bertahap
 * (docs/01 §5, Tahap 1: A2-A6).
 *
 * Regresi ADF: dZt = alpha + beta*t + gamma*Z(t-1) + SUM delta_i*dZ(t-i) + et
 * H0: gamma = 0 (ada unit root / tidak stasioner).
 */
class StationarityTest
{
    /**
     * Differencing ordo-1 diterapkan berulang: W_t = Z_t - Z_(t-1).
     *
     * @param  array<int, float>  $data
     * @return array<int, float>
     */
    public function differencing(array $data, int $ordo = 1): array
    {
        for ($i = 0; $i < $ordo; $i++) {
            $baru = [];
            for ($t = 1; $t < count($data); $t++) {
                $baru[] = $data[$t] - $data[$t - 1];
            }
            $data = $baru;
        }

        return array_values($data);
    }

    /**
     * Uji ADF dengan konstanta + tren + $jumlahLag suku dZ(t-i) tambahan.
     *
     * @param  array<int, float>  $data
     * @return array{rata_rata: float, standar_deviasi: float, adf_statistic: float, nilai_kritis_1: float, nilai_kritis_5: float, nilai_kritis_10: float, p_value: float, is_stasioner: bool, kesimpulan: string}
     */
    public function ujiAdf(array $data, int $jumlahLag = 1): array
    {
        $n = count($data);
        $z = fn (int $t): float => $data[$t - 1];
        $d = fn (int $t): float => $data[$t - 1] - $data[$t - 2];

        $mulai = $jumlahLag + 2;
        if ($mulai > $n) {
            throw new \InvalidArgumentException('Data terlalu sedikit untuk uji ADF dengan jumlah lag ini.');
        }

        $x = [];
        $y = [];
        for ($t = $mulai; $t <= $n; $t++) {
            $baris = [1.0, (float) $t, $z($t - 1)];
            for ($i = 1; $i <= $jumlahLag; $i++) {
                $baris[] = $d($t - $i);
            }
            $x[] = $baris;
            $y[] = $d($t);
        }

        $hasilOls = Matrix::ols($x, $y);
        $gamma = $hasilOls['koefisien'][2];
        $seGamma = $hasilOls['standard_error'][2];
        $statistikAdf = $gamma / $seGamma;

        $nObs = $hasilOls['n'];
        $kritis = Distribution::nilaiKritisAdf($nObs);
        $pValue = Distribution::pValueAdf($statistikAdf, $nObs);
        $stasioner = $pValue < 0.05;

        $rataRata = array_sum($data) / $n;
        $variansi = 0.0;
        foreach ($data as $nilai) {
            $variansi += ($nilai - $rataRata) ** 2;
        }
        $standarDeviasi = sqrt($variansi / ($n - 1));

        return [
            'rata_rata' => $rataRata,
            'standar_deviasi' => $standarDeviasi,
            'adf_statistic' => $statistikAdf,
            'nilai_kritis_1' => $kritis['1%'],
            'nilai_kritis_5' => $kritis['5%'],
            'nilai_kritis_10' => $kritis['10%'],
            'p_value' => $pValue,
            'is_stasioner' => $stasioner,
            'kesimpulan' => $stasioner
                ? sprintf('Stasioner (statistik ADF %.4f < nilai kritis 5%% %.4f, p-value %.4f).', $statistikAdf, $kritis['5%'], $pValue)
                : sprintf('Belum stasioner (statistik ADF %.4f >= nilai kritis 5%% %.4f, p-value %.4f).', $statistikAdf, $kritis['5%'], $pValue),
        ];
    }

    /**
     * Uji ADF berulang: differencing d=0,1,2,... sampai stasioner atau maxD tercapai
     * (docs/01 §5, alur A4-A6).
     *
     * @param  array<int, float>  $data
     * @return array{ordo_d: int, deret_stasioner: array<int, float>, riwayat: array<int, array<string, mixed>>}
     */
    public function tentukanOrdo(array $data, int $maxD = 2, int $jumlahLag = 1): array
    {
        $deret = $data;
        $riwayat = [];

        for ($d = 0; $d <= $maxD; $d++) {
            $uji = $this->ujiAdf($deret, $jumlahLag);
            $riwayat[] = ['differencing_ke' => $d] + $uji;

            if ($uji['is_stasioner'] || $d === $maxD) {
                return [
                    'ordo_d' => $d,
                    'deret_stasioner' => $deret,
                    'riwayat' => $riwayat,
                ];
            }

            $deret = $this->differencing($deret, 1);
        }

        return [
            'ordo_d' => $maxD,
            'deret_stasioner' => $deret,
            'riwayat' => $riwayat,
        ];
    }
}
