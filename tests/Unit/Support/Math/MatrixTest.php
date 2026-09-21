<?php

namespace Tests\Unit\Support\Math;

use App\Support\Math\Matrix;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Uji operasi matriks dibandingkan hitungan manual, dipakai regresi OLS
 * pada StationarityTest (ADF) dan ArimaEstimator.
 */
class MatrixTest extends TestCase
{
    public function test_transpose_menukar_baris_dan_kolom(): void
    {
        $matriks = [
            [1, 2, 3],
            [4, 5, 6],
        ];

        $this->assertSame([
            [1, 4],
            [2, 5],
            [3, 6],
        ], Matrix::transpose($matriks));
    }

    public function test_kali_matriks_sesuai_hitungan_manual(): void
    {
        $a = [[1, 2], [3, 4]];
        $b = [[5, 6], [7, 8]];

        // [1*5+2*7, 1*6+2*8] = [19, 22] ; [3*5+4*7, 3*6+4*8] = [43, 50]
        $this->assertSame([
            [19.0, 22.0],
            [43.0, 50.0],
        ], Matrix::kali($a, $b));
    }

    public function test_kali_vektor(): void
    {
        $matriks = [[2, 0], [0, 3]];
        $vektor = [4, 5];

        $this->assertSame([8.0, 15.0], Matrix::kaliVektor($matriks, $vektor));
    }

    public function test_invers_matriks_2x2(): void
    {
        // Invers [[4,7],[2,6]] = 1/10 * [[6,-7],[-2,4]]
        $matriks = [[4, 7], [2, 6]];
        $invers = Matrix::invers($matriks);

        $this->assertEqualsWithDelta(0.6, $invers[0][0], 1e-9);
        $this->assertEqualsWithDelta(-0.7, $invers[0][1], 1e-9);
        $this->assertEqualsWithDelta(-0.2, $invers[1][0], 1e-9);
        $this->assertEqualsWithDelta(0.4, $invers[1][1], 1e-9);
    }

    public function test_invers_matriks_singular_melempar_exception(): void
    {
        $this->expectException(RuntimeException::class);
        Matrix::invers([[1, 2], [2, 4]]);
    }

    public function test_ols_menghasilkan_koefisien_regresi_sederhana_yang_tepat(): void
    {
        // y = 2 + 3x, tanpa error sama sekali -> koefisien harus persis [2, 3].
        $x = [[1, 1], [1, 2], [1, 3], [1, 4], [1, 5]];
        $y = [5, 8, 11, 14, 17];

        $hasil = Matrix::ols($x, $y);

        $this->assertEqualsWithDelta(2.0, $hasil['koefisien'][0], 1e-9);
        $this->assertEqualsWithDelta(3.0, $hasil['koefisien'][1], 1e-9);
        $this->assertEqualsWithDelta(0.0, $hasil['sigma_kuadrat'], 1e-9);
        $this->assertSame(5, $hasil['n']);
        $this->assertSame(2, $hasil['k']);
    }

    public function test_ols_menolak_jumlah_observasi_kurang_dari_parameter(): void
    {
        $this->expectException(RuntimeException::class);
        Matrix::ols([[1, 1]], [5]);
    }

    public function test_ols_menolak_dimensi_x_dan_y_tidak_sama(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Matrix::ols([[1, 1], [1, 2]], [5]);
    }
}
