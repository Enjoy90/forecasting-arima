<?php

namespace Tests\Feature\Pembelian;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji pembatasan akses per role untuk Pembelian (order, riwayat, rekomendasi,
 * import), sesuai matriks docs/01-alur-kerja-sistem.md §10: admin & gudang
 * penuh, pimpinan lihat saja, produksi tidak boleh akses sama sekali.
 */
class PembelianRoleAksesTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_aktif' => true]);
    }

    public function test_produksi_ditolak_akses_pembelian(): void
    {
        $produksi = $this->pengguna(User::ROLE_PRODUKSI);

        $this->actingAs($produksi)->get(route('pembelian.order.index'))->assertForbidden();
        $this->actingAs($produksi)->get(route('pembelian.order.create'))->assertForbidden();
        $this->actingAs($produksi)->get(route('pembelian.riwayat'))->assertForbidden();
        $this->actingAs($produksi)->get(route('pembelian.rekomendasi.index'))->assertForbidden();
        $this->actingAs($produksi)->get(route('pembelian.import.form'))->assertForbidden();
    }

    public function test_pimpinan_hanya_bisa_lihat_pembelian(): void
    {
        $pimpinan = $this->pengguna(User::ROLE_PIMPINAN);

        $this->actingAs($pimpinan)->get(route('pembelian.order.index'))->assertOk();
        $this->actingAs($pimpinan)->get(route('pembelian.riwayat'))->assertOk();
        $this->actingAs($pimpinan)->get(route('pembelian.rekomendasi.index'))->assertOk();

        $this->actingAs($pimpinan)->get(route('pembelian.order.create'))->assertForbidden();
        $this->actingAs($pimpinan)->get(route('pembelian.import.form'))->assertForbidden();
    }

    public function test_admin_dan_gudang_penuh_akses_pembelian(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_GUDANG] as $role) {
            $pengguna = $this->pengguna($role);

            $this->actingAs($pengguna)->get(route('pembelian.order.index'))->assertOk();
            $this->actingAs($pengguna)->get(route('pembelian.order.create'))->assertOk();
            $this->actingAs($pengguna)->get(route('pembelian.riwayat'))->assertOk();
            $this->actingAs($pengguna)->get(route('pembelian.rekomendasi.index'))->assertOk();
            $this->actingAs($pengguna)->get(route('pembelian.import.form'))->assertOk();
        }
    }
}
