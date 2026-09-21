<?php

namespace Tests\Unit\Produksi;

use App\Exceptions\BahanTidakCukupException;
use App\Models\Barang;
use App\Models\Bom;
use App\Models\MutasiStok;
use App\Models\Produksi;
use App\Models\TahapanProduksi;
use App\Services\Produksi\ProduksiProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Uji eksekusi perintah produksi.
 *
 * Dua hal yang diuji paling teliti: penyalinan rencana bahan (termasuk persen
 * susut) dan pencatatan mutasi stok saat perintah diselesaikan.
 */
class ProduksiProcessorTest extends TestCase
{
    use RefreshDatabase;

    private ProduksiProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = app(ProduksiProcessor::class);
    }

    /**
     * Resep: 2 unit output butuh 10 bahan A (susut 0%) dan 4 bahan B (susut 25%).
     *
     * @return array{bom: Bom, output: Barang, bahanA: Barang, bahanB: Barang, tahapan: TahapanProduksi}
     */
    private function resep(int $stokA = 1000, int $stokB = 1000): array
    {
        $tahapan = TahapanProduksi::factory()->create(['urutan' => 1]);
        $output = Barang::factory()->setengahJadi()->create(['stok_tersedia' => 0]);
        $bahanA = Barang::factory()->create(['stok_tersedia' => $stokA, 'satuan' => 'Lembar']);
        $bahanB = Barang::factory()->create(['stok_tersedia' => $stokB, 'satuan' => 'Kg']);

        $bom = Bom::create([
            'kode_bom' => 'BOM-UJI',
            'nama_bom' => 'Resep Uji',
            'barang_id' => $output->id,
            'tahapan_id' => $tahapan->id,
            'jumlah_output' => 2,
            'is_aktif' => true,
        ]);

        $bom->detail()->create(['barang_id' => $bahanA->id, 'jumlah_kebutuhan' => 10, 'persen_susut' => 0, 'satuan' => 'Lembar']);
        $bom->detail()->create(['barang_id' => $bahanB->id, 'jumlah_kebutuhan' => 4, 'persen_susut' => 25, 'satuan' => 'Kg']);

        return compact('bom', 'output', 'bahanA', 'bahanB', 'tahapan');
    }

    private function perintah(array $r, float $target, string $status = Produksi::STATUS_DRAFT): Produksi
    {
        $produksi = Produksi::create([
            'no_produksi' => 'PRD-UJI-'.uniqid(),
            'tanggal_produksi' => '2026-09-10',
            'tahapan_id' => $r['tahapan']->id,
            'bom_id' => $r['bom']->id,
            'barang_output_id' => $r['output']->id,
            'jumlah_target' => $target,
            'status' => $status,
        ]);

        $this->processor->salinRencanaBahan($produksi, $r['bom'], $target);

        return $produksi;
    }

    public function test_rencana_bahan_disalin_dengan_memperhitungkan_susut(): void
    {
        $r = $this->resep();

        // Target 10 unit = 5x resep (jumlah_output 2).
        $produksi = $this->perintah($r, 10);
        $produksi->load('bahan');

        $this->assertCount(2, $produksi->bahan);

        // Bahan A: 10 x 5 x (1 + 0%)  = 50
        $a = $produksi->bahan->firstWhere('barang_id', $r['bahanA']->id);
        $this->assertEquals(50, (float) $a->jumlah_rencana);

        // Bahan B: 4 x 5 x (1 + 25%) = 25
        $b = $produksi->bahan->firstWhere('barang_id', $r['bahanB']->id);
        $this->assertEquals(25, (float) $b->jumlah_rencana);

        // jumlah_pakai diisi sama dengan rencana sebagai nilai awal.
        $this->assertEquals(50, (float) $a->jumlah_pakai);
        $this->assertSame('Kg', $b->satuan);
    }

    public function test_menyalin_ulang_mengganti_rencana_lama(): void
    {
        $r = $this->resep();
        $produksi = $this->perintah($r, 10);

        $this->processor->salinRencanaBahan($produksi, $r['bom'], 4);
        $produksi->load('bahan');

        // Tidak menumpuk: tetap 2 baris, dengan angka target baru.
        $this->assertCount(2, $produksi->bahan);
        $this->assertEquals(20, (float) $produksi->bahan->firstWhere('barang_id', $r['bahanA']->id)->jumlah_rencana);
    }

    public function test_menyelesaikan_perintah_mencatat_mutasi_bahan_dan_hasil(): void
    {
        $r = $this->resep(stokA: 1000, stokB: 1000);
        $produksi = $this->perintah($r, 10, Produksi::STATUS_PROSES);
        $produksi->update(['jumlah_hasil' => 9, 'jumlah_gagal' => 1]);

        $this->processor->selesaikan($produksi);

        $produksi->refresh();
        $this->assertSame(Produksi::STATUS_SELESAI, $produksi->status);

        // Bahan berkurang sesuai pemakaian.
        $this->assertSame(950, $r['bahanA']->fresh()->stok_tersedia);
        $this->assertSame(975, $r['bahanB']->fresh()->stok_tersedia);

        // Hanya yang jadi yang masuk gudang; produk gagal tidak menambah stok.
        $this->assertSame(9, $r['output']->fresh()->stok_tersedia);

        // 2 mutasi keluar + 1 mutasi masuk, semuanya menunjuk ke perintahnya.
        $this->assertDatabaseCount('mutasi_stok', 3);
        $this->assertDatabaseHas('mutasi_stok', [
            'barang_id' => $r['output']->id,
            'jenis_mutasi' => MutasiStok::MASUK,
            'sumber' => 'produksi',
            'referensi_tipe' => Produksi::class,
            'referensi_id' => $produksi->id,
            'jumlah' => 9,
        ]);
    }

    public function test_bahan_kurang_ditolak_dan_tidak_meninggalkan_jejak(): void
    {
        // Bahan A cukup (butuh 50), bahan B kurang (butuh 25, ada 10).
        $r = $this->resep(stokA: 1000, stokB: 10);
        $produksi = $this->perintah($r, 10, Produksi::STATUS_PROSES);
        $produksi->update(['jumlah_hasil' => 10]);

        try {
            $this->processor->selesaikan($produksi);
            $this->fail('Seharusnya melempar BahanTidakCukupException.');
        } catch (BahanTidakCukupException $e) {
            $this->assertCount(1, $e->kekurangan);
            $this->assertSame(15.0, $e->kekurangan[0]['kurang']);
        }

        // Tidak ada satu pun mutasi tercatat, termasuk untuk bahan yang cukup.
        $this->assertDatabaseCount('mutasi_stok', 0);
        $this->assertSame(1000, $r['bahanA']->fresh()->stok_tersedia);
        $this->assertSame(Produksi::STATUS_PROSES, $produksi->fresh()->status);
    }

    public function test_seluruh_kekurangan_dilaporkan_sekaligus(): void
    {
        $r = $this->resep(stokA: 5, stokB: 5);
        $produksi = $this->perintah($r, 10, Produksi::STATUS_PROSES);
        $produksi->update(['jumlah_hasil' => 10]);

        $kekurangan = $this->processor->kekuranganBahan($produksi);

        // Staf perlu melihat kedua bahan sekaligus, bukan satu per satu.
        $this->assertCount(2, $kekurangan);
    }

    public function test_perintah_draft_tidak_dapat_diselesaikan(): void
    {
        $r = $this->resep();
        $produksi = $this->perintah($r, 10);
        $produksi->update(['jumlah_hasil' => 10]);

        $this->expectException(RuntimeException::class);
        $this->processor->selesaikan($produksi);
    }

    public function test_perintah_tanpa_jumlah_hasil_tidak_dapat_diselesaikan(): void
    {
        $r = $this->resep();
        $produksi = $this->perintah($r, 10, Produksi::STATUS_PROSES);

        $this->expectExceptionMessageMatches('/Jumlah hasil belum diisi/');
        $this->processor->selesaikan($produksi);
    }

    public function test_bahan_dengan_pemakaian_nol_tidak_mencatat_mutasi(): void
    {
        $r = $this->resep();
        $produksi = $this->perintah($r, 10, Produksi::STATUS_PROSES);
        $produksi->update(['jumlah_hasil' => 10]);

        // Bahan B ternyata tidak terpakai sama sekali pada perintah ini.
        $produksi->bahan()->where('barang_id', $r['bahanB']->id)->update(['jumlah_pakai' => 0]);

        $this->processor->selesaikan($produksi);

        // 1 mutasi keluar (bahan A) + 1 mutasi masuk (hasil).
        $this->assertDatabaseCount('mutasi_stok', 2);
        $this->assertSame(1000, $r['bahanB']->fresh()->stok_tersedia);
    }
}
