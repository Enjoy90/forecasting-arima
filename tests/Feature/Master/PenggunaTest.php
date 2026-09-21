<?php

namespace Tests\Feature\Master;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Uji manajemen pengguna.
 *
 * Penjagaan terpenting: admin tidak boleh mengunci dirinya sendiri di luar
 * sistem, dan harus selalu tersisa minimal satu admin aktif.
 */
class PenggunaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_aktif' => true]);
    }

    public function test_hanya_admin_yang_boleh_membuka_halaman_pengguna(): void
    {
        $this->get(route('master.pengguna.index'))->assertRedirect(route('login'));

        $gudang = User::factory()->create(['role' => User::ROLE_GUDANG, 'is_aktif' => true]);
        $this->actingAs($gudang)->get(route('master.pengguna.index'))->assertForbidden();

        $this->actingAs($this->admin())->get(route('master.pengguna.index'))->assertOk();
    }

    public function test_pengguna_baru_tersimpan_dan_langsung_dapat_masuk(): void
    {
        $this->actingAs($this->admin())
            ->post(route('master.pengguna.store'), [
                'name' => 'Staf Gudang Baru',
                'email' => 'GUDANG.BARU@pande.test',
                'password' => 'rahasia-panjang-123',
                'password_confirmation' => 'rahasia-panjang-123',
                'role' => User::ROLE_GUDANG,
                'is_aktif' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('master.pengguna.index'));

        // Email dinormalkan menjadi huruf kecil.
        $baru = User::where('email', 'gudang.baru@pande.test')->first();

        $this->assertNotNull($baru);
        $this->assertSame(User::ROLE_GUDANG, $baru->role);
        // Ditandai terverifikasi supaya tidak terhalang middleware 'verified'.
        $this->assertNotNull($baru->email_verified_at);
        $this->assertTrue(Hash::check('rahasia-panjang-123', $baru->password));
    }

    public function test_kata_sandi_kosong_saat_ubah_berarti_tidak_diganti(): void
    {
        $admin = $this->admin();
        $staf = User::factory()->create(['role' => User::ROLE_GUDANG, 'password' => Hash::make('sandi-lama-123')]);

        $this->actingAs($admin)
            ->put(route('master.pengguna.update', $staf), [
                'name' => 'Nama Diperbarui',
                'email' => $staf->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => User::ROLE_GUDANG,
                'is_aktif' => '1',
            ])
            ->assertSessionHasNoErrors();

        $staf->refresh();
        $this->assertSame('Nama Diperbarui', $staf->name);
        $this->assertTrue(Hash::check('sandi-lama-123', $staf->password));
    }

    public function test_admin_tidak_dapat_menonaktifkan_akunnya_sendiri(): void
    {
        $admin = $this->admin();
        User::factory()->create(['role' => User::ROLE_ADMIN, 'is_aktif' => true]);

        $this->actingAs($admin)
            ->put(route('master.pengguna.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => User::ROLE_ADMIN,
                'is_aktif' => '0',
            ])
            ->assertSessionHasErrors('is_aktif');

        $this->assertTrue($admin->fresh()->is_aktif);
    }

    public function test_admin_tidak_dapat_menurunkan_role_dirinya_sendiri(): void
    {
        $admin = $this->admin();
        User::factory()->create(['role' => User::ROLE_ADMIN, 'is_aktif' => true]);

        $this->actingAs($admin)
            ->put(route('master.pengguna.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => User::ROLE_GUDANG,
                'is_aktif' => '1',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_admin_terakhir_tidak_dapat_diturunkan(): void
    {
        $admin = $this->admin();
        $adminLain = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_aktif' => true]);

        // Turunkan admin lain: boleh, karena masih ada satu admin lagi.
        $this->actingAs($admin)
            ->put(route('master.pengguna.update', $adminLain), [
                'name' => $adminLain->name,
                'email' => $adminLain->email,
                'role' => User::ROLE_PIMPINAN,
                'is_aktif' => '1',
            ])
            ->assertSessionHasNoErrors();

        // Sekarang $admin satu-satunya; menurunkannya harus ditolak.
        $this->actingAs($admin)
            ->put(route('master.pengguna.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => User::ROLE_PIMPINAN,
                'is_aktif' => '1',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_admin_tidak_dapat_menghapus_akunnya_sendiri(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('master.pengguna.destroy', $admin))
            ->assertSessionHas('gagal');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_terakhir_tidak_dapat_dihapus(): void
    {
        $admin = $this->admin();
        $adminLain = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_aktif' => true]);

        // Hapus admin lain lewat akun $admin: boleh.
        $this->actingAs($admin)
            ->delete(route('master.pengguna.destroy', $adminLain))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('users', ['id' => $adminLain->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_pengguna_biasa_dapat_dihapus(): void
    {
        $admin = $this->admin();
        $staf = User::factory()->create(['role' => User::ROLE_PRODUKSI]);

        $this->actingAs($admin)
            ->delete(route('master.pengguna.destroy', $staf))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('users', ['id' => $staf->id]);
    }

    public function test_email_kembar_ditolak(): void
    {
        $admin = $this->admin();
        $staf = User::factory()->create(['email' => 'sudah@ada.test']);

        $this->actingAs($admin)
            ->post(route('master.pengguna.store'), [
                'name' => 'Duplikat',
                'email' => 'sudah@ada.test',
                'password' => 'rahasia-panjang-123',
                'password_confirmation' => 'rahasia-panjang-123',
                'role' => User::ROLE_GUDANG,
                'is_aktif' => '1',
            ])
            ->assertSessionHasErrors('email');
    }
}
