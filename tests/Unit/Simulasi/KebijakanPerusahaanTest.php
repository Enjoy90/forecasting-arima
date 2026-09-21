<?php

namespace Tests\Unit\Simulasi;

use App\Services\Simulasi\KebijakanPerusahaan;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Uji tiga metode pembanding kebijakan perusahaan (docs/01 §7.2) dibandingkan
 * hitungan manual.
 */
class KebijakanPerusahaanTest extends TestCase
{
    private KebijakanPerusahaan $kebijakan;

    /** @var array<int, float> */
    private array $histori = [5, 10, 20, 30]; // 3 nilai terakhir dipakai lookback

    /** @var array<int, float> */
    private array $simulasi = [40, 50, 60];

    protected function setUp(): void
    {
        parent::setUp();
        $this->kebijakan = new KebijakanPerusahaan();
    }

    public function test_naif_bulan_lalu_sesuai_hitungan_manual(): void
    {
        // rencana(t) = permintaan_aktual(t-1); bulan pertama mundur ke histori.
        $hasil = $this->kebijakan->hitungSemua($this->histori, $this->simulasi, 'naif_bulan_lalu');

        $this->assertEquals([30.0, 40.0, 50.0], $hasil);
    }

    public function test_rata_rata_bergerak_sesuai_hitungan_manual(): void
    {
        // i=0: avg(10,20,30)=20 ; i=1: avg(20,30,40)=30 ; i=2: avg(30,40,50)=40
        $hasil = $this->kebijakan->hitungSemua($this->histori, $this->simulasi, 'rata_rata_bergerak');

        $this->assertEqualsWithDelta([20.0, 30.0, 40.0], $hasil, 1e-9);
    }

    public function test_produksi_aktual_mengembalikan_data_yang_diberikan(): void
    {
        $data = array_fill(0, 12, 99.0);
        $hasil = $this->kebijakan->hitungSemua(array_fill(0, 24, 1.0), array_fill(0, 12, 1.0), 'produksi_aktual', $data);

        $this->assertSame($data, $hasil);
    }

    public function test_produksi_aktual_menolak_data_kosong(): void
    {
        $this->expectException(RuntimeException::class);
        $this->kebijakan->hitungSemua($this->histori, $this->simulasi, 'produksi_aktual', null);
    }

    public function test_produksi_aktual_menolak_jumlah_bulan_salah(): void
    {
        $this->expectException(RuntimeException::class);
        $this->kebijakan->hitungSemua($this->histori, $this->simulasi, 'produksi_aktual', [1, 2, 3]);
    }

    public function test_metode_tidak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->kebijakan->hitungSemua($this->histori, $this->simulasi, 'metode_asing');
    }
}
