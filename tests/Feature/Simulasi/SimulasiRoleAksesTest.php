<?php

namespace Tests\Feature\Simulasi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji pembatasan akses per role untuk Simulasi & Pengujian, sesuai matriks
 * docs/01-alur-kerja-sistem.md §10: admin lihat saja, produksi & gudang
 * tidak boleh akses, pimpinan penuh.
 */
class SimulasiRoleAksesTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_aktif' => true]);
    }

    public function test_produksi_dan_gudang_ditolak_akses_simulasi(): void
    {
        foreach ([User::ROLE_PRODUKSI, User::ROLE_GUDANG] as $role) {
            $pengguna = $this->pengguna($role);

            $this->actingAs($pengguna)->get(route('simulasi.index'))->assertForbidden();
            $this->actingAs($pengguna)->get(route('simulasi.create'))->assertForbidden();
        }
    }

    public function test_admin_hanya_bisa_lihat_simulasi(): void
    {
        $admin = $this->pengguna(User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('simulasi.index'))->assertOk();
        $this->actingAs($admin)->get(route('simulasi.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('simulasi.store'), [])->assertForbidden();
    }

    public function test_pimpinan_penuh_akses_simulasi(): void
    {
        $pimpinan = $this->pengguna(User::ROLE_PIMPINAN);

        $this->actingAs($pimpinan)->get(route('simulasi.index'))->assertOk();
        $this->actingAs($pimpinan)->get(route('simulasi.create'))->assertOk();
    }
}
