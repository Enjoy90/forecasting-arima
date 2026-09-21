<?php

namespace Tests\Feature\Pembelian;

use App\Models\Barang;
use App\Models\Pembelian;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Uji import pembelian dari berkas Excel/CSV.
 *
 * Titik terpenting: berbeda dari import penjualan, hasil import ini adalah
 * order BERSTATUS DIPESAN yang sama sekali TIDAK menyentuh stok — persis
 * seperti order manual. Stok baru bergerak lewat proses penerimaan terpisah.
 */
class ImportPembelianTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_aktif' => true,
        ]);
    }

    private function berkas(string $isi, string $nama = 'pembelian.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'uji').'.csv';
        file_put_contents($path, $isi);

        return new UploadedFile($path, $nama, 'text/csv', null, true);
    }

    public function test_halaman_import_dapat_dibuka(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('pembelian.import.form'))
            ->assertOk()
            ->assertSee('stok tidak langsung bertambah');
    }

    public function test_berkas_contoh_dapat_diunduh(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('pembelian.import.template'))
            ->assertOk()
            ->assertDownload('template-import-pembelian.xlsx');
    }

    public function test_order_dengan_barang_berbeda_tersimpan_lengkap(): void
    {
        $supplier = Supplier::factory()->create(['kode_supplier' => 'SUP-01']);
        $barangA = Barang::factory()->create(['kode_barang' => 'BB-01', 'stok_tersedia' => 50, 'supplier_id' => $supplier->id]);
        $barangB = Barang::factory()->create(['kode_barang' => 'BB-02', 'stok_tersedia' => 20, 'supplier_id' => $supplier->id]);

        $isi = <<<'CSV'
        tanggal,no_pembelian,kode_supplier,kode_barang,jumlah,harga_satuan
        2024-01-05,PB-LAMA-001,SUP-01,BB-01,100,15000
        2024-01-05,PB-LAMA-001,SUP-01,BB-02,50,25000
        2024-02-18,PB-LAMA-002,SUP-01,BB-01,200,15000
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('sukses')
            ->assertRedirect(route('pembelian.order.index', ['status' => 'dipesan']));

        $this->assertDatabaseCount('pembelian', 2);
        $this->assertDatabaseCount('detail_pembelian', 3);

        $pertama = Pembelian::where('no_pembelian', 'PB-LAMA-001')->first();
        $this->assertSame('dipesan', $pertama->status);
        $this->assertSame('import', $pertama->sumber_data);
        $this->assertSame($supplier->id, $pertama->supplier_id);
        // (100 * 15.000) + (50 * 25.000) = 2.750.000
        $this->assertEquals(2750000, $pertama->total_harga);

        // Stok sama sekali tidak tersentuh karena order masih berstatus dipesan.
        $this->assertSame(50, $barangA->fresh()->stok_tersedia);
        $this->assertSame(20, $barangB->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_kode_supplier_asing_membatalkan_seluruh_berkas(): void
    {
        Supplier::factory()->create(['kode_supplier' => 'SUP-01']);
        Barang::factory()->create(['kode_barang' => 'BB-01']);

        $isi = <<<'CSV'
        tanggal,no_pembelian,kode_supplier,kode_barang,jumlah,harga_satuan
        2024-01-05,PB-LAMA-001,SUP-01,BB-01,100,15000
        2024-01-06,PB-LAMA-002,SUP-99,BB-01,50,15000
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_kode_barang_asing_membatalkan_seluruh_berkas(): void
    {
        Supplier::factory()->create(['kode_supplier' => 'SUP-01']);
        Barang::factory()->create(['kode_barang' => 'BB-01']);

        $isi = <<<'CSV'
        tanggal,no_pembelian,kode_supplier,kode_barang,jumlah,harga_satuan
        2024-01-05,PB-LAMA-001,SUP-01,BB-01,100,15000
        2024-01-06,PB-LAMA-002,SUP-01,BB-99,50,15000
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_satu_order_dua_supplier_ditolak(): void
    {
        Supplier::factory()->create(['kode_supplier' => 'SUP-01']);
        Supplier::factory()->create(['kode_supplier' => 'SUP-02']);
        Barang::factory()->create(['kode_barang' => 'BB-01']);

        $isi = <<<'CSV'
        tanggal,no_pembelian,kode_supplier,kode_barang,jumlah,harga_satuan
        2024-01-05,PB-LAMA-001,SUP-01,BB-01,100,15000
        2024-01-05,PB-LAMA-001,SUP-02,BB-01,50,15000
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_barang_dobel_dalam_satu_order_ditolak(): void
    {
        Supplier::factory()->create(['kode_supplier' => 'SUP-01']);
        Barang::factory()->create(['kode_barang' => 'BB-01']);

        $isi = <<<'CSV'
        tanggal,no_pembelian,kode_supplier,kode_barang,jumlah,harga_satuan
        2024-01-05,PB-LAMA-001,SUP-01,BB-01,100,15000
        2024-01-05,PB-LAMA-001,SUP-01,BB-01,50,15000
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_nomor_pembelian_yang_sudah_ada_dilewati_bukan_digandakan(): void
    {
        Supplier::factory()->create(['kode_supplier' => 'SUP-01']);
        Barang::factory()->create(['kode_barang' => 'BB-01']);

        $isi = <<<'CSV'
        tanggal,no_pembelian,kode_supplier,kode_barang,jumlah,harga_satuan
        2024-01-05,PB-LAMA-001,SUP-01,BB-01,100,15000
        CSV;

        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('pembelian.import.store'), ['berkas' => $this->berkas($isi)]);
        $this->assertDatabaseCount('pembelian', 1);

        // Import ulang berkas yang sama.
        $this->actingAs($pengguna)
            ->post(route('pembelian.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('sukses');

        $this->assertDatabaseCount('pembelian', 1);
        $this->assertDatabaseCount('detail_pembelian', 1);
    }

    public function test_tanggal_masa_depan_ditolak(): void
    {
        Supplier::factory()->create(['kode_supplier' => 'SUP-01']);
        Barang::factory()->create(['kode_barang' => 'BB-01']);

        $isi = "tanggal,no_pembelian,kode_supplier,kode_barang,jumlah,harga_satuan\n".
            now()->addDay()->format('Y-m-d').",PB-LAMA-001,SUP-01,BB-01,100,15000";

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_kolom_yang_tidak_lengkap_ditolak(): void
    {
        Barang::factory()->create(['kode_barang' => 'BB-01']);

        $isi = <<<'CSV'
        tanggal,no_pembelian,kode_barang
        2024-01-05,PB-LAMA-001,BB-01
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_berkas_bukan_excel_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('pembelian.import.store'), [
                'berkas' => UploadedFile::fake()->create('gambar.png', 10, 'image/png'),
            ])
            ->assertSessionHasErrors('berkas');
    }
}
