<?php

namespace Tests\Feature\Master;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji pembatasan akses per role untuk Master Data (kategori, barang, supplier,
 * pelanggan) dan Tahapan Produksi, sesuai matriks docs/01-alur-kerja-sistem.md §10.
 */
class MasterDataRoleAksesTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCE_MASTER_DATA = ['kategori', 'barang', 'supplier', 'pelanggan'];

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_aktif' => true]);
    }

    public function test_produksi_ditolak_akses_master_data(): void
    {
        $produksi = $this->pengguna(User::ROLE_PRODUKSI);

        foreach (self::RESOURCE_MASTER_DATA as $resource) {
            $this->actingAs($produksi)->get(route("master.{$resource}.index"))->assertForbidden();
            $this->actingAs($produksi)->get(route("master.{$resource}.create"))->assertForbidden();
        }
    }

    public function test_gudang_dan_pimpinan_hanya_bisa_lihat_master_data(): void
    {
        foreach ([User::ROLE_GUDANG, User::ROLE_PIMPINAN] as $role) {
            $pengguna = $this->pengguna($role);

            foreach (self::RESOURCE_MASTER_DATA as $resource) {
                $this->actingAs($pengguna)->get(route("master.{$resource}.index"))->assertOk();
                $this->actingAs($pengguna)->get(route("master.{$resource}.create"))->assertForbidden();
            }
        }
    }

    public function test_admin_penuh_akses_master_data(): void
    {
        $admin = $this->pengguna(User::ROLE_ADMIN);

        foreach (self::RESOURCE_MASTER_DATA as $resource) {
            $this->actingAs($admin)->get(route("master.{$resource}.index"))->assertOk();
            $this->actingAs($admin)->get(route("master.{$resource}.create"))->assertOk();
        }
    }

    public function test_gudang_ditolak_akses_tahapan_produksi(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_GUDANG))
            ->get(route('master.tahapan-produksi.index'))
            ->assertForbidden();
    }

    public function test_pimpinan_hanya_bisa_lihat_tahapan_produksi(): void
    {
        $pimpinan = $this->pengguna(User::ROLE_PIMPINAN);

        $this->actingAs($pimpinan)->get(route('master.tahapan-produksi.index'))->assertOk();
        $this->actingAs($pimpinan)->get(route('master.tahapan-produksi.create'))->assertForbidden();
    }

    public function test_produksi_dapat_menulis_tahapan_produksi(): void
    {
        $produksi = $this->pengguna(User::ROLE_PRODUKSI);

        $this->actingAs($produksi)->get(route('master.tahapan-produksi.index'))->assertOk();
        $this->actingAs($produksi)->get(route('master.tahapan-produksi.create'))->assertOk();
    }
}
