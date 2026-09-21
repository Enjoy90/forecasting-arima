<?php

namespace Tests\Feature\Master;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji alur CRUD master data kategori.
 */
class KategoriCrudTest extends TestCase
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
        $this->get(route('master.kategori.index'))->assertRedirect(route('login'));
    }

    public function test_daftar_kategori_tampil_dan_dapat_dicari(): void
    {
        Kategori::create(['kode_kategori' => 'KTG-01', 'nama_kategori' => 'Bahan Logam', 'keterangan' => null]);
        Kategori::create(['kode_kategori' => 'KTG-02', 'nama_kategori' => 'Bahan Kayu', 'keterangan' => null]);

        $this->actingAs($this->pengguna())
            ->get(route('master.kategori.index'))
            ->assertOk()
            ->assertSee('Bahan Logam')
            ->assertSee('Bahan Kayu');

        $this->actingAs($this->pengguna())
            ->get(route('master.kategori.index', ['cari' => 'Kayu']))
            ->assertOk()
            ->assertSee('Bahan Kayu')
            ->assertDontSee('Bahan Logam');
    }

    public function test_kategori_baru_tersimpan_dengan_kode_huruf_kapital(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.kategori.store'), [
                'kode_kategori' => 'ktg-07',
                'nama_kategori' => 'Bahan Pelumas',
                'keterangan' => 'Oli dan gemuk mesin',
            ])
            ->assertRedirect(route('master.kategori.index'));

        $this->assertDatabaseHas('kategori', [
            'kode_kategori' => 'KTG-07',
            'nama_kategori' => 'Bahan Pelumas',
        ]);
    }

    public function test_kode_kategori_tidak_boleh_kembar(): void
    {
        Kategori::create(['kode_kategori' => 'KTG-01', 'nama_kategori' => 'Bahan Logam', 'keterangan' => null]);

        $this->actingAs($this->pengguna())
            ->post(route('master.kategori.store'), [
                'kode_kategori' => 'KTG-01',
                'nama_kategori' => 'Kategori Lain',
            ])
            ->assertSessionHasErrors('kode_kategori');

        $this->assertDatabaseCount('kategori', 1);
    }

    public function test_kategori_dapat_diubah_tanpa_terganjal_kodenya_sendiri(): void
    {
        $kategori = Kategori::create(['kode_kategori' => 'KTG-01', 'nama_kategori' => 'Bahan Logam', 'keterangan' => null]);

        $this->actingAs($this->pengguna())
            ->put(route('master.kategori.update', $kategori), [
                'kode_kategori' => 'KTG-01',
                'nama_kategori' => 'Bahan Logam & Besi',
                'keterangan' => 'Plat besi dan pipa',
            ])
            ->assertRedirect(route('master.kategori.index'));

        $this->assertDatabaseHas('kategori', [
            'id' => $kategori->id,
            'nama_kategori' => 'Bahan Logam & Besi',
            'keterangan' => 'Plat besi dan pipa',
        ]);
    }

    public function test_kategori_tanpa_barang_dapat_dihapus(): void
    {
        $kategori = Kategori::create(['kode_kategori' => 'KTG-09', 'nama_kategori' => 'Kategori Kosong', 'keterangan' => null]);

        $this->actingAs($this->pengguna())
            ->delete(route('master.kategori.destroy', $kategori))
            ->assertRedirect(route('master.kategori.index'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('kategori', ['id' => $kategori->id]);
    }

    public function test_kategori_yang_masih_dipakai_barang_ditolak_saat_dihapus(): void
    {
        $kategori = Kategori::create(['kode_kategori' => 'KTG-01', 'nama_kategori' => 'Bahan Logam', 'keterangan' => null]);

        Barang::create([
            'kode_barang' => 'BRG-001',
            'nama_barang' => 'Plat Besi 3mm',
            'jenis_barang' => Barang::JENIS_BAHAN_BAKU,
            'kategori_id' => $kategori->id,
            'satuan' => 'lembar',
            'harga_beli' => 150000,
            'stok_tersedia' => 0,
            'stok_minimum' => 0,
            'lead_time_hari' => 7,
        ]);

        $this->actingAs($this->pengguna())
            ->delete(route('master.kategori.destroy', $kategori))
            ->assertRedirect(route('master.kategori.index'))
            ->assertSessionHas('gagal');

        $this->assertDatabaseHas('kategori', ['id' => $kategori->id]);
    }
}
