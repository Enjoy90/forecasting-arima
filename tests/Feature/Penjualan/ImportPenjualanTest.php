<?php

namespace Tests\Feature\Penjualan;

use App\Models\Barang;
use App\Models\Pelanggan;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Uji import penjualan dari berkas Excel/CSV.
 *
 * Titik terpenting: import bersifat historis sehingga TIDAK mengubah stok,
 * dan satu baris yang salah membatalkan seluruh berkas.
 */
class ImportPenjualanTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_aktif' => true,
        ]);
    }

    private function berkas(string $isi, string $nama = 'penjualan.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'uji').'.csv';
        file_put_contents($path, $isi);

        return new UploadedFile($path, $nama, 'text/csv', null, true);
    }

    public function test_halaman_import_dapat_dibuka(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('penjualan.import.form'))
            ->assertOk()
            ->assertSee('Import ini tidak mengubah stok');
    }

    public function test_berkas_contoh_dapat_diunduh(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('penjualan.import.template'))
            ->assertOk()
            ->assertDownload('template-import-penjualan.xlsx');
    }

    public function test_import_membuat_faktur_tanpa_menyentuh_stok(): void
    {
        $barang = Barang::factory()->barangJadi()->create(['kode_barang' => 'BJ-01', 'stok_tersedia' => 100]);
        $pelanggan = Pelanggan::factory()->create(['nama_pelanggan' => 'UD Tani Makmur']);

        $isi = <<<'CSV'
        tanggal,no_faktur,nama_pelanggan,kode_barang,jumlah,harga_satuan
        2024-01-05,FJ-LAMA-001,UD Tani Makmur,BJ-01,25,95000
        2024-01-05,FJ-LAMA-001,UD Tani Makmur,BJ-01,10,95000
        2024-02-18,FJ-LAMA-002,UD Tani Makmur,BJ-01,40,95000
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('sukses')
            ->assertRedirect(route('penjualan.faktur.index', ['sumber' => 'import']));

        // Dua faktur: baris pertama & kedua digabung karena no_faktur sama.
        $this->assertDatabaseCount('penjualan', 2);
        $this->assertDatabaseCount('detail_penjualan', 3);

        $pertama = Penjualan::where('no_faktur', 'FJ-LAMA-001')->first();
        $this->assertSame('import', $pertama->sumber_data);
        $this->assertSame($pelanggan->id, $pertama->pelanggan_id);
        // (25 + 10) * 95.000 = 3.325.000
        $this->assertEquals(3325000, $pertama->total_harga);

        // Stok sama sekali tidak tersentuh.
        $this->assertSame(100, $barang->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_nama_pelanggan_asing_disimpan_sebagai_nama_bebas(): void
    {
        Barang::factory()->barangJadi()->create(['kode_barang' => 'BJ-01']);

        $isi = <<<'CSV'
        tanggal,no_faktur,nama_pelanggan,kode_barang,jumlah,harga_satuan
        2024-03-01,FJ-LAMA-003,Toko Belum Terdaftar,BJ-01,5,95000
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('sukses');

        $faktur = Penjualan::first();
        $this->assertNull($faktur->pelanggan_id);
        $this->assertSame('Toko Belum Terdaftar', $faktur->nama_pelanggan_manual);
    }

    public function test_kode_barang_asing_membatalkan_seluruh_berkas(): void
    {
        Barang::factory()->barangJadi()->create(['kode_barang' => 'BJ-01']);

        $isi = <<<'CSV'
        tanggal,no_faktur,nama_pelanggan,kode_barang,jumlah,harga_satuan
        2024-01-05,FJ-LAMA-001,Umum,BJ-01,25,95000
        2024-01-06,FJ-LAMA-002,Umum,BJ-99,10,95000
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('gagal');

        // Baris pertama sah, tetapi tetap tidak boleh tersimpan.
        $this->assertDatabaseCount('penjualan', 0);
    }

    public function test_nomor_faktur_yang_sudah_ada_dilewati_bukan_digandakan(): void
    {
        Barang::factory()->barangJadi()->create(['kode_barang' => 'BJ-01']);

        $isi = <<<'CSV'
        tanggal,no_faktur,nama_pelanggan,kode_barang,jumlah,harga_satuan
        2024-01-05,FJ-LAMA-001,Umum,BJ-01,25,95000
        CSV;

        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('penjualan.import.store'), ['berkas' => $this->berkas($isi)]);
        $this->assertDatabaseCount('penjualan', 1);

        // Import ulang berkas yang sama.
        $this->actingAs($pengguna)
            ->post(route('penjualan.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('sukses');

        $this->assertDatabaseCount('penjualan', 1);
        $this->assertDatabaseCount('detail_penjualan', 1);
    }

    public function test_kolom_yang_tidak_lengkap_ditolak(): void
    {
        Barang::factory()->barangJadi()->create(['kode_barang' => 'BJ-01']);

        $isi = <<<'CSV'
        tanggal,no_faktur,kode_barang
        2024-01-05,FJ-LAMA-001,BJ-01
        CSV;

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.import.store'), ['berkas' => $this->berkas($isi)])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('penjualan', 0);
    }

    public function test_berkas_bukan_excel_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('penjualan.import.store'), [
                'berkas' => UploadedFile::fake()->create('gambar.png', 10, 'image/png'),
            ])
            ->assertSessionHasErrors('berkas');
    }
}
