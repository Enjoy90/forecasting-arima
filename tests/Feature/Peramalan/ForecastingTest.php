<?php

namespace Tests\Feature\Peramalan;

use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Peramalan;
use App\Models\Penjualan;
use App\Models\User;
use App\Services\Arima\TimeSeriesBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji halaman Proses Forecasting: menjalankan pipeline Box-Jenkins dan
 * menampilkan hasilnya.
 */
class ForecastingTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int> */
    private array $dataBj01 = [1044, 1228, 1266, 1222, 1146, 1132, 1068, 987, 951, 906, 1008, 1053,
        1180, 1405, 1533, 1452, 1487, 1421, 1217, 1271, 1203, 1106, 1274, 1205,
        1475, 1709, 1684, 1668, 1808, 1604, 1475, 1392, 1416, 1468, 1447, 1485];

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_PIMPINAN,
            'is_aktif' => true,
        ]);
    }

    private function barangDenganDeret(array $nilaiBulanan): Barang
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

        (new TimeSeriesBuilder())->bangun($barang);

        return $barang;
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('peramalan.forecasting.index'))->assertRedirect(route('login'));
    }

    public function test_halaman_index_tampil(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('peramalan.forecasting.index'))
            ->assertOk()
            ->assertSee('Jalankan Peramalan Baru');
    }

    public function test_proses_menjalankan_pipeline_dan_redirect_ke_halaman_hasil(): void
    {
        $barang = $this->barangDenganDeret($this->dataBj01);

        $response = $this->actingAs($this->pengguna())->post(route('peramalan.forecasting.proses'), [
            'barang_id' => $barang->id,
            'horizon' => 6,
        ]);

        $peramalan = Peramalan::first();
        $response->assertRedirect(route('peramalan.forecasting.show', $peramalan));
        $response->assertSessionHas('sukses');
        $this->assertSame(3, $peramalan->ordo_p);
        $this->assertSame(2, $peramalan->ordo_d);
    }

    public function test_proses_menolak_data_kurang_dari_24_periode(): void
    {
        $barang = $this->barangDenganDeret(array_slice($this->dataBj01, 0, 20));

        $response = $this->actingAs($this->pengguna())->post(route('peramalan.forecasting.proses'), [
            'barang_id' => $barang->id,
            'horizon' => 6,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('gagal');
        $this->assertDatabaseCount('peramalan', 0);
    }

    public function test_proses_menolak_horizon_di_luar_rentang(): void
    {
        $barang = $this->barangDenganDeret($this->dataBj01);

        $response = $this->actingAs($this->pengguna())->post(route('peramalan.forecasting.proses'), [
            'barang_id' => $barang->id,
            'horizon' => 13,
        ]);

        $response->assertSessionHasErrors('horizon');
    }

    public function test_halaman_show_menampilkan_seluruh_bukti_perhitungan(): void
    {
        $barang = $this->barangDenganDeret($this->dataBj01);
        $this->actingAs($this->pengguna())->post(route('peramalan.forecasting.proses'), [
            'barang_id' => $barang->id,
            'horizon' => 6,
        ]);

        $peramalan = Peramalan::first();

        $this->actingAs($this->pengguna())
            ->get(route('peramalan.forecasting.show', $peramalan))
            ->assertOk()
            ->assertSee($peramalan->kode_peramalan)
            ->assertSee('Uji Stasioneritas')
            ->assertSee('Kandidat Model')
            ->assertSee('Parameter Model Terpilih')
            ->assertSee('Aktual vs Prediksi');
    }
}
