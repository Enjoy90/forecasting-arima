<?php

namespace App\Support\Math;

use InvalidArgumentException;
use RuntimeException;

/**
 * Operasi matriks murni PHP untuk kebutuhan regresi (uji ADF, estimasi ARIMA).
 * Matriks direpresentasikan sebagai array baris (array of array), vektor sebagai array datar.
 */
final class Matrix
{
    /**
     * @param  array<int, array<int, float>>  $matriks
     * @return array<int, array<int, float>>
     */
    public static function transpose(array $matriks): array
    {
        if ($matriks === []) {
            return [];
        }

        $hasil = [];
        foreach ($matriks as $i => $baris) {
            foreach ($baris as $j => $nilai) {
                $hasil[$j][$i] = $nilai;
            }
        }
        ksort($hasil);

        return array_map(function (array $baris) {
            ksort($baris);

            return array_values($baris);
        }, $hasil);
    }

    /**
     * @param  array<int, array<int, float>>  $a
     * @param  array<int, array<int, float>>  $b
     * @return array<int, array<int, float>>
     */
    public static function kali(array $a, array $b): array
    {
        $barisA = count($a);
        $kolomA = $barisA > 0 ? count($a[0]) : 0;
        $barisB = count($b);
        $kolomB = $barisB > 0 ? count($b[0]) : 0;

        if ($kolomA !== $barisB) {
            throw new InvalidArgumentException('Dimensi matriks tidak cocok untuk perkalian.');
        }

        $hasil = array_fill(0, $barisA, array_fill(0, $kolomB, 0.0));

        for ($i = 0; $i < $barisA; $i++) {
            for ($j = 0; $j < $kolomB; $j++) {
                $jumlah = 0.0;
                for ($k = 0; $k < $kolomA; $k++) {
                    $jumlah += $a[$i][$k] * $b[$k][$j];
                }
                $hasil[$i][$j] = $jumlah;
            }
        }

        return $hasil;
    }

    /**
     * Perkalian matriks x vektor kolom.
     *
     * @param  array<int, array<int, float>>  $matriks
     * @param  array<int, float>  $vektor
     * @return array<int, float>
     */
    public static function kaliVektor(array $matriks, array $vektor): array
    {
        $hasil = [];
        foreach ($matriks as $baris) {
            $jumlah = 0.0;
            foreach ($baris as $j => $nilai) {
                $jumlah += $nilai * $vektor[$j];
            }
            $hasil[] = $jumlah;
        }

        return $hasil;
    }

    /**
     * @return array<int, array<int, float>>
     */
    public static function identitas(int $n): array
    {
        $hasil = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $hasil[$i][$j] = $i === $j ? 1.0 : 0.0;
            }
        }

        return $hasil;
    }

    /**
     * Invers matriks bujursangkar memakai eliminasi Gauss-Jordan dengan pivot parsial.
     *
     * @param  array<int, array<int, float>>  $matriks
     * @return array<int, array<int, float>>
     */
    public static function invers(array $matriks): array
    {
        $n = count($matriks);
        foreach ($matriks as $baris) {
            if (count($baris) !== $n) {
                throw new InvalidArgumentException('Invers hanya berlaku untuk matriks bujursangkar.');
            }
        }

        // Gabungkan matriks asal dengan matriks identitas (augmented matrix).
        $gabungan = [];
        for ($i = 0; $i < $n; $i++) {
            $gabungan[$i] = array_merge($matriks[$i], self::identitas($n)[$i]);
        }

        for ($kolom = 0; $kolom < $n; $kolom++) {
            // Pivot parsial: pilih baris dengan nilai absolut terbesar pada kolom ini.
            $pivotBaris = $kolom;
            $pivotNilai = abs($gabungan[$kolom][$kolom]);
            for ($i = $kolom + 1; $i < $n; $i++) {
                if (abs($gabungan[$i][$kolom]) > $pivotNilai) {
                    $pivotBaris = $i;
                    $pivotNilai = abs($gabungan[$i][$kolom]);
                }
            }

            if ($pivotNilai < 1e-12) {
                throw new RuntimeException('Matriks singular, tidak memiliki invers.');
            }

            if ($pivotBaris !== $kolom) {
                [$gabungan[$kolom], $gabungan[$pivotBaris]] = [$gabungan[$pivotBaris], $gabungan[$kolom]];
            }

            $pivot = $gabungan[$kolom][$kolom];
            for ($j = 0; $j < 2 * $n; $j++) {
                $gabungan[$kolom][$j] /= $pivot;
            }

            for ($i = 0; $i < $n; $i++) {
                if ($i === $kolom) {
                    continue;
                }
                $faktor = $gabungan[$i][$kolom];
                if ($faktor === 0.0) {
                    continue;
                }
                for ($j = 0; $j < 2 * $n; $j++) {
                    $gabungan[$i][$j] -= $faktor * $gabungan[$kolom][$j];
                }
            }
        }

        $hasil = [];
        for ($i = 0; $i < $n; $i++) {
            $hasil[$i] = array_slice($gabungan[$i], $n, $n);
        }

        return $hasil;
    }

    /**
     * Regresi linear berganda memakai metode kuadrat terkecil (OLS): beta = (X'X)^-1 X'y.
     *
     * $x adalah matriks desain (tiap baris satu observasi, kolom pertama boleh berisi
     * konstanta 1 bila model memakai intersep). Mengembalikan koefisien, residual, standar
     * error tiap koefisien (untuk uji-t), dan ragam residual (sigma^2).
     *
     * @param  array<int, array<int, float>>  $x
     * @param  array<int, float>  $y
     * @return array{koefisien: array<int, float>, residual: array<int, float>, standard_error: array<int, float>, sigma_kuadrat: float, n: int, k: int}
     */
    public static function ols(array $x, array $y): array
    {
        $n = count($x);
        $k = $n > 0 ? count($x[0]) : 0;

        if ($n === 0 || $n !== count($y)) {
            throw new InvalidArgumentException('Jumlah baris X dan y harus sama dan tidak boleh kosong.');
        }
        if ($n <= $k) {
            throw new RuntimeException('Jumlah observasi harus lebih banyak daripada jumlah parameter.');
        }

        $xT = self::transpose($x);
        $xTx = self::kali($xT, $x);
        $xTxInv = self::invers($xTx);
        $xTy = self::kaliVektor($xT, $y);
        $koefisien = self::kaliVektor($xTxInv, $xTy);

        $prediksi = self::kaliVektor($x, $koefisien);
        $residual = [];
        $sse = 0.0;
        foreach ($y as $i => $nilaiAktual) {
            $residual[$i] = $nilaiAktual - $prediksi[$i];
            $sse += $residual[$i] ** 2;
        }

        $derajatBebas = $n - $k;
        $sigmaKuadrat = $sse / $derajatBebas;

        $standardError = [];
        for ($j = 0; $j < $k; $j++) {
            $standardError[$j] = sqrt($sigmaKuadrat * $xTxInv[$j][$j]);
        }

        return [
            'koefisien' => $koefisien,
            'residual' => $residual,
            'standard_error' => $standardError,
            'sigma_kuadrat' => $sigmaKuadrat,
            'n' => $n,
            'k' => $k,
        ];
    }
}
