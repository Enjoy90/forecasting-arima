<?php

namespace Tests\Unit\Support\Math;

use App\Support\Math\Distribution;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Uji nilai kritis distribusi dibandingkan tabel baku yang lazim dipakai
 * di lampiran buku statistik/ekonometrika, dipakai StationarityTest (ADF),
 * ArimaEstimator (uji-t), dan DiagnosticChecker (Ljung-Box).
 */
class DistributionTest extends TestCase
{
    public function test_z_score_titik_titik_baku(): void
    {
        // 95% dua-arah -> 1.96 ; 95% satu-arah (service level) -> 1.645
        $this->assertEqualsWithDelta(1.959964, Distribution::zScore(0.975), 1e-4);
        $this->assertEqualsWithDelta(1.644854, Distribution::zScore(0.95), 1e-4);
        $this->assertEqualsWithDelta(0.0, Distribution::zScore(0.5), 1e-6);
    }

    public function test_z_score_menolak_peluang_di_luar_rentang(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Distribution::zScore(1.0);
    }

    public function test_t_tabel_alpha_005_sesuai_tabel_baku(): void
    {
        $this->assertEqualsWithDelta(12.706, Distribution::tTabel(1), 1e-3);
        $this->assertEqualsWithDelta(2.228, Distribution::tTabel(10), 1e-3);
        $this->assertEqualsWithDelta(2.042, Distribution::tTabel(30), 1e-3);
        $this->assertEqualsWithDelta(1.960, Distribution::tTabel(999999), 1e-3);
    }

    public function test_t_tabel_interpolasi_di_antara_titik_tabel(): void
    {
        // df=35 berada di antara 30 (2.042) dan 40 (2.021).
        $nilai = Distribution::tTabel(35);
        $this->assertGreaterThan(2.021, $nilai);
        $this->assertLessThan(2.042, $nilai);
    }

    public function test_chi_square_tabel_mendekati_nilai_baku(): void
    {
        // Nilai kritis chi-square baku (alpha=0.05): df=1 -> 3.841, df=10 -> 18.307
        // Pendekatan Wilson-Hilferty kurang presisi di df kecil, jadi toleransi df=1 dilonggarkan.
        $this->assertEqualsWithDelta(3.841, Distribution::chiSquareTabel(1), 0.15);
        $this->assertEqualsWithDelta(18.307, Distribution::chiSquareTabel(10), 0.3);
    }

    public function test_nilai_kritis_adf_pada_titik_tabel(): void
    {
        $hasil = Distribution::nilaiKritisAdf(25);

        $this->assertEqualsWithDelta(-4.38, $hasil['1%'], 1e-9);
        $this->assertEqualsWithDelta(-3.60, $hasil['5%'], 1e-9);
        $this->assertEqualsWithDelta(-3.24, $hasil['10%'], 1e-9);
    }

    public function test_nilai_kritis_adf_sampel_kecil_dibulatkan_ke_titik_terkecil(): void
    {
        // n di bawah titik tabel terkecil (25) memakai nilai konservatif titik itu.
        $hasil = Distribution::nilaiKritisAdf(20);
        $this->assertEqualsWithDelta(-4.38, $hasil['1%'], 1e-9);
    }

    public function test_nilai_kritis_adf_interpolasi_antara_25_dan_50(): void
    {
        $hasil = Distribution::nilaiKritisAdf(37);

        // Titik tengah 25 (-3.60) dan 50 (-3.50) untuk kolom 5%.
        $this->assertEqualsWithDelta(-3.55, $hasil['5%'], 0.02);
    }

    public function test_p_value_t_konsisten_dengan_t_tabel(): void
    {
        // Statistik-t persis di titik kritis tTabel(df,0.05) harus punya p-value ~0.05.
        $kritis = Distribution::tTabel(15, 0.05);
        $this->assertEqualsWithDelta(0.05, Distribution::pValueT(15, $kritis), 1e-3);

        // Statistik-t jauh dari nol -> p-value harus sangat kecil.
        $this->assertLessThan(0.01, Distribution::pValueT(15, 5.0));
        // Statistik-t nol -> p-value harus mendekati 1.
        $this->assertEqualsWithDelta(1.0, Distribution::pValueT(15, 0.0), 1e-2);
    }

    public function test_p_value_chi_square_konsisten_dengan_chi_square_tabel(): void
    {
        $kritis = Distribution::chiSquareTabel(10, 0.05);
        $this->assertEqualsWithDelta(0.05, Distribution::pValueChiSquare(10, $kritis), 1e-3);
    }

    public function test_p_value_adf_konsisten_dengan_nilai_kritis_adf(): void
    {
        $kritis = Distribution::nilaiKritisAdf(30);
        $this->assertEqualsWithDelta(0.05, Distribution::pValueAdf($kritis['5%'], 30), 1e-3);
        $this->assertEqualsWithDelta(0.01, Distribution::pValueAdf($kritis['1%'], 30), 1e-3);

        // Statistik jauh lebih negatif dari titik 1% -> p-value harus di bawah 0.01.
        $this->assertLessThan(0.01, Distribution::pValueAdf($kritis['1%'] - 2, 30));
    }
}
