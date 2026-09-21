<?php

namespace Tests\Feature\Simulasi;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Models\Simulasi;
use App\Models\TahapanProduksi;
use App\Models\User;
use App\Services\Arima\TimeSeriesBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Uji halaman Simulasi & Pengujian (docs/01 §7): jalankan backtesting dan
 * tampilkan hasilnya.
 */
class SimulasiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int> */
    private array $dataBj01 = [1044, 1228, 1266, 1222, 1146, 1132, 1068, 987, 951, 906, 1008, 1053,
        1180, 1405, 1533, 1452, 1487, 1421, 1217, 1271, 1203, 1106, 1274, 1205,
        1475, 1709, 1684, 1668, 1808, 1604, 1475, 1392, 1416, 1468, 1447, 1485];

    private function pengguna(): User
    {
        return User::factory()->create(['role' => User::ROLE_PIMPINAN, 'is_aktif' => true]);
    }

    private function barangDenganDeret(): Barang
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

        return $barangJadi;
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('simulasi.index'))->assertRedirect(route('login'));
        $this->get(route('simulasi.create'))->assertRedirect(route('login'));

        $barang = $this->barangDenganDeret();
        $this->actingAs($this->pengguna())->post(route('simulasi.store'), [
            'barang_id' => $barang->id,
            'metode_pembanding' => 'naif_bulan_lalu',
            'stok_awal_simulasi' => 200,
            'biaya_simpan_per_unit' => 500,
            'biaya_stockout_per_unit' => 2000,
        ]);
        $simulasi = Simulasi::first();
        auth()->logout();

        $this->get(route('simulasi.cetak', $simulasi))->assertRedirect(route('login'));
    }

    public function test_halaman_index_dan_create_tampil(): void
    {
        $this->actingAs($this->pengguna())->get(route('simulasi.index'))->assertOk();
        $this->actingAs($this->pengguna())->get(route('simulasi.create'))->assertOk()->assertSee('Jalankan Simulasi Baru');
    }

    public function test_store_menjalankan_simulasi_dan_redirect_ke_show(): void
    {
        $barang = $this->barangDenganDeret();

        $response = $this->actingAs($this->pengguna())->post(route('simulasi.store'), [
            'barang_id' => $barang->id,
            'metode_pembanding' => 'naif_bulan_lalu',
            'stok_awal_simulasi' => 200,
            'biaya_simpan_per_unit' => 500,
            'biaya_stockout_per_unit' => 2000,
        ]);

        $simulasi = Simulasi::first();
        $response->assertRedirect(route('simulasi.show', $simulasi));
        $response->assertSessionHas('sukses');
        $this->assertSame(24, $simulasi->detail()->count());
    }

    public function test_store_menolak_data_kurang_dari_36_periode(): void
    {
        $barang = Barang::factory()->barangJadi()->create();

        $response = $this->actingAs($this->pengguna())->post(route('simulasi.store'), [
            'barang_id' => $barang->id,
            'metode_pembanding' => 'naif_bulan_lalu',
            'stok_awal_simulasi' => 200,
            'biaya_simpan_per_unit' => 500,
            'biaya_stockout_per_unit' => 2000,
        ]);

        $response->assertSessionHas('gagal');
        $this->assertDatabaseCount('simulasi', 0);
    }

    public function test_store_menolak_input_tidak_valid(): void
    {
        $response = $this->actingAs($this->pengguna())->post(route('simulasi.store'), []);

        $response->assertSessionHasErrors(['barang_id', 'metode_pembanding', 'stok_awal_simulasi', 'biaya_simpan_per_unit', 'biaya_stockout_per_unit']);
    }

    public function test_halaman_show_menampilkan_rincian_bulanan(): void
    {
        $barang = $this->barangDenganDeret();
        $this->actingAs($this->pengguna())->post(route('simulasi.store'), [
            'barang_id' => $barang->id,
            'metode_pembanding' => 'naif_bulan_lalu',
            'stok_awal_simulasi' => 200,
            'biaya_simpan_per_unit' => 500,
            'biaya_stockout_per_unit' => 2000,
        ]);
        $simulasi = Simulasi::first();

        $this->actingAs($this->pengguna())
            ->get(route('simulasi.show', $simulasi))
            ->assertOk()
            ->assertSee('Perusahaan (Kebijakan Lama)')
            ->assertSee('Sistem (Rekomendasi ARIMA)');
    }

    public function test_cetak_menghasilkan_pdf(): void
    {
        $barang = $this->barangDenganDeret();
        $this->actingAs($this->pengguna())->post(route('simulasi.store'), [
            'barang_id' => $barang->id,
            'metode_pembanding' => 'naif_bulan_lalu',
            'stok_awal_simulasi' => 200,
            'biaya_simpan_per_unit' => 500,
            'biaya_stockout_per_unit' => 2000,
        ]);
        $simulasi = Simulasi::first();

        // dompdf mengembalikan response biasa (bukan streamed), jadi isinya
        // dibaca lewat getContent(). Diperiksa lewat tanda tangan berkas
        // "%PDF" agar yakin yang terkirim benar-benar PDF, bukan halaman galat.
        $pdf = $this->actingAs($this->pengguna())->get(route('simulasi.cetak', $simulasi));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_halaman_perbandingan_menampilkan_kesimpulan(): void
    {
        $barang = $this->barangDenganDeret();
        $this->actingAs($this->pengguna())->post(route('simulasi.store'), [
            'barang_id' => $barang->id,
            'metode_pembanding' => 'naif_bulan_lalu',
            'stok_awal_simulasi' => 200,
            'biaya_simpan_per_unit' => 500,
            'biaya_stockout_per_unit' => 2000,
        ]);
        $simulasi = Simulasi::first();

        $this->actingAs($this->pengguna())
            ->get(route('simulasi.perbandingan', $simulasi))
            ->assertOk()
            ->assertSee($simulasi->kesimpulan)
            ->assertSee('Ringkasan Berdampingan');
    }
}
