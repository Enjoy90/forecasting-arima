<?php

namespace Tests\Feature\Master;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\MutasiStok;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji alur CRUD master data barang — simpul pusat seluruh transaksi.
 */
class BarangCrudTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_aktif' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function isianSah(array $ganti = []): array
    {
        $kategori = Kategori::factory()->create();
        $supplier = Supplier::factory()->create();

        return array_merge([
            'kode_barang' => 'BB-50',
            'nama_barang' => 'Plat Besi Uji',
            'jenis_barang' => Barang::JENIS_BAHAN_BAKU,
            'kategori_id' => $kategori->id,
            'supplier_id' => $supplier->id,
            'satuan' => 'Lembar',
            'harga_beli' => 185000,
            'harga_jual' => 0,
            'stok_minimum' => 40,
            'lead_time_hari' => 14,
            'service_level' => 95,
            'is_diramalkan' => '0',
            'is_aktif' => '1',
        ], $ganti);
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('master.barang.index'))->assertRedirect(route('login'));
    }

    public function test_daftar_barang_tampil_dan_dapat_dicari(): void
    {
        Barang::factory()->create(['kode_barang' => 'BB-01', 'nama_barang' => 'Plat Besi']);
        Barang::factory()->create(['kode_barang' => 'BB-02', 'nama_barang' => 'Kawat Las']);

        $this->actingAs($this->pengguna())
            ->get(route('master.barang.index'))
            ->assertOk()
            ->assertSee('Plat Besi')
            ->assertSee('Kawat Las');

        $this->actingAs($this->pengguna())
            ->get(route('master.barang.index', ['cari' => 'Kawat']))
            ->assertOk()
            ->assertSee('Kawat Las')
            ->assertDontSee('Plat Besi');
    }

    public function test_daftar_dapat_disaring_menurut_jenis(): void
    {
        Barang::factory()->create(['nama_barang' => 'Bahan Baku Satu']);
        Barang::factory()->barangJadi()->create(['nama_barang' => 'Sekop Siap Jual']);

        $this->actingAs($this->pengguna())
            ->get(route('master.barang.index', ['jenis' => Barang::JENIS_BARANG_JADI]))
            ->assertOk()
            ->assertSee('Sekop Siap Jual')
            ->assertDontSee('Bahan Baku Satu');
    }

    public function test_daftar_dapat_disaring_menurut_stok_menipis(): void
    {
        Barang::factory()->create(['nama_barang' => 'Stok Aman', 'stok_tersedia' => 500, 'stok_minimum' => 10]);
        Barang::factory()->create(['nama_barang' => 'Stok Kritis', 'stok_tersedia' => 5, 'stok_minimum' => 50]);

        $this->actingAs($this->pengguna())
            ->get(route('master.barang.index', ['menipis' => 1]))
            ->assertOk()
            ->assertSee('Stok Kritis')
            ->assertDontSee('Stok Aman');
    }

    public function test_barang_baru_selalu_dimulai_dengan_stok_nol(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.barang.store'), $this->isianSah([
                // Sengaja mengirim stok_tersedia; harus diabaikan.
                'stok_tersedia' => 9999,
            ]))
            ->assertRedirect(route('master.barang.index'));

        $this->assertDatabaseHas('barang', [
            'kode_barang' => 'BB-50',
            'stok_tersedia' => 0,
        ]);
    }

    public function test_stok_tersedia_tidak_dapat_diubah_lewat_form_ubah(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 120]);

        $this->actingAs($this->pengguna())
            ->put(route('master.barang.update', $barang), $this->isianSah([
                'kode_barang' => $barang->kode_barang,
                'nama_barang' => 'Nama Diubah',
                'kategori_id' => $barang->kategori_id,
                'supplier_id' => $barang->supplier_id,
                'stok_tersedia' => 7777,
            ]))
            ->assertRedirect(route('master.barang.index'));

        $this->assertDatabaseHas('barang', [
            'id' => $barang->id,
            'nama_barang' => 'Nama Diubah',
            'stok_tersedia' => 120,
        ]);
    }

    public function test_kode_barang_tersimpan_huruf_kapital_dan_tidak_boleh_kembar(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.barang.store'), $this->isianSah(['kode_barang' => 'bb-51']))
            ->assertRedirect(route('master.barang.index'));

        $this->assertDatabaseHas('barang', ['kode_barang' => 'BB-51']);

        $this->actingAs($this->pengguna())
            ->post(route('master.barang.store'), $this->isianSah(['kode_barang' => 'BB-51']))
            ->assertSessionHasErrors('kode_barang');

        $this->assertDatabaseCount('barang', 1);
    }

    public function test_bahan_baku_wajib_punya_supplier(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.barang.store'), $this->isianSah([
                'jenis_barang' => Barang::JENIS_BAHAN_BAKU,
                'supplier_id' => '',
            ]))
            ->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseCount('barang', 0);
    }

    public function test_barang_jadi_boleh_tanpa_supplier(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.barang.store'), $this->isianSah([
                'kode_barang' => 'BJ-50',
                'nama_barang' => 'Sekop Tanah',
                'jenis_barang' => Barang::JENIS_BARANG_JADI,
                'supplier_id' => '',
                'harga_jual' => 95000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('master.barang.index'));

        $this->assertDatabaseHas('barang', [
            'kode_barang' => 'BJ-50',
            'supplier_id' => null,
        ]);
    }

    public function test_hanya_barang_jadi_yang_boleh_ditandai_diramalkan(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.barang.store'), $this->isianSah([
                'jenis_barang' => Barang::JENIS_BAHAN_BAKU,
                'is_diramalkan' => '1',
            ]))
            ->assertSessionHasErrors('is_diramalkan');

        $this->assertDatabaseCount('barang', 0);
    }

    public function test_barang_jadi_dapat_ditandai_diramalkan(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.barang.store'), $this->isianSah([
                'kode_barang' => 'BJ-51',
                'jenis_barang' => Barang::JENIS_BARANG_JADI,
                'supplier_id' => '',
                'is_diramalkan' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('barang', [
            'kode_barang' => 'BJ-51',
            'is_diramalkan' => true,
        ]);
    }

    public function test_angka_perencanaan_stok_divalidasi(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.barang.store'), $this->isianSah([
                'stok_minimum' => -5,
                'lead_time_hari' => -1,
                'service_level' => 20,
            ]))
            ->assertSessionHasErrors(['stok_minimum', 'lead_time_hari', 'service_level']);

        $this->assertDatabaseCount('barang', 0);
    }

    public function test_barang_tanpa_riwayat_dapat_dihapus(): void
    {
        $barang = Barang::factory()->create();

        $this->actingAs($this->pengguna())
            ->delete(route('master.barang.destroy', $barang))
            ->assertRedirect(route('master.barang.index'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('barang', ['id' => $barang->id]);
    }

    public function test_barang_yang_punya_mutasi_stok_ditolak_saat_dihapus(): void
    {
        $barang = Barang::factory()->create();

        MutasiStok::create([
            'barang_id' => $barang->id,
            'tanggal' => '2026-01-10',
            'jenis_mutasi' => 'masuk',
            'sumber' => 'pembelian',
            'jumlah' => 100,
            'stok_awal' => 0,
            'stok_akhir' => 100,
        ]);

        $this->actingAs($this->pengguna())
            ->delete(route('master.barang.destroy', $barang))
            ->assertRedirect(route('master.barang.index'))
            ->assertSessionHas('gagal');

        // Barang tetap ada, dan mutasi stoknya tidak ikut terhapus oleh cascade.
        $this->assertDatabaseHas('barang', ['id' => $barang->id]);
        $this->assertDatabaseCount('mutasi_stok', 1);
    }
}
