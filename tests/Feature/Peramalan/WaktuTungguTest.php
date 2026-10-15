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
        $bahanBaku = Barang::factory()->create(['lead_time_hari' => 9, 'stok_tersedia' => 0]);

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
            'jumlah' => [$barang->id => 50],
            'awal_periode' => '2026-12-01',
            'periksa_stok' => 1,
        ]);

        $response->assertRedirect();
        $hasil = $response->getSession()->get('hasil');

        // waktu=1 (kapasitas besar, tidak nambah hari), stok bahan 0 dengan lead time 9 -> total=10.
        // Mulai produksi mundur 1 hari kerja dari Selasa 2026-12-01 = Senin 2026-11-30.
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
            'jumlah' => [$barang->id => 10],
            'awal_periode' => '2026-12-01',
            'periksa_stok' => 1,
        ]);

        $response->assertSessionHas('gagal');
    }

    public function test_hitung_menolak_bila_semua_jumlah_kosong(): void
    {
        $barang = $this->barangDenganBom();

        $response = $this->actingAs($this->pengguna())->post(route('peramalan.waktu-tunggu.hitung'), [
            'jumlah' => [$barang->id => null],
            'awal_periode' => '2026-12-01',
            'periksa_stok' => 1,
        ]);

        $response->assertSessionHas('gagal');
    }

    public function test_hitung_menolak_input_tidak_valid(): void
    {
        $response = $this->actingAs($this->pengguna())->post(route('peramalan.waktu-tunggu.hitung'), []);

        $response->assertSessionHasErrors(['jumlah', 'awal_periode', 'periksa_stok']);
    }

    public function test_jumlah_harus_bilangan_bulat_satu_sampai_seratus_ribu(): void
    {
        // [C4] angka raksasa dulu menghasilkan tanggal tahun -58129.
        $barang = $this->barangDenganBom();

        foreach (['1000000000', '1e400', '0.01', '0', '2.5'] as $nilai) {
            $this->actingAs($this->pengguna())
                ->post(route('peramalan.waktu-tunggu.hitung'), [
                    'jumlah' => [$barang->id => $nilai],
                    'awal_periode' => '2026-11-01',
                    'periksa_stok' => '1',
                ])
                ->assertSessionHasErrors('jumlah.'.$barang->id);
        }
    }

    public function test_rincian_jumlah_dibulatkan_ke_atas(): void
    {
        // [C7] resep 1 barang butuh 1,01 bahan; 24 barang -> 24,24 bahan tampil 25.
        $tahapan = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 1000]);
        $barangJadi = Barang::factory()->barangJadi()->create();
        $bahan = Barang::factory()->create(['lead_time_hari' => 3, 'stok_tersedia' => 0, 'satuan' => 'Pcs']);
        $bom = Bom::create([
            'kode_bom' => 'BOM-BULAT', 'nama_bom' => 'Resep', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $tahapan->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bom->detail()->create(['barang_id' => $bahan->id, 'jumlah_kebutuhan' => 1.01, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $this->actingAs($this->pengguna())
            ->followingRedirects()
            ->from(route('peramalan.waktu-tunggu.index'))
            ->post(route('peramalan.waktu-tunggu.hitung'), [
                'jumlah' => [$barangJadi->id => 24],
                'awal_periode' => '2026-11-01',
                'periksa_stok' => '1',
            ])
            ->assertOk()
            ->assertSee('25 Pcs')
            ->assertDontSee('24,24');
    }
}
