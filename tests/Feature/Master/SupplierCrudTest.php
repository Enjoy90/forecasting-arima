<?php

namespace Tests\Feature\Master;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji alur CRUD master data supplier.
 */
class SupplierCrudTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_aktif' => true,
        ]);
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('master.supplier.index'))->assertRedirect(route('login'));
    }

    public function test_daftar_supplier_tampil_dan_dapat_dicari(): void
    {
        Supplier::factory()->create(['kode_supplier' => 'SUP-01', 'nama_supplier' => 'UD Baja Perkasa']);
        Supplier::factory()->create(['kode_supplier' => 'SUP-02', 'nama_supplier' => 'CV Kayu Jati Makmur']);

        $this->actingAs($this->pengguna())
            ->get(route('master.supplier.index'))
            ->assertOk()
            ->assertSee('UD Baja Perkasa')
            ->assertSee('CV Kayu Jati Makmur');

        $this->actingAs($this->pengguna())
            ->get(route('master.supplier.index', ['cari' => 'Kayu']))
            ->assertOk()
            ->assertSee('CV Kayu Jati Makmur')
            ->assertDontSee('UD Baja Perkasa');
    }

    public function test_daftar_dapat_disaring_menurut_status(): void
    {
        Supplier::factory()->create(['nama_supplier' => 'Supplier Masih Jalan']);
        Supplier::factory()->nonaktif()->create(['nama_supplier' => 'Supplier Sudah Berhenti']);

        $this->actingAs($this->pengguna())
            ->get(route('master.supplier.index', ['status' => 'aktif']))
            ->assertOk()
            ->assertSee('Supplier Masih Jalan')
            ->assertDontSee('Supplier Sudah Berhenti');

        $this->actingAs($this->pengguna())
            ->get(route('master.supplier.index', ['status' => 'nonaktif']))
            ->assertOk()
            ->assertSee('Supplier Sudah Berhenti')
            ->assertDontSee('Supplier Masih Jalan');
    }

    public function test_supplier_baru_tersimpan_dengan_kode_huruf_kapital(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.supplier.store'), [
                'kode_supplier' => 'sup-07',
                'nama_supplier' => 'UD Logam Jaya',
                'telepon' => '031-1234567',
                'email' => 'kontak@logamjaya.test',
                'alamat' => 'Jl. Percobaan No. 1',
                'lead_time_default' => 12,
                'is_aktif' => '1',
            ])
            ->assertRedirect(route('master.supplier.index'));

        $this->assertDatabaseHas('supplier', [
            'kode_supplier' => 'SUP-07',
            'nama_supplier' => 'UD Logam Jaya',
            'lead_time_default' => 12,
            'is_aktif' => true,
        ]);
    }

    public function test_checkbox_tidak_dicentang_tersimpan_sebagai_nonaktif(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.supplier.store'), [
                'kode_supplier' => 'SUP-08',
                'nama_supplier' => 'Supplier Nonaktif',
                'lead_time_default' => 7,
                'is_aktif' => '0',
            ])
            ->assertRedirect(route('master.supplier.index'));

        $this->assertDatabaseHas('supplier', [
            'kode_supplier' => 'SUP-08',
            'is_aktif' => false,
        ]);
    }

    public function test_kode_supplier_tidak_boleh_kembar(): void
    {
        Supplier::factory()->create(['kode_supplier' => 'SUP-01']);

        $this->actingAs($this->pengguna())
            ->post(route('master.supplier.store'), [
                'kode_supplier' => 'SUP-01',
                'nama_supplier' => 'Supplier Lain',
                'lead_time_default' => 7,
                'is_aktif' => '1',
            ])
            ->assertSessionHasErrors('kode_supplier');

        $this->assertDatabaseCount('supplier', 1);
    }

    public function test_lead_time_negatif_dan_email_salah_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.supplier.store'), [
                'kode_supplier' => 'SUP-09',
                'nama_supplier' => 'Supplier Uji',
                'email' => 'bukan-email',
                'lead_time_default' => -5,
                'is_aktif' => '1',
            ])
            ->assertSessionHasErrors(['email', 'lead_time_default']);

        $this->assertDatabaseCount('supplier', 0);
    }

    public function test_supplier_dapat_diubah_tanpa_terganjal_kodenya_sendiri(): void
    {
        $supplier = Supplier::factory()->create([
            'kode_supplier' => 'SUP-01',
            'nama_supplier' => 'UD Baja Perkasa',
            'lead_time_default' => 14,
        ]);

        $this->actingAs($this->pengguna())
            ->put(route('master.supplier.update', $supplier), [
                'kode_supplier' => 'SUP-01',
                'nama_supplier' => 'UD Baja Perkasa Sentosa',
                'telepon' => '031-9999999',
                'lead_time_default' => 10,
                'is_aktif' => '1',
            ])
            ->assertRedirect(route('master.supplier.index'));

        $this->assertDatabaseHas('supplier', [
            'id' => $supplier->id,
            'nama_supplier' => 'UD Baja Perkasa Sentosa',
            'lead_time_default' => 10,
        ]);
    }

    public function test_supplier_tanpa_relasi_dapat_dihapus(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->pengguna())
            ->delete(route('master.supplier.destroy', $supplier))
            ->assertRedirect(route('master.supplier.index'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('supplier', ['id' => $supplier->id]);
    }

    public function test_supplier_yang_masih_dipakai_barang_ditolak_saat_dihapus(): void
    {
        $supplier = Supplier::factory()->create();
        $kategori = Kategori::create(['kode_kategori' => 'KTG-01', 'nama_kategori' => 'Bahan Logam', 'keterangan' => null]);

        Barang::create([
            'kode_barang' => 'BRG-001',
            'nama_barang' => 'Plat Besi 3mm',
            'jenis_barang' => Barang::JENIS_BAHAN_BAKU,
            'kategori_id' => $kategori->id,
            'supplier_id' => $supplier->id,
            'satuan' => 'lembar',
            'harga_beli' => 150000,
            'stok_tersedia' => 0,
            'stok_minimum' => 0,
            'lead_time_hari' => 7,
        ]);

        $this->actingAs($this->pengguna())
            ->delete(route('master.supplier.destroy', $supplier))
            ->assertRedirect(route('master.supplier.index'))
            ->assertSessionHas('gagal');

        // Supplier tetap ada, dan kaitan pada barang tidak ikut dikosongkan.
        $this->assertDatabaseHas('supplier', ['id' => $supplier->id]);
        $this->assertDatabaseHas('barang', ['kode_barang' => 'BRG-001', 'supplier_id' => $supplier->id]);
    }
}
