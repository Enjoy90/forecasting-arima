<?php

namespace Tests\Feature\Peramalan;

use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Uji halaman Data Historis: agregasi penjualan menjadi deret Zt.
 */
class DataHistorisTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_PIMPINAN,
            'is_aktif' => true,
        ]);
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

    public function test_tamu_ditolak(): void
    {
        $this->get(route('peramalan.historis.index'))->assertRedirect(route('login'));
    }

    public function test_halaman_tampil_tanpa_barang_diramalkan(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('peramalan.historis.index'))
            ->assertOk()
            ->assertSee('Belum ada barang jadi');
    }

    public function test_halaman_menampilkan_deret_yang_sudah_ada(): void
    {
        $barang = Barang::factory()->barangJadi()->create();
        $this->jual($barang, '2026-01-05', 10);
        (new \App\Services\Arima\TimeSeriesBuilder())->bangun($barang);

        $this->actingAs($this->pengguna())
            ->get(route('peramalan.historis.index', ['barang' => $barang->id]))
            ->assertOk()
            ->assertSee($barang->nama_barang)
            ->assertSee('1 periode');
    }

    public function test_agregasi_membangun_deret_dan_redirect_dengan_pesan_sukses(): void
    {
        $barang = Barang::factory()->barangJadi()->create();
        $this->jual($barang, '2026-01-05', 10);
        $this->jual($barang, '2026-02-05', 20);

        $response = $this->actingAs($this->pengguna())
            ->post(route('peramalan.historis.agregasi'), ['barang_id' => $barang->id]);

        // Fragment #hasil-deret supaya browser langsung scroll ke hasil, bukan ke atas halaman.
        $response->assertRedirect(route('peramalan.historis.index', ['barang' => $barang->id]).'#hasil-deret');
        $response->assertSessionHas('sukses');

        $this->assertDatabaseCount('data_time_series', 2);
    }

    public function test_agregasi_menolak_barang_id_kosong(): void
    {
        $response = $this->actingAs($this->pengguna())
            ->post(route('peramalan.historis.agregasi'), []);

        $response->assertSessionHasErrors('barang_id');
    }

    public function test_agregasi_menolak_barang_id_asing(): void
    {
        $response = $this->actingAs($this->pengguna())
            ->post(route('peramalan.historis.agregasi'), ['barang_id' => 99999]);

        $response->assertSessionHasErrors('barang_id');
    }
}
