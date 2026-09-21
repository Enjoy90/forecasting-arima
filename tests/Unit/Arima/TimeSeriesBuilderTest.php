<?php

namespace Tests\Unit\Arima;

use App\Models\Barang;
use App\Models\DataTimeSeries;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Services\Arima\TimeSeriesBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji agregasi penjualan bulanan menjadi deret Zt (docs/01 §5): sumber data
 * awal seluruh pipeline Box-Jenkins, jadi bulan kosong tidak boleh membuat
 * urutan_t bolong.
 */
class TimeSeriesBuilderTest extends TestCase
{
    use RefreshDatabase;

    private TimeSeriesBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new TimeSeriesBuilder();
    }

    private function jual(Barang $barang, string $tanggal, int $jumlah): void
    {
        $penjualan = Penjualan::create([
            'no_faktur' => 'FK-'.uniqid(),
            'tanggal_penjualan' => $tanggal,
            'total_harga' => $jumlah * 10000,
        ]);

        DetailPenjualan::create([
            'penjualan_id' => $penjualan->id,
            'barang_id' => $barang->id,
            'jumlah' => $jumlah,
            'harga_satuan' => 10000,
            'subtotal' => $jumlah * 10000,
        ]);
    }

    public function test_bangun_mengagregasi_jumlah_per_bulan(): void
    {
        $barang = Barang::factory()->barangJadi()->create();

        $this->jual($barang, '2026-01-05', 10);
        $this->jual($barang, '2026-01-20', 15);
        $this->jual($barang, '2026-02-10', 8);

        $deret = $this->builder->bangun($barang);

        $this->assertCount(2, $deret);
        $this->assertDatabaseHas('data_time_series', [
            'barang_id' => $barang->id,
            'periode' => '2026-01',
            'urutan_t' => 1,
            'nilai_zt' => 25,
        ]);
        $this->assertDatabaseHas('data_time_series', [
            'barang_id' => $barang->id,
            'periode' => '2026-02',
            'urutan_t' => 2,
            'nilai_zt' => 8,
        ]);
    }

    public function test_bulan_tanpa_penjualan_tetap_tercatat_bernilai_nol(): void
    {
        $barang = Barang::factory()->barangJadi()->create();

        $this->jual($barang, '2026-01-15', 12);
        // Februari sengaja dilewati (tidak ada transaksi).
        $this->jual($barang, '2026-03-10', 20);

        $deret = $this->builder->bangun($barang);

        $this->assertCount(3, $deret);
        $this->assertDatabaseHas('data_time_series', [
            'barang_id' => $barang->id,
            'periode' => '2026-02',
            'urutan_t' => 2,
            'nilai_zt' => 0,
        ]);
    }

    public function test_bangun_ulang_memperbarui_baris_yang_sama_bukan_menggandakan(): void
    {
        $barang = Barang::factory()->barangJadi()->create();

        $this->jual($barang, '2026-01-05', 10);
        $this->builder->bangun($barang);

        $this->jual($barang, '2026-01-20', 5);
        $this->builder->bangun($barang);

        $this->assertDatabaseCount('data_time_series', 1);
        $this->assertDatabaseHas('data_time_series', [
            'barang_id' => $barang->id,
            'periode' => '2026-01',
            'nilai_zt' => 15,
        ]);
    }

    public function test_ambil_deret_mengembalikan_array_angka_terurut(): void
    {
        $barang = Barang::factory()->barangJadi()->create();

        $this->jual($barang, '2026-01-05', 10);
        $this->jual($barang, '2026-02-05', 20);
        $this->jual($barang, '2026-03-05', 30);
        $this->builder->bangun($barang);

        $deret = $this->builder->ambilDeret($barang);

        $this->assertSame([10.0, 20.0, 30.0], $deret);
    }

    public function test_barang_tanpa_transaksi_menghasilkan_deret_kosong(): void
    {
        $barang = Barang::factory()->barangJadi()->create();

        $deret = $this->builder->bangun($barang);

        $this->assertCount(0, $deret);
        $this->assertSame([], $this->builder->ambilDeret($barang));
    }
}
