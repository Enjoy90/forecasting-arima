<?php

namespace Tests\Feature\Peramalan;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\HasilPeramalan;
use App\Models\Peramalan;
use App\Models\TahapanProduksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji halaman Target Produksi (docs/01 §6): hitung rencana stok dari hasil
 * peramalan dan menyetujuinya.
 */
class TargetProduksiTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create(['role' => User::ROLE_PIMPINAN, 'is_aktif' => true]);
    }

    /** @return array{peramalan: Peramalan, barangJadi: Barang} */
    private function skenario(): array
    {
        $tahapan = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 1000]);
        $barangJadi = Barang::factory()->barangJadi()->create(['stok_tersedia' => 10, 'stok_minimum' => 5]);
        $bahanBaku = Barang::factory()->create(['lead_time_hari' => 5, 'stok_tersedia' => 1000]);

        $bom = Bom::create([
            'kode_bom' => 'BOM-UJI', 'nama_bom' => 'Resep', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $tahapan->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bom->detail()->create(['barang_id' => $bahanBaku->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $peramalan = Peramalan::create([
            'kode_peramalan' => 'PRM-UJI-'.uniqid(),
            'barang_id' => $barangJadi->id,
            'periode_awal' => '2024-01', 'periode_akhir' => '2026-05', 'jumlah_data' => 29,
            'ordo_p' => 1, 'ordo_d' => 0, 'ordo_q' => 0,
            'sigma_kuadrat' => 100, 'horizon' => 3, 'status' => 'draft',
        ]);
        HasilPeramalan::create([
            'peramalan_id' => $peramalan->id, 'periode' => '2026-06', 'urutan_t' => 30,
            'tipe' => 'forecast', 'nilai_prediksi' => 50,
        ]);

        return compact('peramalan', 'barangJadi');
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('peramalan.target.index'))->assertRedirect(route('login'));
    }

    public function test_halaman_tampil(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('peramalan.target.index'))
            ->assertOk()
            ->assertSee('Riwayat Target Produksi');
    }

    public function test_halaman_menampilkan_periode_forecast_saat_barang_dipilih(): void
    {
        ['barangJadi' => $barangJadi] = $this->skenario();

        $this->actingAs($this->pengguna())
            ->get(route('peramalan.target.index', ['barang' => $barangJadi->id]))
            ->assertOk()
            ->assertSee('2026-06');
    }

    public function test_hitung_membuat_target_produksi_dan_redirect_ke_detail(): void
    {
        ['barangJadi' => $barangJadi] = $this->skenario();

        $response = $this->actingAs($this->pengguna())->post(route('peramalan.target.hitung'), [
            'barang_id' => $barangJadi->id,
            'periode' => '2026-06',
        ]);

        $target = \App\Models\TargetProduksi::first();
        $response->assertRedirect(route('peramalan.target.index', ['barang' => $barangJadi->id, 'target' => $target->id]));
        $response->assertSessionHas('sukses');
        $this->assertSame('menunggu', $target->status_approval);
    }

    public function test_hitung_menolak_barang_tanpa_peramalan(): void
    {
        $barang = Barang::factory()->barangJadi()->create();

        $response = $this->actingAs($this->pengguna())->post(route('peramalan.target.hitung'), [
            'barang_id' => $barang->id,
            'periode' => '2026-06',
        ]);

        $response->assertSessionHas('gagal');
        $this->assertDatabaseCount('target_produksi', 0);
    }

    public function test_setujui_mengubah_status_approval(): void
    {
        ['peramalan' => $peramalan] = $this->skenario();
        $target = (new \App\Services\Stok\TargetProduksiPlanner())->rencanakan($peramalan, '2026-06');

        $response = $this->actingAs($this->pengguna())->patch(route('peramalan.target.setujui', $target));

        $response->assertRedirect();
        $response->assertSessionHas('sukses');
        $this->assertSame('disetujui', $target->fresh()->status_approval);
        $this->assertNotNull($target->fresh()->disetujui_pada);
    }

    public function test_detail_menampilkan_kebutuhan_bahan(): void
    {
        ['peramalan' => $peramalan, 'barangJadi' => $barangJadi] = $this->skenario();
        $target = (new \App\Services\Stok\TargetProduksiPlanner())->rencanakan($peramalan, '2026-06');

        $this->actingAs($this->pengguna())
            ->get(route('peramalan.target.index', ['barang' => $barangJadi->id, 'target' => $target->id]))
            ->assertOk()
            ->assertSee('Kebutuhan Bahan Baku');
    }
}
