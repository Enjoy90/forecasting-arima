<?php

namespace Tests\Unit\Arima;

use App\Services\Arima\StationarityTest;
use Tests\TestCase;

/**
 * Uji ADF + differencing bertahap (docs/01 §5, Tahap 1: A2-A6). Data non-stasioner
 * memakai penjualan riil BJ-01 (36 bulan, trending, dari seeder) sebagai fixture
 * tetap, dipilih karena perilaku ADF pada deret sintetis kecil (n<40) cenderung
 * tidak dapat ditebak tanpa dijalankan (unit root test punya power rendah di sampel
 * kecil), sedangkan data riil ini sudah terverifikasi trending & tidak stasioner.
 */
class StationarityTestTest extends TestCase
{
    private StationarityTest $st;

    /** @var array<int, float> */
    private array $dataBj01 = [1044, 1228, 1266, 1222, 1146, 1132, 1068, 987, 951, 906, 1008, 1053,
        1180, 1405, 1533, 1452, 1487, 1421, 1217, 1271, 1203, 1106, 1274, 1205,
        1475, 1709, 1684, 1668, 1808, 1604, 1475, 1392, 1416, 1468, 1447, 1485];

    protected function setUp(): void
    {
        parent::setUp();
        $this->st = new StationarityTest();
    }

    public function test_differencing_ordo_1_sesuai_hitungan_manual(): void
    {
        // data=[5,8,4,9,3] -> diff: 8-5=3, 4-8=-4, 9-4=5, 3-9=-6
        $hasil = $this->st->differencing([5, 8, 4, 9, 3], 1);

        $this->assertEquals([3, -4, 5, -6], $hasil);
    }

    public function test_differencing_ordo_2_menerapkan_differencing_berulang(): void
    {
        // differencing 1x -> [3,-4,5,-6], differencing lagi -> [-7,9,-11]
        $hasil = $this->st->differencing([5, 8, 4, 9, 3], 2);

        $this->assertEquals([-7, 9, -11], $hasil);
    }

    public function test_uji_adf_data_trending_belum_stasioner(): void
    {
        $hasil = $this->st->ujiAdf($this->dataBj01);

        $this->assertFalse($hasil['is_stasioner']);
        $this->assertGreaterThan($hasil['nilai_kritis_5'], $hasil['adf_statistic']);
        $this->assertGreaterThanOrEqual(0.05, $hasil['p_value']);
    }

    public function test_uji_adf_mengembalikan_nilai_kritis_tiga_level(): void
    {
        $hasil = $this->st->ujiAdf($this->dataBj01);

        // Nilai kritis makin longgar (mendekati nol) seiring alpha membesar.
        $this->assertLessThan($hasil['nilai_kritis_5'], $hasil['nilai_kritis_1']);
        $this->assertLessThan($hasil['nilai_kritis_10'], $hasil['nilai_kritis_5']);
    }

    public function test_tentukan_ordo_differencing_sampai_stasioner(): void
    {
        $hasil = $this->st->tentukanOrdo($this->dataBj01, maxD: 2);

        $this->assertTrue($hasil['ordo_d'] >= 1);
        $this->assertLessThanOrEqual(2, $hasil['ordo_d']);

        // Deret akhir harus benar-benar stasioner menurut riwayat uji terakhirnya.
        $ujiTerakhir = end($hasil['riwayat']);
        $this->assertTrue($ujiTerakhir['is_stasioner']);

        // Jumlah data berkurang tepat sebanyak ordo_d karena tiap differencing membuang satu titik.
        $this->assertCount(count($this->dataBj01) - $hasil['ordo_d'], $hasil['deret_stasioner']);
    }

    public function test_tentukan_ordo_berhenti_di_max_d_walau_belum_stasioner(): void
    {
        // maxD=0 memaksa berhenti di differencing pertama (d=0) walau data trending belum stasioner.
        $hasil = $this->st->tentukanOrdo($this->dataBj01, maxD: 0);

        $this->assertSame(0, $hasil['ordo_d']);
        $this->assertCount(1, $hasil['riwayat']);
    }

    public function test_riwayat_mencatat_urutan_differencing_dari_nol(): void
    {
        $hasil = $this->st->tentukanOrdo($this->dataBj01, maxD: 2);

        foreach ($hasil['riwayat'] as $i => $ujiPadaD) {
            $this->assertSame($i, $ujiPadaD['differencing_ke']);
        }
    }
}
