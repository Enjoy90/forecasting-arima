<?php

namespace Tests\Unit\Simulasi;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Models\TahapanProduksi;
use App\Services\Arima\TimeSeriesBuilder;
use App\Services\Simulasi\SimulationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

/**
 * Uji orkestrator backtesting end-to-end (docs/01 §7) -- tolak ukur utama
 * keberhasilan sistem. Fixture memakai rantai BOM 2 level + 36 bulan
 * penjualan riil BJ-01 (sama seperti dipakai BoxJenkinsPipelineTest &
 * TargetProduksiPlannerTest), sudah diverifikasi manual lewat tinker
 * sebelum dikunci sebagai nilai regresi di sini.
 */
class SimulationRunnerTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int> */
    private array $dataBj01 = [1044, 1228, 1266, 1222, 1146, 1132, 1068, 987, 951, 906, 1008, 1053,
        1180, 1405, 1533, 1452, 1487, 1421, 1217, 1271, 1203, 1106, 1274, 1205,
        1475, 1709, 1684, 1668, 1808, 1604, 1475, 1392, 1416, 1468, 1447, 1485];

    /** @return array{barangJadi: Barang} */
    private function skenario(): array
    {
        $tahapan = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 1000]);
        $barangJadi = Barang::factory()->barangJadi()->create(['service_level' => 95]);
        $bahanBaku = Barang::factory()->create(['lead_time_hari' => 9]);

        $bom = Bom::create([
            'kode_bom' => 'BOM-UJI', 'nama_bom' => 'Resep', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $tahapan->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bom->detail()->create(['barang_id' => $bahanBaku->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $bulan = Carbon::parse('2023-01-01');
        foreach ($this->dataBj01 as $jumlah) {
            $penjualan = Penjualan::create([
                'no_faktur' => 'FK-'.uniqid(),
                'tanggal_penjualan' => $bulan->format('Y-m-15'),
                'total_harga' => $jumlah * 10000,
            ]);
            DetailPenjualan::create([
                'penjualan_id' => $penjualan->id, 'barang_id' => $barangJadi->id,
                'jumlah' => $jumlah, 'harga_satuan' => 10000, 'subtotal' => $jumlah * 10000,
            ]);
            $bulan->addMonth();
        }
        (new TimeSeriesBuilder())->bangun($barangJadi);

        return compact('barangJadi');
    }

    public function test_simulasi_menghasilkan_metrik_yang_dapat_direproduksi(): void
    {
        ['barangJadi' => $barangJadi] = $this->skenario();

        $sim = (new SimulationRunner())->jalankan(
            barang: $barangJadi,
            metodePembanding: 'naif_bulan_lalu',
            stokAwalSimulasi: 200,
            biayaSimpanPerUnit: 500,
            biayaStockoutPerUnit: 2000,
        );

        // Regression-lock: dikonfirmasi via tinker (fixture identik) sebelum dikunci di sini.
        $this->assertEqualsWithDelta(2071.0, (float) $sim->pb_total_stockout_unit, 0.5);
        $this->assertEqualsWithDelta(544.0, (float) $sim->pb_total_overstock_unit, 0.5);
        $this->assertEqualsWithDelta(2680.59, (float) $sim->sis_total_stockout_unit, 0.5);
        $this->assertEqualsWithDelta(1019.58, (float) $sim->sis_total_overstock_unit, 0.5);
        $this->assertFalse($sim->is_sistem_lebih_baik);
    }

    public function test_simulasi_menyimpan_24_baris_detail_dengan_periode_berurutan(): void
    {
        ['barangJadi' => $barangJadi] = $this->skenario();

        $sim = (new SimulationRunner())->jalankan(
            barang: $barangJadi,
            metodePembanding: 'naif_bulan_lalu',
            stokAwalSimulasi: 200,
            biayaSimpanPerUnit: 500,
            biayaStockoutPerUnit: 2000,
        );

        $this->assertSame(24, $sim->detail()->count());
        $this->assertSame(12, $sim->detailPerusahaan()->count());
        $this->assertSame(12, $sim->detailSistem()->count());

        $periodePerusahaan = $sim->detailPerusahaan()->pluck('periode')->all();
        $this->assertSame($periodePerusahaan, $sim->detailSistem()->pluck('periode')->all());
        $this->assertSame($sim->periode_awal, $periodePerusahaan[0]);
        $this->assertSame($sim->periode_akhir, $periodePerusahaan[11]);

        // Kedua skenario memakai permintaan_aktual & stok_awal_simulasi yang identik
        // (docs/01 §7.5: yang berbeda hanya cara menentukan rencana_produksi).
        $this->assertEqualsWithDelta(
            (float) $sim->detailPerusahaan()->first()->permintaan_aktual,
            (float) $sim->detailSistem()->first()->permintaan_aktual,
            1e-9
        );
        $this->assertEqualsWithDelta(200.0, (float) $sim->detailPerusahaan()->first()->stok_awal, 1e-9);
        $this->assertEqualsWithDelta(200.0, (float) $sim->detailSistem()->first()->stok_awal, 1e-9);
    }

    public function test_prediksi_permintaan_hanya_terisi_pada_skenario_sistem(): void
    {
        ['barangJadi' => $barangJadi] = $this->skenario();

        $sim = (new SimulationRunner())->jalankan(
            barang: $barangJadi,
            metodePembanding: 'naif_bulan_lalu',
            stokAwalSimulasi: 200,
            biayaSimpanPerUnit: 500,
            biayaStockoutPerUnit: 2000,
        );

        $this->assertNull($sim->detailPerusahaan()->first()->prediksi_permintaan);
        $this->assertNotNull($sim->detailSistem()->first()->prediksi_permintaan);
    }

    public function test_kesimpulan_konsisten_dengan_perbandingan_metrik(): void
    {
        ['barangJadi' => $barangJadi] = $this->skenario();

        $sim = (new SimulationRunner())->jalankan(
            barang: $barangJadi,
            metodePembanding: 'naif_bulan_lalu',
            stokAwalSimulasi: 200,
            biayaSimpanPerUnit: 500,
            biayaStockoutPerUnit: 2000,
        );

        $seharusnya = ((float) $sim->sis_total_overstock_unit <= (float) $sim->pb_total_overstock_unit)
            && ((float) $sim->sis_total_stockout_unit <= (float) $sim->pb_total_stockout_unit);

        $this->assertSame($seharusnya, $sim->is_sistem_lebih_baik);
        $this->assertNotEmpty($sim->kesimpulan);
    }

    public function test_menolak_data_kurang_dari_36_periode(): void
    {
        ['barangJadi' => $barangJadi] = $this->skenario();
        // Hapus deret supaya cuma tersisa 20 bulan.
        $barangJadi->dataTimeSeries()->orderBy('urutan_t')->skip(20)->take(100)->delete();

        $this->expectException(RuntimeException::class);
        (new SimulationRunner())->jalankan(
            barang: $barangJadi,
            metodePembanding: 'naif_bulan_lalu',
            stokAwalSimulasi: 200,
            biayaSimpanPerUnit: 500,
            biayaStockoutPerUnit: 2000,
        );
    }
}
