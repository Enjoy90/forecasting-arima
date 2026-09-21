<?php

namespace Tests\Feature\Master;

use App\Models\Pelanggan;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji alur CRUD master data pelanggan.
 */
class PelangganCrudTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_aktif' => true,
        ]);
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('master.pelanggan.index'))->assertRedirect(route('login'));
    }

    public function test_daftar_pelanggan_tampil_dan_dapat_dicari(): void
    {
        Pelanggan::factory()->create(['kode_pelanggan' => 'PLG-01', 'nama_pelanggan' => 'Toko Sumber Rejeki', 'kota' => 'Mojokerto']);
        Pelanggan::factory()->create(['kode_pelanggan' => 'PLG-02', 'nama_pelanggan' => 'UD Tani Makmur', 'kota' => 'Jombang']);

        $this->actingAs($this->pengguna())
            ->get(route('master.pelanggan.index'))
            ->assertOk()
            ->assertSee('Toko Sumber Rejeki')
            ->assertSee('UD Tani Makmur');

        $this->actingAs($this->pengguna())
            ->get(route('master.pelanggan.index', ['cari' => 'Tani']))
            ->assertOk()
            ->assertSee('UD Tani Makmur')
            ->assertDontSee('Toko Sumber Rejeki');
    }

    public function test_pencarian_juga_menjangkau_kota(): void
    {
        Pelanggan::factory()->create(['nama_pelanggan' => 'Pelanggan Kota Kediri', 'kota' => 'Kediri']);
        Pelanggan::factory()->create(['nama_pelanggan' => 'Pelanggan Kota Malang', 'kota' => 'Malang']);

        $this->actingAs($this->pengguna())
            ->get(route('master.pelanggan.index', ['cari' => 'Kediri']))
            ->assertOk()
            ->assertSee('Pelanggan Kota Kediri')
            ->assertDontSee('Pelanggan Kota Malang');
    }

    public function test_daftar_dapat_disaring_menurut_jenis_dan_status(): void
    {
        Pelanggan::factory()->create(['nama_pelanggan' => 'Distributor Besar', 'jenis' => 'distributor']);
        Pelanggan::factory()->create(['nama_pelanggan' => 'Toko Kecil', 'jenis' => 'toko']);
        Pelanggan::factory()->nonaktif()->create(['nama_pelanggan' => 'Pelanggan Berhenti', 'jenis' => 'toko']);

        $this->actingAs($this->pengguna())
            ->get(route('master.pelanggan.index', ['jenis' => 'distributor']))
            ->assertOk()
            ->assertSee('Distributor Besar')
            ->assertDontSee('Toko Kecil');

        $this->actingAs($this->pengguna())
            ->get(route('master.pelanggan.index', ['status' => 'nonaktif']))
            ->assertOk()
            ->assertSee('Pelanggan Berhenti')
            ->assertDontSee('Distributor Besar');
    }

    public function test_pelanggan_baru_tersimpan_dengan_kode_huruf_kapital(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.pelanggan.store'), [
                'kode_pelanggan' => 'plg-09',
                'nama_pelanggan' => 'Toko Tani Jaya',
                'jenis' => 'toko',
                'telepon' => '0321-111222',
                'email' => 'tani@jaya.test',
                'alamat' => 'Jl. Percobaan No. 2',
                'kota' => 'Mojokerto',
                'is_aktif' => '1',
            ])
            ->assertRedirect(route('master.pelanggan.index'));

        $this->assertDatabaseHas('pelanggan', [
            'kode_pelanggan' => 'PLG-09',
            'nama_pelanggan' => 'Toko Tani Jaya',
            'jenis' => 'toko',
            'kota' => 'Mojokerto',
            'is_aktif' => true,
        ]);
    }

    public function test_jenis_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.pelanggan.store'), [
                'kode_pelanggan' => 'PLG-10',
                'nama_pelanggan' => 'Pelanggan Salah Jenis',
                'jenis' => 'koperasi',
                'is_aktif' => '1',
            ])
            ->assertSessionHasErrors('jenis');

        $this->assertDatabaseCount('pelanggan', 0);
    }

    public function test_kode_pelanggan_tidak_boleh_kembar(): void
    {
        Pelanggan::factory()->create(['kode_pelanggan' => 'PLG-01']);

        $this->actingAs($this->pengguna())
            ->post(route('master.pelanggan.store'), [
                'kode_pelanggan' => 'PLG-01',
                'nama_pelanggan' => 'Pelanggan Lain',
                'jenis' => 'toko',
                'is_aktif' => '1',
            ])
            ->assertSessionHasErrors('kode_pelanggan');

        $this->assertDatabaseCount('pelanggan', 1);
    }

    public function test_checkbox_tidak_dicentang_tersimpan_sebagai_nonaktif(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.pelanggan.store'), [
                'kode_pelanggan' => 'PLG-11',
                'nama_pelanggan' => 'Pelanggan Nonaktif',
                'jenis' => 'perorangan',
                'is_aktif' => '0',
            ])
            ->assertRedirect(route('master.pelanggan.index'));

        $this->assertDatabaseHas('pelanggan', [
            'kode_pelanggan' => 'PLG-11',
            'is_aktif' => false,
        ]);
    }

    public function test_pelanggan_dapat_diubah_tanpa_terganjal_kodenya_sendiri(): void
    {
        $pelanggan = Pelanggan::factory()->create([
            'kode_pelanggan' => 'PLG-01',
            'nama_pelanggan' => 'Toko Sumber Rejeki',
            'jenis' => 'toko',
        ]);

        $this->actingAs($this->pengguna())
            ->put(route('master.pelanggan.update', $pelanggan), [
                'kode_pelanggan' => 'PLG-01',
                'nama_pelanggan' => 'Toko Sumber Rejeki Abadi',
                'jenis' => 'distributor',
                'kota' => 'Sidoarjo',
                'is_aktif' => '1',
            ])
            ->assertRedirect(route('master.pelanggan.index'));

        $this->assertDatabaseHas('pelanggan', [
            'id' => $pelanggan->id,
            'nama_pelanggan' => 'Toko Sumber Rejeki Abadi',
            'jenis' => 'distributor',
            'kota' => 'Sidoarjo',
        ]);
    }

    public function test_pelanggan_tanpa_transaksi_dapat_dihapus(): void
    {
        $pelanggan = Pelanggan::factory()->create();

        $this->actingAs($this->pengguna())
            ->delete(route('master.pelanggan.destroy', $pelanggan))
            ->assertRedirect(route('master.pelanggan.index'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('pelanggan', ['id' => $pelanggan->id]);
    }

    public function test_pelanggan_yang_punya_penjualan_ditolak_saat_dihapus(): void
    {
        $pelanggan = Pelanggan::factory()->create();

        Penjualan::create([
            'no_faktur' => 'FJ-2026-0001',
            'tanggal_penjualan' => '2026-01-15',
            'pelanggan_id' => $pelanggan->id,
            'total_harga' => 500000,
            'sumber_data' => 'manual',
        ]);

        $this->actingAs($this->pengguna())
            ->delete(route('master.pelanggan.destroy', $pelanggan))
            ->assertRedirect(route('master.pelanggan.index'))
            ->assertSessionHas('gagal');

        // Pelanggan tetap ada, dan faktur tidak kehilangan pemiliknya.
        $this->assertDatabaseHas('pelanggan', ['id' => $pelanggan->id]);
        $this->assertDatabaseHas('penjualan', ['no_faktur' => 'FJ-2026-0001', 'pelanggan_id' => $pelanggan->id]);
    }
}
