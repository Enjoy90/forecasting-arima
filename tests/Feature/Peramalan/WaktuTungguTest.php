<?php

namespace Tests\Feature\Peramalan;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\TahapanProduksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji halaman kalkulator Waktu Tunggu (docs/01 §4).
 */
class WaktuTungguTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create(['role' => User::ROLE_PIMPINAN, 'is_aktif' => true]);
    }

    private function barangDenganBom(): Barang
    {
        $tahapan = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 1000]);
        $barangJadi = Barang::factory()->barangJadi()->create();
        $bahanBaku = Barang::factory()->create(['lead_time_hari' => 9]);

        $bom = Bom::create([
            'kode_bom' => 'BOM-UJI', 'nama_bom' => 'Resep', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $tahapan->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bom->detail()->create(['barang_id' => $bahanBaku->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        return $barangJadi;
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('peramalan.waktu-tunggu.index'))->assertRedirect(route('login'));
    }

    public function test_halaman_tampil(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('peramalan.waktu-tunggu.index'))
            ->assertOk()
            ->assertSee('Waktu Tunggu Operasional');
    }

    public function test_hitung_menampilkan_hasil_lead_time(): void
    {
        $barang = $this->barangDenganBom();

        $response = $this->actingAs($this->pengguna())->post(route('peramalan.waktu-tunggu.hitung'), [
            'barang_id' => $barang->id,
            'jumlah_target' => 50,
            'awal_periode' => '2026-12-01',
        ]);

        $response->assertRedirect();
        $hasil = $response->getSession()->get('hasil');

        // waktu=1 (kapasitas besar, tidak nambah hari), lead_time bahan=9 -> total=10
        $this->assertEqualsWithDelta(9.0, $hasil['lead_time_pembelian_hari'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $hasil['lead_time_produksi_hari'], 1e-9);
        $this->assertEqualsWithDelta(10.0, $hasil['lead_time_total_hari'], 1e-9);
        $this->assertSame('2026-11-30', $hasil['tanggal_mulai_produksi']);
        $this->assertSame('2026-11-21', $hasil['tanggal_pesan_bahan']);
    }

    public function test_hitung_menolak_barang_tanpa_bom(): void
    {
        $barang = Barang::factory()->barangJadi()->create();

        $response = $this->actingAs($this->pengguna())->post(route('peramalan.waktu-tunggu.hitung'), [
            'barang_id' => $barang->id,
            'jumlah_target' => 10,
            'awal_periode' => '2026-12-01',
        ]);

        $response->assertSessionHas('gagal');
    }

    public function test_hitung_menolak_input_tidak_valid(): void
    {
        $response = $this->actingAs($this->pengguna())->post(route('peramalan.waktu-tunggu.hitung'), []);

        $response->assertSessionHasErrors(['barang_id', 'jumlah_target', 'awal_periode']);
    }
}
