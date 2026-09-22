<?php

namespace Tests\Feature\Penjualan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji pembatasan akses per role untuk Penjualan, sesuai matriks
 * docs/01-alur-kerja-sistem.md §10: admin penuh, pimpinan lihat saja,
 * produksi & gudang tidak boleh akses sama sekali.
 */
class PenjualanRoleAksesTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_aktif' => true]);
    }

    public function test_produksi_dan_gudang_ditolak_akses_penjualan(): void
    {
        foreach ([User::ROLE_PRODUKSI, User::ROLE_GUDANG] as $role) {
            $this->actingAs($this->pengguna($role))
                ->get(route('penjualan.faktur.index'))
                ->assertForbidden();
        }
    }

    public function test_pimpinan_hanya_bisa_lihat_penjualan(): void
    {
        $pimpinan = $this->pengguna(User::ROLE_PIMPINAN);

        $this->actingAs($pimpinan)->get(route('penjualan.faktur.index'))->assertOk();
        $this->actingAs($pimpinan)->get(route('penjualan.faktur.create'))->assertForbidden();
        $this->actingAs($pimpinan)->get(route('penjualan.import.form'))->assertForbidden();
    }

    public function test_admin_penuh_akses_penjualan(): void
    {
        $admin = $this->pengguna(User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('penjualan.faktur.index'))->assertOk();
        $this->actingAs($admin)->get(route('penjualan.faktur.create'))->assertOk();
        $this->actingAs($admin)->get(route('penjualan.import.form'))->assertOk();
    }
}
