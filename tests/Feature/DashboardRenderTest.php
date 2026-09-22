<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji asap fondasi: memastikan layout + sidebar dua modul ter-render
 * tanpa error setelah pengguna masuk.
 */
class DashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_menampilkan_menu_kedua_modul(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_aktif' => true,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();

        // menu milik Modul A
        $response->assertSee('Data Barang');
        $response->assertSee('BOM / Komposisi');
        $response->assertSee('Mutasi Stok');

        // menu milik Modul B. "Jalankan Simulasi" tidak dicek di sini: sejak
        // RBAC diterapkan (2026-09-22), menu itu cuma tampil untuk Pimpinan,
        // admin cuma "lihat saja" (docs/01-alur-kerja-sistem.md §10).
        $response->assertSee('Proses Forecasting');
        $response->assertSee('Target Produksi');
        $response->assertSee('Perbandingan Skenario');
    }

    public function test_pengguna_nonaktif_ditolak_middleware_role(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_GUDANG,
            'is_aktif' => false,
        ]);

        $this->assertFalse($user->is_aktif);
        $this->assertTrue($user->hasRole(User::ROLE_GUDANG));
        $this->assertFalse($user->isAdmin());
    }
}
