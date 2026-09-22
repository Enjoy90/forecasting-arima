<?php

namespace Tests\Feature\Produksi;

use App\Models\TahapanProduksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji pembatasan akses per role untuk BOM/Komposisi dan Perintah Produksi
 * (5 tahap), sesuai matriks docs/01-alur-kerja-sistem.md §10: BOM -> admin &
 * produksi penuh, pimpinan lihat saja, gudang tidak boleh akses. Perintah ->
 * admin & produksi penuh, gudang & pimpinan lihat saja.
 */
class ProduksiRoleAksesTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_aktif' => true]);
    }

    public function test_gudang_ditolak_akses_bom(): void
    {
        $gudang = $this->pengguna(User::ROLE_GUDANG);

        $this->actingAs($gudang)->get(route('produksi.bom.index'))->assertForbidden();
        $this->actingAs($gudang)->get(route('produksi.bom.create'))->assertForbidden();
    }

    public function test_pimpinan_hanya_bisa_lihat_bom(): void
    {
        $pimpinan = $this->pengguna(User::ROLE_PIMPINAN);

        $this->actingAs($pimpinan)->get(route('produksi.bom.index'))->assertOk();
        $this->actingAs($pimpinan)->get(route('produksi.bom.create'))->assertForbidden();
    }

    public function test_admin_dan_produksi_penuh_akses_bom(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_PRODUKSI] as $role) {
            $pengguna = $this->pengguna($role);

            $this->actingAs($pengguna)->get(route('produksi.bom.index'))->assertOk();
            $this->actingAs($pengguna)->get(route('produksi.bom.create'))->assertOk();
        }
    }

    public function test_keempat_role_bisa_lihat_perintah_produksi(): void
    {
        $tahapan = TahapanProduksi::factory()->create();

        foreach ([User::ROLE_ADMIN, User::ROLE_PRODUKSI, User::ROLE_GUDANG, User::ROLE_PIMPINAN] as $role) {
            $this->actingAs($this->pengguna($role))
                ->get(route('produksi.perintah.index', $tahapan->kode_tahapan))
                ->assertOk();
        }
    }

    public function test_hanya_admin_dan_produksi_bisa_membuat_perintah(): void
    {
        $tahapan = TahapanProduksi::factory()->create();

        foreach ([User::ROLE_ADMIN, User::ROLE_PRODUKSI] as $role) {
            $this->actingAs($this->pengguna($role))
                ->get(route('produksi.perintah.create', $tahapan->kode_tahapan))
                ->assertOk();
        }

        foreach ([User::ROLE_GUDANG, User::ROLE_PIMPINAN] as $role) {
            $this->actingAs($this->pengguna($role))
                ->get(route('produksi.perintah.create', $tahapan->kode_tahapan))
                ->assertForbidden();
        }
    }
}
