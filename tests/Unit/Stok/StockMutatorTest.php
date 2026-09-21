<?php

namespace Tests\Unit\Stok;

use App\Exceptions\StokTidakCukupException;
use App\Models\Barang;
use App\Models\MutasiStok;
use App\Models\Pembelian;
use App\Models\Supplier;
use App\Services\Stok\StockMutator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Uji pencatatan mutasi stok terpusat.
 *
 * Inilah pintu masuk tunggal perubahan stok, jadi perilakunya diuji terpisah
 * dari halaman web: seluruh modul transaksi nantinya bergantung padanya.
 */
class StockMutatorTest extends TestCase
{
    use RefreshDatabase;

    private StockMutator $mutator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mutator = new StockMutator();
    }

    public function test_mutasi_masuk_menambah_stok_barang(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 100]);

        $mutasi = $this->mutator->catat(
            barang: $barang,
            jenis: MutasiStok::MASUK,
            sumber: 'pembelian',
            jumlah: 50,
        );

        $this->assertEquals(100, $mutasi->stok_awal);
        $this->assertEquals(150, $mutasi->stok_akhir);
        $this->assertSame(150, $barang->fresh()->stok_tersedia);
    }

    public function test_mutasi_keluar_mengurangi_stok_barang(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 100]);

        $mutasi = $this->mutator->catat(
            barang: $barang,
            jenis: MutasiStok::KELUAR,
            sumber: 'penjualan',
            jumlah: 30,
        );

        $this->assertEquals(70, $mutasi->stok_akhir);
        // Jumlah selalu dicatat positif; arahnya ditentukan jenis_mutasi.
        $this->assertEquals(30, $mutasi->jumlah);
        $this->assertSame(70, $barang->fresh()->stok_tersedia);
    }

    public function test_mutasi_keluar_melebihi_stok_ditolak(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 10]);

        try {
            $this->mutator->catat(
                barang: $barang,
                jenis: MutasiStok::KELUAR,
                sumber: 'produksi',
                jumlah: 25,
            );
            $this->fail('Seharusnya melempar StokTidakCukupException.');
        } catch (StokTidakCukupException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->getMessage());
        }

        // Tidak boleh ada jejak apa pun yang tertinggal.
        $this->assertDatabaseCount('mutasi_stok', 0);
        $this->assertSame(10, $barang->fresh()->stok_tersedia);
    }

    public function test_rantai_mutasi_berurutan_menyambung(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 0]);

        $this->mutator->catat($barang, MutasiStok::MASUK, 'pembelian', 100);
        $this->mutator->catat($barang, MutasiStok::KELUAR, 'produksi', 40);
        $ketiga = $this->mutator->catat($barang, MutasiStok::MASUK, 'produksi', 25);

        $this->assertEquals(60, $ketiga->stok_awal);
        $this->assertEquals(85, $ketiga->stok_akhir);
        $this->assertSame(85, $barang->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 3);
    }

    public function test_penyesuaian_menerima_selisih_bertanda(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 100]);

        $kurang = $this->mutator->catat($barang, MutasiStok::PENYESUAIAN, 'opname', -15);
        $this->assertEquals(85, $kurang->stok_akhir);

        $lebih = $this->mutator->catat($barang, MutasiStok::PENYESUAIAN, 'opname', 5);
        $this->assertEquals(90, $lebih->stok_akhir);
        $this->assertSame(90, $barang->fresh()->stok_tersedia);
    }

    public function test_sesuaikan_ke_menghitung_selisih_sendiri(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 120]);

        $mutasi = $this->mutator->sesuaikanKe($barang, 98, keterangan: 'Hasil hitung fisik gudang');

        $this->assertNotNull($mutasi);
        $this->assertSame(MutasiStok::PENYESUAIAN, $mutasi->jenis_mutasi);
        $this->assertSame('opname', $mutasi->sumber);
        $this->assertEquals(-22, $mutasi->jumlah);
        $this->assertEquals(98, $mutasi->stok_akhir);
        $this->assertSame(98, $barang->fresh()->stok_tersedia);
    }

    public function test_sesuaikan_ke_tidak_mencatat_apa_pun_bila_stok_sudah_cocok(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 75]);

        $this->assertNull($this->mutator->sesuaikanKe($barang, 75));
        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_referensi_sumber_tersimpan(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 0]);
        $supplier = Supplier::factory()->create();

        $pembelian = Pembelian::create([
            'no_pembelian' => 'PB-2026-0001',
            'tanggal_pembelian' => '2026-02-01',
            'supplier_id' => $supplier->id,
            'total_harga' => 1000000,
            'status' => 'diterima',
        ]);

        $mutasi = $this->mutator->catat(
            barang: $barang,
            jenis: MutasiStok::MASUK,
            sumber: 'pembelian',
            jumlah: 10,
            referensi: $pembelian,
            keterangan: 'Penerimaan dari '.$supplier->nama_supplier,
        );

        $this->assertSame(Pembelian::class, $mutasi->referensi_tipe);
        $this->assertSame($pembelian->id, $mutasi->referensi_id);
    }

    public function test_jenis_dan_sumber_asing_ditolak(): void
    {
        $barang = Barang::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->mutator->catat($barang, 'hilang', 'pembelian', 5);
    }

    public function test_jumlah_nol_atau_negatif_pada_mutasi_masuk_ditolak(): void
    {
        $barang = Barang::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->mutator->catat($barang, MutasiStok::MASUK, 'pembelian', 0);
    }

    public function test_stok_barang_tanpa_mutasi_diambil_dari_kolom_barang(): void
    {
        $barang = Barang::factory()->create(['stok_tersedia' => 42]);

        $this->assertSame(42.0, $this->mutator->stokAwal($barang));
    }
}
