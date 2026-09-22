<?php

namespace Tests\Feature\Peramalan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji pembatasan akses per role untuk Peramalan & Target Produksi, sesuai
 * matriks docs/01-alur-kerja-sistem.md §10: admin & produksi lihat saja,
 * gudang tidak boleh akses, pimpinan penuh (termasuk menyetujui target).
 * `peramalan.target.setujui` tidak diuji di sini (butuh fixture Peramalan +
 * TargetProduksi), tapi didaftarkan lewat middleware grup yang sama dengan
 * `peramalan.target.hitung` yang sudah diuji.
 */
class PeramalanRoleAksesTest extends TestCase
{
    use RefreshDatabase;

    private const HALAMAN_INDEX = [
        'peramalan.historis.index',
        'peramalan.forecasting.index',
        'peramalan.waktu-tunggu.index',
        'peramalan.target.index',
    ];

    private const AKSI_TULIS = [
        'peramalan.historis.agregasi',
        'peramalan.forecasting.proses',
        'peramalan.waktu-tunggu.hitung',
        'peramalan.target.hitung',
    ];

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_aktif' => true]);
    }

    public function test_gudang_ditolak_akses_peramalan(): void
    {
        $gudang = $this->pengguna(User::ROLE_GUDANG);

        foreach (self::HALAMAN_INDEX as $nama) {
            $this->actingAs($gudang)->get(route($nama))->assertForbidden();
        }

        foreach (self::AKSI_TULIS as $nama) {
            $this->actingAs($gudang)->post(route($nama), [])->assertForbidden();
        }
    }

    public function test_admin_dan_produksi_hanya_bisa_lihat_peramalan(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_PRODUKSI] as $role) {
            $pengguna = $this->pengguna($role);

            foreach (self::HALAMAN_INDEX as $nama) {
                $this->actingAs($pengguna)->get(route($nama))->assertOk();
            }

            foreach (self::AKSI_TULIS as $nama) {
                $this->actingAs($pengguna)->post(route($nama), [])->assertForbidden();
            }
        }
    }

    public function test_pimpinan_bisa_menjalankan_aksi_tulis_peramalan(): void
    {
        $pimpinan = $this->pengguna(User::ROLE_PIMPINAN);

        foreach (self::HALAMAN_INDEX as $nama) {
            $this->actingAs($pimpinan)->get(route($nama))->assertOk();
        }

        // Payload kosong akan gagal validasi (422), bukan 403 — cukup buktikan
        // middleware role tidak lagi menghalangi Pimpinan.
        foreach (self::AKSI_TULIS as $nama) {
            $this->actingAs($pimpinan)->post(route($nama), [])->assertStatus(302);
        }
    }
}
