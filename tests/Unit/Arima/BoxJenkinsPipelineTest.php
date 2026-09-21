<?php

namespace Tests\Unit\Arima;

use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Services\Arima\BoxJenkinsPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Uji orkestrator 4 tahap Box-Jenkins end-to-end (docs/01 §5): dari data
 * penjualan mentah sampai baris tersimpan di seluruh tabel peramalan.
 * Fixture memakai 36 nilai penjualan riil BJ-01 (sudah diverifikasi manual
 * lewat tinker: model terpilih ARIMA(3,2,0), MAPE ~6.42%) supaya angka yang
 * diasersikan bukan tebakan.
 */
class BoxJenkinsPipelineTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int> */
    private array $dataBj01 = [1044, 1228, 1266, 1222, 1146, 1132, 1068, 987, 951, 906, 1008, 1053,
        1180, 1405, 1533, 1452, 1487, 1421, 1217, 1271, 1203, 1106, 1274, 1205,
        1475, 1709, 1684, 1668, 1808, 1604, 1475, 1392, 1416, 1468, 1447, 1485];

    private function barangDenganPenjualan(array $nilaiBulanan): Barang
    {
        $barang = Barang::factory()->barangJadi()->create();
        $bulan = \Illuminate\Support\Carbon::parse('2023-01-01');

        foreach ($nilaiBulanan as $jumlah) {
            $penjualan = Penjualan::create([
                'no_faktur' => 'FK-'.uniqid(),
                'tanggal_penjualan' => $bulan->format('Y-m-15'),
                'total_harga' => $jumlah * 10000,
            ]);
            DetailPenjualan::create([
                'penjualan_id' => $penjualan->id,
                'barang_id' => $barang->id,
                'jumlah' => $jumlah,
                'harga_satuan' => 10000,
                'subtotal' => $jumlah * 10000,
            ]);
            $bulan->addMonth();
        }

        (new \App\Services\Arima\TimeSeriesBuilder())->bangun($barang);

        return $barang;
    }

    public function test_pipeline_menghasilkan_peramalan_lengkap_dari_data_riil(): void
    {
        $barang = $this->barangDenganPenjualan($this->dataBj01);

        $peramalan = (new BoxJenkinsPipeline())->jalankan($barang, horizon: 6);

        $this->assertSame(3, $peramalan->ordo_p);
        $this->assertSame(2, $peramalan->ordo_d);
        $this->assertSame(0, $peramalan->ordo_q);
        $this->assertSame('draft', $peramalan->status);
        $this->assertSame('Sangat Baik', $peramalan->kategori_akurasi);
        $this->assertEqualsWithDelta(6.42, (float) $peramalan->mape, 0.1);
    }

    public function test_pipeline_menyimpan_baris_ke_seluruh_tabel_terkait(): void
    {
        $barang = $this->barangDenganPenjualan($this->dataBj01);

        $peramalan = (new BoxJenkinsPipeline())->jalankan($barang, horizon: 6);

        // d=2 -> 3 percobaan uji ADF (d=0,1,2).
        $this->assertSame(3, $peramalan->ujiStasioneritas()->count());

        // Grid default p=0..3, q=0..3 -> 16 kombinasi, semua cukup data.
        $this->assertSame(16, $peramalan->kandidatModel()->count());
        $this->assertSame(1, $peramalan->kandidatModel()->where('is_terpilih', true)->count());

        // ARIMA(3,2,0) terpilih -> parameter: konstanta + AR(1) + AR(2) + AR(3) = 4 baris.
        $this->assertSame(4, $peramalan->parameterModel()->count());

        // hasil_peramalan: in_sample sebanyak n-(p+d)=36-5=31, forecast sebanyak horizon=6.
        $this->assertSame(31, $peramalan->hasil()->where('tipe', 'in_sample')->count());
        $this->assertSame(6, $peramalan->hasil()->where('tipe', 'forecast')->count());

        // Baris forecast tidak boleh punya nilai aktual; baris in_sample harus punya.
        $contohForecast = $peramalan->hasil()->where('tipe', 'forecast')->first();
        $this->assertNull($contohForecast->nilai_aktual);

        $contohInSample = $peramalan->hasil()->where('tipe', 'in_sample')->first();
        $this->assertNotNull($contohInSample->nilai_aktual);
    }

    public function test_kandidat_terpilih_tercatat_hasil_diagnostic_ljung_box(): void
    {
        $barang = $this->barangDenganPenjualan($this->dataBj01);

        $peramalan = (new BoxJenkinsPipeline())->jalankan($barang, horizon: 6);

        $terpilih = $peramalan->kandidatModel()->where('is_terpilih', true)->first();
        $this->assertNotNull($terpilih->lolos_ljung_box);

        // Kandidat lain tidak pernah didiagnosa Ljung-Box (bukan false, tapi memang belum diuji).
        $lainnya = $peramalan->kandidatModel()->where('is_terpilih', false)->first();
        $this->assertFalse((bool) $lainnya->lolos_ljung_box);
    }

    public function test_menolak_data_kurang_dari_24_periode(): void
    {
        $barang = $this->barangDenganPenjualan(array_slice($this->dataBj01, 0, 20));

        $this->expectException(RuntimeException::class);
        (new BoxJenkinsPipeline())->jalankan($barang);
    }
}
