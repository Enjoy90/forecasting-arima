<?php

namespace Tests\Feature\Laporan;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\Pelanggan;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Produksi;
use App\Models\Supplier;
use App\Models\TahapanProduksi;
use App\Models\User;
use App\Services\Stok\StockMutator;
use App\Models\MutasiStok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji keempat laporan operasional beserta unduhan PDF & Excel.
 *
 * Yang diuji paling teliti: laporan hanya memuat transaksi yang benar-benar
 * terwujud (pembelian diterima, produksi selesai) dan hanya dalam rentang
 * tanggal yang diminta.
 */
class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create(['role' => User::ROLE_PIMPINAN, 'is_aktif' => true]);
    }

    /** @return list<string> */
    private function rute(): array
    {
        return ['laporan.pembelian', 'laporan.produksi', 'laporan.persediaan', 'laporan.penjualan'];
    }

    public function test_tamu_ditolak_di_seluruh_laporan(): void
    {
        foreach ($this->rute() as $rute) {
            $this->get(route($rute))->assertRedirect(route('login'));
        }
    }

    public function test_seluruh_laporan_terbuka_dan_dapat_diunduh(): void
    {
        $pengguna = $this->pengguna();

        foreach ($this->rute() as $rute) {
            $this->actingAs($pengguna)->get(route($rute))->assertOk();

            $this->actingAs($pengguna)
                ->get(route($rute, ['unduh' => 'excel']))
                ->assertOk()
                ->assertHeader('content-disposition');

            // dompdf mengembalikan response biasa (bukan streamed), jadi isinya
            // dibaca lewat getContent(). Diperiksa lewat tanda tangan berkas
            // "%PDF" agar yakin yang terkirim benar-benar PDF, bukan halaman galat.
            $pdf = $this->actingAs($pengguna)->get(route($rute, ['unduh' => 'pdf']));
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }
    }

    public function test_laporan_pembelian_hanya_memuat_order_yang_diterima(): void
    {
        $supplier = Supplier::factory()->create();
        $barang = Barang::factory()->create(['nama_barang' => 'Plat Uji Laporan']);

        $diterima = Pembelian::create([
            'no_pembelian' => 'PB-DITERIMA', 'tanggal_pembelian' => '2026-09-01',
            'tanggal_terima' => '2026-09-05', 'supplier_id' => $supplier->id,
            'status' => 'diterima', 'total_harga' => 100000,
        ]);
        $diterima->detail()->create(['barang_id' => $barang->id, 'jumlah' => 10, 'harga_satuan' => 10000, 'subtotal' => 100000]);

        $dipesan = Pembelian::create([
            'no_pembelian' => 'PB-DIPESAN', 'tanggal_pembelian' => '2026-09-02',
            'supplier_id' => $supplier->id, 'status' => 'dipesan', 'total_harga' => 50000,
        ]);
        $dipesan->detail()->create(['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 10000, 'subtotal' => 50000]);

        $this->actingAs($this->pengguna())
            ->get(route('laporan.pembelian', ['dari' => '2026-09-01', 'sampai' => '2026-09-30']))
            ->assertOk()
            ->assertSee('PB-DITERIMA')
            ->assertDontSee('PB-DIPESAN');
    }

    public function test_laporan_produksi_hanya_memuat_perintah_selesai(): void
    {
        $tahapan = TahapanProduksi::factory()->create(['urutan' => 1]);
        $output = Barang::factory()->setengahJadi()->create();

        Produksi::create([
            'no_produksi' => 'PRD-SELESAI', 'tanggal_produksi' => '2026-09-10',
            'tahapan_id' => $tahapan->id, 'barang_output_id' => $output->id,
            'jumlah_target' => 10, 'jumlah_hasil' => 9, 'jumlah_gagal' => 1,
            'status' => Produksi::STATUS_SELESAI,
        ]);

        Produksi::create([
            'no_produksi' => 'PRD-PROSES', 'tanggal_produksi' => '2026-09-11',
            'tahapan_id' => $tahapan->id, 'barang_output_id' => $output->id,
            'jumlah_target' => 10, 'status' => Produksi::STATUS_PROSES,
        ]);

        $this->actingAs($this->pengguna())
            ->get(route('laporan.produksi', ['dari' => '2026-09-01', 'sampai' => '2026-09-30']))
            ->assertOk()
            ->assertSee('PRD-SELESAI')
            ->assertDontSee('PRD-PROSES')
            // 1 gagal dari 10 dikerjakan = 10%
            ->assertSee('10,00%');
    }

    public function test_laporan_penjualan_menghormati_rentang_tanggal(): void
    {
        $barang = Barang::factory()->barangJadi()->create();
        $pelanggan = Pelanggan::factory()->create();

        foreach ([['FJ-DALAM', '2026-09-15'], ['FJ-LUAR', '2026-07-15']] as [$nomor, $tanggal]) {
            $faktur = Penjualan::create([
                'no_faktur' => $nomor, 'tanggal_penjualan' => $tanggal,
                'pelanggan_id' => $pelanggan->id, 'total_harga' => 95000,
            ]);
            $faktur->detail()->create(['barang_id' => $barang->id, 'jumlah' => 1, 'harga_satuan' => 95000, 'subtotal' => 95000]);
        }

        $this->actingAs($this->pengguna())
            ->get(route('laporan.penjualan', ['dari' => '2026-09-01', 'sampai' => '2026-09-30']))
            ->assertOk()
            ->assertSee('FJ-DALAM')
            ->assertDontSee('FJ-LUAR');
    }

    public function test_laporan_persediaan_menghitung_stok_awal_dari_mutasi(): void
    {
        $barang = Barang::factory()->create(['nama_barang' => 'Bahan Kartu Stok', 'stok_tersedia' => 0]);
        $mutator = app(StockMutator::class);

        // Stok awal periode: 100 (masuk sebelum rentang).
        $mutator->catat($barang, MutasiStok::MASUK, 'pembelian', 100, tanggal: '2026-08-20');
        // Dalam rentang: +50 masuk, -30 keluar  => stok akhir 120
        $mutator->catat($barang, MutasiStok::MASUK, 'pembelian', 50, tanggal: '2026-09-05');
        $mutator->catat($barang, MutasiStok::KELUAR, 'produksi', 30, tanggal: '2026-09-12');
        // Sesudah rentang: -20  (tidak boleh ikut mengubah stok akhir laporan)
        $mutator->catat($barang, MutasiStok::KELUAR, 'penjualan', 20, tanggal: '2026-10-02');

        $response = $this->actingAs($this->pengguna())
            ->get(route('laporan.persediaan', ['dari' => '2026-09-01', 'sampai' => '2026-09-30']))
            ->assertOk();

        $laporan = $response->viewData('laporan');
        $baris = collect($laporan['baris'])->firstWhere(1, 'Bahan Kartu Stok');

        $this->assertNotNull($baris);
        $this->assertSame('100', $baris[3]);        // stok awal
        $this->assertSame('50', $baris[4]);         // masuk
        $this->assertSame('30', $baris[5]);         // keluar
        $this->assertStringStartsWith('120', $baris[7]); // stok akhir periode
    }

    public function test_rentang_tanggal_terbalik_dibetulkan_sendiri(): void
    {
        $response = $this->actingAs($this->pengguna())
            ->get(route('laporan.penjualan', ['dari' => '2026-09-30', 'sampai' => '2026-09-01']))
            ->assertOk();

        $this->assertSame('2026-09-01', $response->viewData('dari')->format('Y-m-d'));
        $this->assertSame('2026-09-30', $response->viewData('sampai')->format('Y-m-d'));
    }

    public function test_laporan_kosong_tetap_dapat_dibuka_dan_diunduh(): void
    {
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)
            ->get(route('laporan.pembelian', ['dari' => '2020-01-01', 'sampai' => '2020-01-31']))
            ->assertOk()
            ->assertSee('Tidak ada data pada rentang tanggal ini');

        $this->actingAs($pengguna)
            ->get(route('laporan.pembelian', ['dari' => '2020-01-01', 'sampai' => '2020-01-31', 'unduh' => 'excel']))
            ->assertOk();
    }
}
