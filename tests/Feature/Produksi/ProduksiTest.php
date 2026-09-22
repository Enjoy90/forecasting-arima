<?php

namespace Tests\Feature\Produksi;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\MutasiStok;
use App\Models\Produksi;
use App\Models\TahapanProduksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji halaman BOM dan perintah produksi lima tahapan.
 */
class ProduksiTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_PRODUKSI,
            'is_aktif' => true,
        ]);
    }

    /**
     * @return array{tahapan: TahapanProduksi, bom: Bom, output: Barang, bahan: Barang}
     */
    private function resep(int $stokBahan = 1000): array
    {
        $tahapan = TahapanProduksi::factory()->create(['kode_tahapan' => 'TP-01', 'urutan' => 1]);
        $output = Barang::factory()->setengahJadi()->create(['stok_tersedia' => 0]);
        $bahan = Barang::factory()->create(['stok_tersedia' => $stokBahan]);

        $bom = Bom::create([
            'kode_bom' => 'BOM-01',
            'nama_bom' => 'Resep Kepala',
            'barang_id' => $output->id,
            'tahapan_id' => $tahapan->id,
            'jumlah_output' => 1,
            'is_aktif' => true,
        ]);
        $bom->detail()->create(['barang_id' => $bahan->id, 'jumlah_kebutuhan' => 2, 'persen_susut' => 0]);

        return compact('tahapan', 'bom', 'output', 'bahan');
    }

    // -----------------------------------------------------------------
    // BOM
    // -----------------------------------------------------------------

    public function test_tamu_ditolak(): void
    {
        $this->get(route('produksi.bom.index'))->assertRedirect(route('login'));
    }

    public function test_bom_baru_tersimpan_beserta_komponennya(): void
    {
        $tahapan = TahapanProduksi::factory()->create(['urutan' => 1]);
        $output = Barang::factory()->setengahJadi()->create();
        $bahan = Barang::factory()->create();

        $this->actingAs($this->pengguna())
            ->post(route('produksi.bom.store'), [
                'kode_bom' => 'bom-09',
                'nama_bom' => 'Resep Uji',
                'barang_id' => $output->id,
                'tahapan_id' => $tahapan->id,
                'jumlah_output' => 2,
                'is_aktif' => '1',
                'detail' => [
                    ['barang_id' => $bahan->id, 'jumlah_kebutuhan' => 5, 'persen_susut' => 3],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('bom', ['kode_bom' => 'BOM-09', 'jumlah_output' => 2]);
        $this->assertDatabaseHas('detail_bom', [
            'barang_id' => $bahan->id,
            'jumlah_kebutuhan' => 5,
            'persen_susut' => 3,
        ]);
    }

    public function test_komponen_tidak_boleh_sama_dengan_barang_hasil(): void
    {
        $tahapan = TahapanProduksi::factory()->create(['urutan' => 1]);
        $output = Barang::factory()->setengahJadi()->create();

        $this->actingAs($this->pengguna())
            ->post(route('produksi.bom.store'), [
                'kode_bom' => 'BOM-10',
                'nama_bom' => 'Resep Memutar',
                'barang_id' => $output->id,
                'tahapan_id' => $tahapan->id,
                'jumlah_output' => 1,
                'is_aktif' => '1',
                'detail' => [['barang_id' => $output->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0]],
            ])
            ->assertSessionHasErrors('detail.0.barang_id');

        $this->assertDatabaseCount('bom', 0);
    }

    public function test_bom_yang_dipakai_perintah_tidak_dapat_dihapus(): void
    {
        $r = $this->resep();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('produksi.perintah.store', 'TP-01'), [
            'tanggal_produksi' => '2026-09-10',
            'bom_id' => $r['bom']->id,
            'jumlah_target' => 5,
        ]);

        $this->actingAs($pengguna)
            ->delete(route('produksi.bom.destroy', $r['bom']))
            ->assertSessionHas('gagal');

        $this->assertDatabaseHas('bom', ['id' => $r['bom']->id]);
    }

    // -----------------------------------------------------------------
    // Perintah produksi
    // -----------------------------------------------------------------

    public function test_perintah_baru_menyalin_rencana_bahan_tanpa_menyentuh_stok(): void
    {
        $r = $this->resep();

        $this->actingAs($this->pengguna())
            ->post(route('produksi.perintah.store', 'TP-01'), [
                'tanggal_produksi' => '2026-09-10',
                'bom_id' => $r['bom']->id,
                'jumlah_target' => 20,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $produksi = Produksi::first();

        $this->assertSame(Produksi::STATUS_DRAFT, $produksi->status);
        $this->assertSame($r['output']->id, $produksi->barang_output_id);
        $this->assertStringStartsWith('PRD-TP01-202609-', $produksi->no_produksi);

        // 2 bahan per unit x 20 unit = 40
        $this->assertDatabaseHas('detail_produksi_bahan', [
            'produksi_id' => $produksi->id,
            'barang_id' => $r['bahan']->id,
            'jumlah_rencana' => 40,
        ]);

        // Stok belum bergerak sedikit pun.
        $this->assertSame(1000, $r['bahan']->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_resep_milik_tahapan_lain_ditolak(): void
    {
        $r = $this->resep();
        TahapanProduksi::factory()->create(['kode_tahapan' => 'TP-02', 'urutan' => 2]);

        // Resep BOM-01 milik TP-01, dipakai di menu TP-02.
        $this->actingAs($this->pengguna())
            ->post(route('produksi.perintah.store', 'TP-02'), [
                'tanggal_produksi' => '2026-09-10',
                'bom_id' => $r['bom']->id,
                'jumlah_target' => 5,
            ])
            ->assertSessionHasErrors('bom_id');

        $this->assertDatabaseCount('produksi', 0);
    }

    public function test_alur_lengkap_draft_proses_selesai_menggerakkan_stok(): void
    {
        $r = $this->resep();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('produksi.perintah.store', 'TP-01'), [
            'tanggal_produksi' => '2026-09-10',
            'bom_id' => $r['bom']->id,
            'jumlah_target' => 20,
        ]);
        $produksi = Produksi::first();

        // draft -> proses
        $this->actingAs($pengguna)
            ->post(route('produksi.perintah.mulai', ['TP-01', $produksi]))
            ->assertSessionHas('sukses');
        $this->assertSame(Produksi::STATUS_PROSES, $produksi->fresh()->status);

        // catat realisasi: pakai 42 (lebih boros dari rencana 40), jadi 18, gagal 2
        $baris = $produksi->bahan()->first();
        $this->actingAs($pengguna)
            ->post(route('produksi.perintah.realisasi', ['TP-01', $produksi]), [
                'jumlah_hasil' => 18,
                'jumlah_gagal' => 2,
                'bahan' => [$baris->id => ['jumlah_pakai' => 42]],
            ])
            ->assertSessionHasNoErrors();

        // Realisasi tersimpan tapi stok belum bergerak.
        $this->assertEquals(42, (float) $baris->fresh()->jumlah_pakai);
        $this->assertDatabaseCount('mutasi_stok', 0);

        // proses -> selesai
        $this->actingAs($pengguna)
            ->post(route('produksi.perintah.selesaikan', ['TP-01', $produksi]))
            ->assertSessionHas('sukses');

        $this->assertSame(Produksi::STATUS_SELESAI, $produksi->fresh()->status);
        $this->assertSame(958, $r['bahan']->fresh()->stok_tersedia);   // 1000 - 42
        $this->assertSame(18, $r['output']->fresh()->stok_tersedia);   // hanya yang jadi
        $this->assertDatabaseCount('mutasi_stok', 2);
    }

    public function test_perintah_tidak_dapat_diselesaikan_bila_bahan_kurang(): void
    {
        $r = $this->resep(stokBahan: 10);
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('produksi.perintah.store', 'TP-01'), [
            'tanggal_produksi' => '2026-09-10',
            'bom_id' => $r['bom']->id,
            'jumlah_target' => 20,
        ]);
        $produksi = Produksi::first();

        $this->actingAs($pengguna)->post(route('produksi.perintah.mulai', ['TP-01', $produksi]));
        $this->actingAs($pengguna)->post(route('produksi.perintah.realisasi', ['TP-01', $produksi]), [
            'jumlah_hasil' => 20,
            'jumlah_gagal' => 0,
            'bahan' => [$produksi->bahan()->first()->id => ['jumlah_pakai' => 40]],
        ]);

        $this->actingAs($pengguna)
            ->post(route('produksi.perintah.selesaikan', ['TP-01', $produksi]))
            ->assertSessionHas('gagal');

        $this->assertSame(Produksi::STATUS_PROSES, $produksi->fresh()->status);
        $this->assertSame(10, $r['bahan']->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_perintah_yang_sudah_diproses_tidak_dapat_diubah_atau_dihapus(): void
    {
        $r = $this->resep();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('produksi.perintah.store', 'TP-01'), [
            'tanggal_produksi' => '2026-09-10',
            'bom_id' => $r['bom']->id,
            'jumlah_target' => 20,
        ]);
        $produksi = Produksi::first();
        $this->actingAs($pengguna)->post(route('produksi.perintah.mulai', ['TP-01', $produksi]));

        $this->actingAs($pengguna)->get(route('produksi.perintah.edit', ['TP-01', $produksi]))->assertSessionHas('gagal');
        $this->actingAs($pengguna)->delete(route('produksi.perintah.destroy', ['TP-01', $produksi]))->assertSessionHas('gagal');

        $this->assertDatabaseHas('produksi', ['id' => $produksi->id]);
    }

    public function test_perintah_dapat_dibatalkan_tanpa_menyentuh_stok(): void
    {
        $r = $this->resep();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('produksi.perintah.store', 'TP-01'), [
            'tanggal_produksi' => '2026-09-10',
            'bom_id' => $r['bom']->id,
            'jumlah_target' => 20,
        ]);
        $produksi = Produksi::first();

        $this->actingAs($pengguna)
            ->post(route('produksi.perintah.batal', ['TP-01', $produksi]))
            ->assertSessionHas('sukses');

        $this->assertSame(Produksi::STATUS_BATAL, $produksi->fresh()->status);
        $this->assertSame(1000, $r['bahan']->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_daftar_perintah_hanya_menampilkan_tahapan_yang_dibuka(): void
    {
        $r = $this->resep();
        $lain = TahapanProduksi::factory()->create(['kode_tahapan' => 'TP-02', 'urutan' => 2]);
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('produksi.perintah.store', 'TP-01'), [
            'tanggal_produksi' => '2026-09-10',
            'bom_id' => $r['bom']->id,
            'jumlah_target' => 20,
        ]);
        $nomor = Produksi::first()->no_produksi;

        $this->actingAs($pengguna)
            ->get(route('produksi.perintah.index', 'TP-01'))
            ->assertOk()
            ->assertSee($nomor);

        $this->actingAs($pengguna)
            ->get(route('produksi.perintah.index', 'TP-02'))
            ->assertOk()
            ->assertDontSee($nomor);
    }

    public function test_kode_tahapan_asing_menghasilkan_404(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('produksi.perintah.index', 'TP-99'))
            ->assertNotFound();
    }

    /**
     * IDOR relasi: {tahapan} dan {perintah} di URL adalah dua route-model-binding
     * yang berdiri sendiri-sendiri. Kode tahapan TP-02 di sini valid dan
     * perintahnya juga valid, cuma keduanya bukan pasangan yang benar (perintah
     * itu sebenarnya milik TP-01) — beda kasus dengan test kode tahapan asing
     * di atas yang kode tahapannya sendiri tidak ada.
     */
    public function test_perintah_tidak_bisa_diakses_lewat_kode_tahapan_yang_salah(): void
    {
        $r = $this->resep();
        $tahapan2 = TahapanProduksi::factory()->create(['kode_tahapan' => 'TP-02', 'urutan' => 2]);
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('produksi.perintah.store', 'TP-01'), [
            'tanggal_produksi' => '2026-09-10',
            'bom_id' => $r['bom']->id,
            'jumlah_target' => 20,
        ]);
        $produksi = Produksi::first();

        // BOM milik TP-02 sendiri, supaya validasi bom-vs-tahapan di
        // ProduksiRequest lolos duluan dan yang benar-benar diuji adalah
        // pengecekan kepemilikan tahapan di controller, bukan validasi lain.
        $bomTp2 = Bom::create([
            'kode_bom' => 'BOM-02', 'nama_bom' => 'Resep Lain',
            'barang_id' => $r['output']->id, 'tahapan_id' => $tahapan2->id,
            'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bomTp2->detail()->create(['barang_id' => $r['bahan']->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0]);

        $this->actingAs($pengguna)->get(route('produksi.perintah.show', ['TP-02', $produksi]))->assertNotFound();
        $this->actingAs($pengguna)->get(route('produksi.perintah.edit', ['TP-02', $produksi]))->assertNotFound();
        $this->actingAs($pengguna)->put(route('produksi.perintah.update', ['TP-02', $produksi]), [
            'tanggal_produksi' => '2026-09-10',
            'bom_id' => $bomTp2->id,
            'jumlah_target' => 20,
        ])->assertNotFound();
        $this->actingAs($pengguna)->delete(route('produksi.perintah.destroy', ['TP-02', $produksi]))->assertNotFound();
        $this->actingAs($pengguna)->post(route('produksi.perintah.mulai', ['TP-02', $produksi]))->assertNotFound();
        $baris = $produksi->bahan()->first();
        $this->actingAs($pengguna)->post(route('produksi.perintah.realisasi', ['TP-02', $produksi]), [
            'jumlah_hasil' => 1, 'jumlah_gagal' => 0,
            'bahan' => [$baris->id => ['jumlah_pakai' => 1]],
        ])->assertNotFound();
        $this->actingAs($pengguna)->post(route('produksi.perintah.selesaikan', ['TP-02', $produksi]))->assertNotFound();
        $this->actingAs($pengguna)->post(route('produksi.perintah.batal', ['TP-02', $produksi]))->assertNotFound();

        // Lewat kode tahapan yang benar, tetap berfungsi normal.
        $this->assertDatabaseHas('produksi', ['id' => $produksi->id, 'status' => Produksi::STATUS_DRAFT]);
    }

    public function test_mutasi_produksi_menunjuk_ke_perintahnya(): void
    {
        $r = $this->resep();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('produksi.perintah.store', 'TP-01'), [
            'tanggal_produksi' => '2026-09-10',
            'bom_id' => $r['bom']->id,
            'jumlah_target' => 10,
        ]);
        $produksi = Produksi::first();

        $this->actingAs($pengguna)->post(route('produksi.perintah.mulai', ['TP-01', $produksi]));
        $this->actingAs($pengguna)->post(route('produksi.perintah.realisasi', ['TP-01', $produksi]), [
            'jumlah_hasil' => 10,
            'jumlah_gagal' => 0,
            'bahan' => [$produksi->bahan()->first()->id => ['jumlah_pakai' => 20]],
        ]);
        $this->actingAs($pengguna)->post(route('produksi.perintah.selesaikan', ['TP-01', $produksi]));

        $this->assertDatabaseHas('mutasi_stok', [
            'jenis_mutasi' => MutasiStok::KELUAR,
            'sumber' => 'produksi',
            'referensi_tipe' => Produksi::class,
            'referensi_id' => $produksi->id,
        ]);
    }
}
