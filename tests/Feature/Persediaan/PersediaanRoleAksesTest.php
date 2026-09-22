<?php

namespace Tests\Feature\Persediaan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji pembatasan akses per role untuk Persediaan & Mutasi Stok, sesuai
 * matriks docs/01-alur-kerja-sistem.md §10: admin & gudang penuh (termasuk
 * mencatat opname), produksi & pimpinan lihat saja.
 */
class PersediaanRoleAksesTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_aktif' => true]);
    }

    public function test_keempat_role_bisa_lihat_persediaan(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_GUDANG, User::ROLE_PRODUKSI, User::ROLE_PIMPINAN] as $role) {
            $pengguna = $this->pengguna($role);

            $this->actingAs($pengguna)->get(route('persediaan.stok'))->assertOk();
            $this->actingAs($pengguna)->get(route('persediaan.mutasi'))->assertOk();
            $this->actingAs($pengguna)->get(route('persediaan.opname.index'))->assertOk();
        }
    }

    public function test_hanya_admin_dan_gudang_bisa_mencatat_opname(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_GUDANG] as $role) {
            $this->actingAs($this->pengguna($role))
                ->get(route('persediaan.opname.create'))
                ->assertOk();
        }

        foreach ([User::ROLE_PRODUKSI, User::ROLE_PIMPINAN] as $role) {
            $this->actingAs($this->pengguna($role))
                ->get(route('persediaan.opname.create'))
                ->assertForbidden();
        }
    }
}
