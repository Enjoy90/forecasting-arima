<?php

namespace App\Support\Math;

/**
 * Tabel & fungsi distribusi statistik murni PHP: normal baku, t, chi-square,
 * dan nilai kritis uji Dickey-Fuller (ADF). Dipakai StationarityTest,
 * ArimaEstimator, dan DiagnosticChecker.
 */
final class Distribution
{
    /**
     * Kuantil distribusi normal baku (invers CDF) memakai algoritma rasional
     * Peter J. Acklam, akurat hingga ~1.15e-9.
     */
    public static function zScore(float $peluang): float
    {
        if ($peluang <= 0.0 || $peluang >= 1.0) {
            throw new \InvalidArgumentException('Peluang harus di antara 0 dan 1 (eksklusif).');
        }

        $a = [-3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02,
            1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00];
        $b = [-5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02,
            6.680131188771972e+01, -1.328068155288572e+01];
        $c = [-7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00,
            -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00];
        $d = [7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00,
            3.754408661907416e+00];

        $pRendah = 0.02425;
        $pTinggi = 1 - $pRendah;

        if ($peluang < $pRendah) {
            $q = sqrt(-2 * log($peluang));

            return ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5])
                / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
        }

        if ($peluang <= $pTinggi) {
            $q = $peluang - 0.5;
            $r = $q * $q;

            return ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q
                / ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1);
        }

        $q = sqrt(-2 * log(1 - $peluang));

        return -((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5])
            / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
    }

    /**
     * Nilai kritis distribusi t dua-arah pada derajat bebas tertentu.
     *
     * Untuk alpha=0.05 memakai tabel-t baku (df 1-30, 40, 60, 120, ~) yang lazim
     * dipakai di lampiran buku statistik. Untuk alpha lain dipakai pendekatan
     * Cornish-Fisher dari kuantil normal baku.
     */
    public static function tTabel(int $derajatBebas, float $alpha = 0.05): float
    {
        if ($derajatBebas < 1) {
            throw new \InvalidArgumentException('Derajat bebas minimal 1.');
        }

        if (abs($alpha - 0.05) < 1e-9) {
            $tabel = self::tabelTDuaArahAlpha005();
            if (array_key_exists($derajatBebas, $tabel)) {
                return $tabel[$derajatBebas];
            }
            if ($derajatBebas > 120) {
                return $tabel[999999];
            }
            // Interpolasi linear antar titik tabel terdekat.
            $kunci = array_keys($tabel);
            sort($kunci);
            foreach ($kunci as $i => $df) {
                if ($df > $derajatBebas) {
                    $dfBawah = $kunci[$i - 1];
                    $dfAtas = $df;
                    $proporsi = ($derajatBebas - $dfBawah) / ($dfAtas - $dfBawah);

                    return $tabel[$dfBawah] + $proporsi * ($tabel[$dfAtas] - $tabel[$dfBawah]);
                }
            }
        }

        return self::tKuantilCornishFisher($derajatBebas, 1 - $alpha / 2);
    }

    /**
     * Pendekatan Cornish-Fisher untuk kuantil distribusi t dari kuantil normal baku.
     */
    private static function tKuantilCornishFisher(int $v, float $peluang): float
    {
        $z = self::zScore($peluang);
        $z2 = $z ** 2;
        $z3 = $z ** 3;
        $z5 = $z ** 5;

        return $z
            + ($z3 + $z) / (4 * $v)
            + (5 * $z5 + 16 * $z3 + 3 * $z) / (96 * $v ** 2);
    }

    /**
     * @return array<int, float> derajat bebas => nilai kritis dua-arah alpha=0.05 (kunci 999999 = df tak hingga)
     */
    private static function tabelTDuaArahAlpha005(): array
    {
        return [
            1 => 12.706, 2 => 4.303, 3 => 3.182, 4 => 2.776, 5 => 2.571,
            6 => 2.447, 7 => 2.365, 8 => 2.306, 9 => 2.262, 10 => 2.228,
            11 => 2.201, 12 => 2.179, 13 => 2.160, 14 => 2.145, 15 => 2.131,
            16 => 2.120, 17 => 2.110, 18 => 2.101, 19 => 2.093, 20 => 2.086,
            21 => 2.080, 22 => 2.074, 23 => 2.069, 24 => 2.064, 25 => 2.060,
            26 => 2.056, 27 => 2.052, 28 => 2.048, 29 => 2.045, 30 => 2.042,
            40 => 2.021, 60 => 2.000, 120 => 1.980, 999999 => 1.960,
        ];
    }

    /**
     * Nilai kritis chi-square (sisi atas) memakai pendekatan Wilson-Hilferty.
     */
    public static function chiSquareTabel(int $derajatBebas, float $alpha = 0.05): float
    {
        if ($derajatBebas < 1) {
            throw new \InvalidArgumentException('Derajat bebas minimal 1.');
        }

        $z = self::zScore(1 - $alpha);
        $v = $derajatBebas;
        $inti = 1 - (2 / (9 * $v)) + $z * sqrt(2 / (9 * $v));

        return $v * ($inti ** 3);
    }

    /**
     * P-value dua-arah dari statistik-t pada derajat bebas tertentu, dicari
     * lewat bisection terhadap tTabel() sendiri (konsisten: keputusan
     * is_signifikan = p_value < alpha akan selalu sama dengan
     * |t_hitung| > tTabel(df, alpha)).
     */
    public static function pValueT(int $derajatBebas, float $statistikT): float
    {
        $target = abs($statistikT);
        $batasBawah = 1e-9;
        $batasAtas = 1 - 1e-9;

        for ($i = 0; $i < 60; $i++) {
            $tengah = ($batasBawah + $batasAtas) / 2;
            $nilai = self::tTabel($derajatBebas, $tengah);

            if ($nilai > $target) {
                $batasBawah = $tengah;
            } else {
                $batasAtas = $tengah;
            }
        }

        return ($batasBawah + $batasAtas) / 2;
    }

    /**
     * P-value uji ADF dicari lewat interpolasi/ekstrapolasi linear di antara
     * tiga titik nilaiKritisAdf() (1%, 5%, 10%). ADF tidak punya rumus tabel-t
     * kontinu seperti pValueT(), jadi ini pendekatan praktis, bukan probabilitas
     * ekor yang presisi, tapi cukup untuk keputusan "stasioner bila p < 0.05".
     */
    public static function pValueAdf(float $statistikAdf, int $n): float
    {
        $kritis = self::nilaiKritisAdf($n);
        $alpha = [0.01, 0.05, 0.10];
        $nilai = [$kritis['1%'], $kritis['5%'], $kritis['10%']];

        if ($statistikAdf <= $nilai[0]) {
            $slope = ($alpha[1] - $alpha[0]) / ($nilai[1] - $nilai[0]);

            return max(0.0001, min(1.0, $alpha[0] + $slope * ($statistikAdf - $nilai[0])));
        }

        if ($statistikAdf >= $nilai[2]) {
            $slope = ($alpha[2] - $alpha[1]) / ($nilai[2] - $nilai[1]);

            return max(0.0, min(0.9999, $alpha[2] + $slope * ($statistikAdf - $nilai[2])));
        }

        for ($i = 0; $i < 2; $i++) {
            if ($statistikAdf >= $nilai[$i] && $statistikAdf <= $nilai[$i + 1]) {
                $proporsi = ($statistikAdf - $nilai[$i]) / ($nilai[$i + 1] - $nilai[$i]);

                return $alpha[$i] + $proporsi * ($alpha[$i + 1] - $alpha[$i]);
            }
        }

        return 1.0;
    }

    /**
     * P-value sisi-atas dari statistik chi-square, dicari lewat bisection
     * terhadap chiSquareTabel() sendiri (konsisten dgn keputusan alpha=0.05).
     */
    public static function pValueChiSquare(int $derajatBebas, float $statistik): float
    {
        $batasBawah = 1e-9;
        $batasAtas = 1 - 1e-9;

        for ($i = 0; $i < 60; $i++) {
            $tengah = ($batasBawah + $batasAtas) / 2;
            $nilai = self::chiSquareTabel($derajatBebas, $tengah);

            if ($nilai > $statistik) {
                $batasBawah = $tengah;
            } else {
                $batasAtas = $tengah;
            }
        }

        return ($batasBawah + $batasAtas) / 2;
    }

    /**
     * Nilai kritis uji Dickey-Fuller (model dengan konstanta & tren, sesuai
     * spesifikasi ADF pada docs/01) berdasarkan tabel Dickey-Fuller yang lazim
     * dipakai pada buku ekonometrika (mis. Gujarati, "Basic Econometrics"),
     * diinterpolasi linear menurut ukuran sampel n.
     *
     * @return array{"1%": float, "5%": float, "10%": float}
     */
    public static function nilaiKritisAdf(int $n): array
    {
        $tabel = [
            25 => ['1%' => -4.38, '5%' => -3.60, '10%' => -3.24],
            50 => ['1%' => -4.15, '5%' => -3.50, '10%' => -3.18],
            100 => ['1%' => -4.04, '5%' => -3.45, '10%' => -3.15],
            250 => ['1%' => -3.99, '5%' => -3.43, '10%' => -3.13],
            500 => ['1%' => -3.98, '5%' => -3.42, '10%' => -3.13],
            100000 => ['1%' => -3.96, '5%' => -3.41, '10%' => -3.12],
        ];

        $ukuran = array_keys($tabel);

        if ($n <= $ukuran[0]) {
            return $tabel[$ukuran[0]];
        }
        if ($n >= end($ukuran)) {
            return $tabel[end($ukuran)];
        }

        foreach ($ukuran as $i => $nAtas) {
            if ($nAtas >= $n) {
                $nBawah = $ukuran[$i - 1];
                $proporsi = ($n - $nBawah) / ($nAtas - $nBawah);

                $hasil = [];
                foreach (['1%', '5%', '10%'] as $level) {
                    $hasil[$level] = $tabel[$nBawah][$level]
                        + $proporsi * ($tabel[$nAtas][$level] - $tabel[$nBawah][$level]);
                }

                return $hasil;
            }
        }

        return $tabel[end($ukuran)];
    }
}
