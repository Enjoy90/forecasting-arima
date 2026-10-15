<?php

namespace Tests\Feature\Master;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\Kategori;
use App\Models\TahapanProduksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji alur CRUD master tahapan produksi.
 *
 * Angka waktu_proses_hari & kapasitas_per_hari di sini adalah bahan
 * perhitungan waktu tunggu Modul B, jadi validasinya ikut diuji.
 */
class TahapanProduksiCrudTest extends TestCase
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
        $this->get(route('master.tahapan-produksi.index'))->assertRedirect(route('login'));
    }

    public function test_daftar_tampil_urut_menurut_urutan_tahapan(): void
    {
        TahapanProduksi::factory()->create(['kode_tahapan' => 'TP-03', 'nama_tahapan' => 'Proses Coating', 'urutan' => 3]);
        TahapanProduksi::factory()->create(['kode_tahapan' => 'TP-01', 'nama_tahapan' => 'Produksi Kepala', 'urutan' => 1]);
        TahapanProduksi::factory()->create(['kode_tahapan' => 'TP-02', 'nama_tahapan' => 'Produksi Handle', 'urutan' => 2]);

        $response = $this->actingAs($this->pengguna())
            ->get(route('master.tahapan-produksi.index'))
            ->assertOk();

        $isi = $response->getContent();

        $this->assertLessThan(strpos($isi, 'Produksi Handle'), strpos($isi, 'Produksi Kepala'));
        $this->assertLessThan(strpos($isi, 'Proses Coating'), strpos($isi, 'Produksi Handle'));
    }

    public function test_ringkasan_memakai_jalur_resep_dan_kapasitas_setara(): void
    {
        // Komponen 120 pcs/hari, pengemasan 20 dz/hari, 1 dz butuh 12 komponen.
        // Setara barang jadi: komponen 120 / 12 = 10 dz/hari, jadi komponen yang menghambat
        // walaupun angka kapasitasnya lebih besar. Dari nol: komponen 1 hari + kemas 1 hari = 2.
        $tahapKomponen = TahapanProduksi::factory()->create(['urutan' => 1, 'nama_tahapan' => 'Tahap Komponen', 'waktu_proses_hari' => 1, 'kapasitas_per_hari' => 120]);
        $tahapKemas = TahapanProduksi::factory()->create(['urutan' => 2, 'nama_tahapan' => 'Tahap Kemas', 'waktu_proses_hari' => 1, 'kapasitas_per_hari' => 20]);
        TahapanProduksi::factory()->nonaktif()->create(['urutan' => 3, 'waktu_proses_hari' => 10]);

        $jadi = Barang::factory()->barangJadi()->create(['satuan' => 'Dz']);
        $komponen = Barang::factory()->setengahJadi()->create(['satuan' => 'Pcs']);
        $bahan = Barang::factory()->create();
        $bomKomponen = Bom::create(['kode_bom' => 'BOM-K', 'nama_bom' => 'Komponen', 'barang_id' => $komponen->id, 'tahapan_id' => $tahapKomponen->id, 'jumlah_output' => 1, 'is_aktif' => true]);
        $bomKomponen->detail()->create(['barang_id' => $bahan->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);
        $bomJadi = Bom::create(['kode_bom' => 'BOM-J', 'nama_bom' => 'Kemas', 'barang_id' => $jadi->id, 'tahapan_id' => $tahapKemas->id, 'jumlah_output' => 1, 'is_aktif' => true]);
        $bomJadi->detail()->create(['barang_id' => $komponen->id, 'jumlah_kebutuhan' => 12, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $response = $this->actingAs($this->pengguna())
            ->get(route('master.tahapan-produksi.index'))
            ->assertOk()
            ->assertViewHas('lamaDariNol', 2.0)
            ->assertSee('Tahap Komponen')
            ->assertSee('120 Pcs');

        $penghambat = $response->viewData('penghambat');
        $this->assertSame($tahapKomponen->id, $penghambat['tahapan']->id);
        $this->assertEqualsWithDelta(10.0, $penghambat['setara'], 1e-9);
    }

    public function test_ringkasan_kosong_bila_belum_ada_resep(): void
    {
        TahapanProduksi::factory()->create(['urutan' => 1, 'waktu_proses_hari' => 1]);

        $this->actingAs($this->pengguna())
            ->get(route('master.tahapan-produksi.index'))
            ->assertOk()
            ->assertViewHas('lamaDariNol', null)
            ->assertSee('Belum ada resep');
    }

    public function test_tahapan_baru_tersimpan_dengan_kode_huruf_kapital(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.tahapan-produksi.store'), [
                'kode_tahapan' => 'tp-06',
                'nama_tahapan' => 'Pemeriksaan Mutu',
                'urutan' => 6,
                'waktu_proses_hari' => 0.5,
                'kapasitas_per_hari' => 250,
                'deskripsi' => 'Periksa hasil rakitan sebelum dikemas',
                'is_aktif' => '1',
            ])
            ->assertRedirect(route('master.tahapan-produksi.index'));

        $this->assertDatabaseHas('tahapan_produksi', [
            'kode_tahapan' => 'TP-06',
            'nama_tahapan' => 'Pemeriksaan Mutu',
            'urutan' => 6,
            'waktu_proses_hari' => 0.5,
        ]);
    }

    public function test_urutan_tidak_boleh_kembar(): void
    {
        TahapanProduksi::factory()->create(['urutan' => 1]);

        $this->actingAs($this->pengguna())
            ->post(route('master.tahapan-produksi.store'), [
                'kode_tahapan' => 'TP-99',
                'nama_tahapan' => 'Tahapan Bentrok',
                'urutan' => 1,
                'waktu_proses_hari' => 1,
                'kapasitas_per_hari' => 100,
                'is_aktif' => '1',
            ])
            ->assertSessionHasErrors('urutan');

        $this->assertDatabaseCount('tahapan_produksi', 1);
    }

    public function test_waktu_proses_dan_kapasitas_negatif_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.tahapan-produksi.store'), [
                'kode_tahapan' => 'TP-97',
                'nama_tahapan' => 'Angka Negatif',
                'urutan' => 7,
                'waktu_proses_hari' => -1,
                'kapasitas_per_hari' => -50,
                'is_aktif' => '1',
            ])
            ->assertSessionHasErrors(['waktu_proses_hari', 'kapasitas_per_hari']);

        $this->assertDatabaseCount('tahapan_produksi', 0);
    }

    public function test_kapasitas_nol_diterima_sebagai_tidak_dibatasi(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('master.tahapan-produksi.store'), [
                'kode_tahapan' => 'TP-08',
                'nama_tahapan' => 'Tanpa Batas Kapasitas',
                'urutan' => 8,
                'waktu_proses_hari' => 1,
                'kapasitas_per_hari' => 0,
                'is_aktif' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('master.tahapan-produksi.index'));

        $this->assertDatabaseHas('tahapan_produksi', [
            'kode_tahapan' => 'TP-08',
            'kapasitas_per_hari' => 0,
        ]);
    }

    public function test_tahapan_dapat_diubah_tanpa_terganjal_urutannya_sendiri(): void
    {
        $tahapan = TahapanProduksi::factory()->create([
            'kode_tahapan' => 'TP-01',
            'nama_tahapan' => 'Produksi Kepala',
            'urutan' => 1,
            'waktu_proses_hari' => 1,
        ]);

        $this->actingAs($this->pengguna())
            ->put(route('master.tahapan-produksi.update', $tahapan), [
                'kode_tahapan' => 'TP-01',
                'nama_tahapan' => 'Produksi Kepala Sekop',
                'urutan' => 1,
                'waktu_proses_hari' => 2.5,
                'kapasitas_per_hari' => 150,
                'is_aktif' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('master.tahapan-produksi.index'));

        $this->assertDatabaseHas('tahapan_produksi', [
            'id' => $tahapan->id,
            'nama_tahapan' => 'Produksi Kepala Sekop',
            'waktu_proses_hari' => 2.5,
        ]);
    }

    public function test_perubahan_waktu_proses_tercatat_di_log_aktivitas(): void
    {
        $tahapan = TahapanProduksi::factory()->create(['urutan' => 1, 'waktu_proses_hari' => 1]);

        $this->actingAs($this->pengguna())
            ->put(route('master.tahapan-produksi.update', $tahapan), [
                'kode_tahapan' => $tahapan->kode_tahapan,
                'nama_tahapan' => $tahapan->nama_tahapan,
                'urutan' => 1,
                'waktu_proses_hari' => 4,
                'kapasitas_per_hari' => 100,
                'is_aktif' => '1',
            ]);

        $log = \App\Models\LogAktivitas::latest('id')->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('waktu proses', $log->aktivitas);
        $this->assertStringContainsString('4', $log->aktivitas);
    }

    public function test_tahapan_tanpa_relasi_dapat_dihapus(): void
    {
        $tahapan = TahapanProduksi::factory()->create(['urutan' => 9]);

        $this->actingAs($this->pengguna())
            ->delete(route('master.tahapan-produksi.destroy', $tahapan))
            ->assertRedirect(route('master.tahapan-produksi.index'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('tahapan_produksi', ['id' => $tahapan->id]);
    }

    public function test_tahapan_yang_dipakai_bom_ditolak_saat_dihapus(): void
    {
        $tahapan = TahapanProduksi::factory()->create(['urutan' => 1]);
        $kategori = Kategori::create(['kode_kategori' => 'KTG-05', 'nama_kategori' => 'Setengah Jadi', 'keterangan' => null]);

        $barang = Barang::create([
            'kode_barang' => 'BRG-100',
            'nama_barang' => 'Kepala Sekop Mentah',
            'jenis_barang' => Barang::JENIS_SETENGAH_JADI,
            'kategori_id' => $kategori->id,
            'satuan' => 'Pcs',
            'stok_tersedia' => 0,
            'stok_minimum' => 0,
            'lead_time_hari' => 0,
        ]);

        Bom::create([
            'kode_bom' => 'BOM-001',
            'nama_bom' => 'Resep Kepala Sekop',
            'barang_id' => $barang->id,
            'tahapan_id' => $tahapan->id,
            'jumlah_output' => 1,
            'is_aktif' => true,
        ]);

        $this->actingAs($this->pengguna())
            ->delete(route('master.tahapan-produksi.destroy', $tahapan))
            ->assertRedirect(route('master.tahapan-produksi.index'))
            ->assertSessionHas('gagal');

        $this->assertDatabaseHas('tahapan_produksi', ['id' => $tahapan->id]);
    }
}
