<?php

namespace Tests\Feature\Persediaan;

use App\Models\Barang;
use App\Models\MutasiStok;
use App\Models\User;
use App\Services\Stok\StockMutator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji halaman Stok Saat Ini, Mutasi Stok, dan Stok Opname.
 */
class PersediaanTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_GUDANG,
            'is_aktif' => true,
        ]);
    }

    private function mutator(): StockMutator
    {
        return app(StockMutator::class);
    }

    // -----------------------------------------------------------------
    // Stok Saat Ini
    // -----------------------------------------------------------------

    public function test_tamu_ditolak_di_semua_halaman_persediaan(): void
    {
        $this->get(route('persediaan.stok'))->assertRedirect(route('login'));
        $this->get(route('persediaan.mutasi'))->assertRedirect(route('login'));
        $this->get(route('persediaan.opname.index'))->assertRedirect(route('login'));
    }

    public function test_halaman_stok_menampilkan_ringkasan_persediaan(): void
    {
        Barang::factory()->create(['stok_tersedia' => 100, 'stok_minimum' => 10, 'harga_beli' => 1000]);
        Barang::factory()->create(['stok_tersedia' => 5, 'stok_minimum' => 50, 'harga_beli' => 2000]);
        Barang::factory()->create(['stok_tersedia' => 0, 'stok_minimum' => 5, 'harga_beli' => 3000]);

        $this->actingAs($this->pengguna())
            ->get(route('persediaan.stok'))
            ->assertOk()
            ->assertViewHas('jumlahBarangAktif', 3)
            // dua barang menyentuh/di bawah minimum, salah satunya kosong
            ->assertViewHas('jumlahMenipis', 2)
            ->assertViewHas('jumlahKosong', 1)
            // 100*1000 + 5*2000 + 0 = 110.000
            ->assertViewHas('nilaiPersediaan', 110000.0);
    }

    public function test_halaman_stok_tidak_menampilkan_barang_nonaktif(): void
    {
        Barang::factory()->create(['nama_barang' => 'Barang Terpakai']);
        Barang::factory()->nonaktif()->create(['nama_barang' => 'Barang Pensiun']);

        $this->actingAs($this->pengguna())
            ->get(route('persediaan.stok'))
            ->assertOk()
            ->assertSee('Barang Terpakai')
            ->assertDontSee('Barang Pensiun');
    }

    public function test_halaman_stok_dapat_disaring_stok_menipis(): void
    {
        Barang::factory()->create(['nama_barang' => 'Aman Sentosa', 'stok_tersedia' => 500, 'stok_minimum' => 10]);
        Barang::factory()->create(['nama_barang' => 'Hampir Habis', 'stok_tersedia' => 2, 'stok_minimum' => 20]);

        $this->actingAs($this->pengguna())
            ->get(route('persediaan.stok', ['menipis' => 1]))
            ->assertOk()
            ->assertSee('Hampir Habis')
            ->assertDontSee('Aman Sentosa');
    }

    // -----------------------------------------------------------------
    // Mutasi Stok
    // -----------------------------------------------------------------

    public function test_halaman_mutasi_menampilkan_riwayat_dan_dapat_disaring(): void
    {
        $barang = Barang::factory()->create(['nama_barang' => 'Plat Besi Uji', 'stok_tersedia' => 0]);
        $lain = Barang::factory()->create(['nama_barang' => 'Kayu Gagang Uji', 'stok_tersedia' => 0]);

        $this->mutator()->catat($barang, MutasiStok::MASUK, 'pembelian', 100, keterangan: 'Penerimaan awal');
        $this->mutator()->catat($barang, MutasiStok::KELUAR, 'produksi', 30, keterangan: 'Dipakai produksi');
        $this->mutator()->catat($lain, MutasiStok::MASUK, 'pembelian', 50, keterangan: 'Kayu masuk');

        $this->actingAs($this->pengguna())
            ->get(route('persediaan.mutasi'))
            ->assertOk()
            ->assertSee('Penerimaan awal')
            ->assertSee('Dipakai produksi')
            ->assertSee('Kayu masuk');

        // Saring per barang
        $this->actingAs($this->pengguna())
            ->get(route('persediaan.mutasi', ['barang' => $barang->id]))
            ->assertOk()
            ->assertSee('Penerimaan awal')
            ->assertDontSee('Kayu masuk');

        // Saring per jenis
        $this->actingAs($this->pengguna())
            ->get(route('persediaan.mutasi', ['jenis' => MutasiStok::KELUAR]))
            ->assertOk()
            ->assertSee('Dipakai produksi')
            ->assertDontSee('Penerimaan awal');
    }

    public function test_halaman_mutasi_dapat_disaring_rentang_tanggal(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 0]);

        $this->mutator()->catat($barang, MutasiStok::MASUK, 'pembelian', 10, tanggal: '2026-01-15', keterangan: 'Mutasi Januari');
        $this->mutator()->catat($barang, MutasiStok::MASUK, 'pembelian', 10, tanggal: '2026-03-20', keterangan: 'Mutasi Maret');

        $this->actingAs($this->pengguna())
            ->get(route('persediaan.mutasi', ['dari' => '2026-03-01', 'sampai' => '2026-03-31']))
            ->assertOk()
            ->assertSee('Mutasi Maret')
            ->assertDontSee('Mutasi Januari');
    }

    // -----------------------------------------------------------------
    // Stok Opname
    // -----------------------------------------------------------------

    public function test_opname_mencatat_penyesuaian_dan_memperbarui_stok(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 120]);

        $this->actingAs($this->pengguna())
            ->post(route('persediaan.opname.store'), [
                'barang_id' => $barang->id,
                'stok_fisik' => 98,
                'tanggal' => '2026-09-01',
                'keterangan' => 'Ada yang rusak saat penyimpanan',
            ])
            ->assertRedirect(route('persediaan.opname.index'))
            ->assertSessionHas('sukses');

        $this->assertSame(98, $barang->fresh()->stok_tersedia);

        $this->assertDatabaseHas('mutasi_stok', [
            'barang_id' => $barang->id,
            'jenis_mutasi' => MutasiStok::PENYESUAIAN,
            'sumber' => 'opname',
            'jumlah' => -22,
            'stok_awal' => 120,
            'stok_akhir' => 98,
        ]);
    }

    public function test_opname_tanpa_selisih_tidak_mencatat_mutasi(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 75]);

        $this->actingAs($this->pengguna())
            ->post(route('persediaan.opname.store'), [
                'barang_id' => $barang->id,
                'stok_fisik' => 75,
                'tanggal' => now()->format('Y-m-d'),
            ])
            ->assertRedirect(route('persediaan.opname.index'))
            ->assertSessionHas('info');

        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_opname_menolak_tanggal_masa_depan_dan_stok_negatif(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 10]);

        $this->actingAs($this->pengguna())
            ->post(route('persediaan.opname.store'), [
                'barang_id' => $barang->id,
                'stok_fisik' => -5,
                'tanggal' => now()->addDays(3)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors(['stok_fisik', 'tanggal']);

        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_riwayat_opname_hanya_menampilkan_penyesuaian_dari_opname(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 0]);

        $this->mutator()->catat($barang, MutasiStok::MASUK, 'pembelian', 100, keterangan: 'Bukan opname');
        $this->mutator()->sesuaikanKe($barang, 90, keterangan: 'Hasil opname gudang');

        $this->actingAs($this->pengguna())
            ->get(route('persediaan.opname.index'))
            ->assertOk()
            ->assertSee('Hasil opname gudang')
            ->assertDontSee('Bukan opname');
    }

    public function test_halaman_opname_tidak_menyediakan_ubah_atau_hapus(): void
    {
        // Rute sengaja dibatasi index/create/store saja.
        $this->assertFalse(app('router')->has('persediaan.opname.edit'));
        $this->assertFalse(app('router')->has('persediaan.opname.update'));
        $this->assertFalse(app('router')->has('persediaan.opname.destroy'));
    }
}
