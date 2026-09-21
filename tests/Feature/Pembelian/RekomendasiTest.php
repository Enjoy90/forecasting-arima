<?php

namespace Tests\Feature\Pembelian;

use App\Models\Barang;
use App\Models\KebutuhanBahan;
use App\Models\Pembelian;
use App\Models\Peramalan;
use App\Models\Supplier;
use App\Models\TargetProduksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji tombol "Buat Order dari Rekomendasi" — titik temu Modul A dan Modul B.
 *
 * Isi tabel `kebutuhan_bahan` di sini dibuat langsung, menirukan apa yang
 * nanti ditulis Modul B saat menghitung target produksi. Dengan begitu Modul A
 * dapat diuji penuh tanpa menunggu Modul B selesai.
 */
class RekomendasiTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_aktif' => true]);
    }

    /**
     * Menirukan keluaran Modul B: satu peramalan final yang melahirkan satu
     * target produksi. Kolomnya diisi seadanya karena yang diuji di sini
     * adalah pembacaannya oleh Modul A, bukan cara menghitungnya.
     */
    private function target(): TargetProduksi
    {
        $barangJadi = Barang::factory()->barangJadi()->create();

        $peramalan = Peramalan::create([
            'kode_peramalan' => 'FC-UJI-'.uniqid(),
            'barang_id' => $barangJadi->id,
            'periode_awal' => '2023-10',
            'periode_akhir' => '2026-09',
            'jumlah_data' => 36,
            'status' => 'final',
        ]);

        return TargetProduksi::create([
            'peramalan_id' => $peramalan->id,
            'barang_id' => $barangJadi->id,
            'periode' => '2026-10',
            'prediksi_penjualan' => 1500,
            'jumlah_target_produksi' => 100,
        ]);
    }

    private function rekomendasi(TargetProduksi $target, Barang $bahan, float $qty, string $status = 'perlu_beli'): KebutuhanBahan
    {
        return KebutuhanBahan::create([
            'target_produksi_id' => $target->id,
            'barang_id' => $bahan->id,
            'jumlah_kebutuhan' => $qty + 10,
            'stok_tersedia' => 10,
            'kekurangan' => $qty,
            'safety_stock_bahan' => 0,
            'qty_rekomendasi_beli' => $qty,
            'satuan' => $bahan->satuan,
            'status' => $status,
        ]);
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('pembelian.rekomendasi.index'))->assertRedirect(route('login'));
    }

    public function test_halaman_kosong_saat_modul_b_belum_mengisi(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('pembelian.rekomendasi.index'))
            ->assertOk()
            ->assertSee('Belum ada rekomendasi pembelian');
    }

    public function test_hanya_menampilkan_yang_perlu_dibeli(): void
    {
        $target = $this->target();
        $supplier = Supplier::factory()->create();

        $perlu = Barang::factory()->create(['nama_barang' => 'Bahan Kurang', 'supplier_id' => $supplier->id]);
        $cukup = Barang::factory()->create(['nama_barang' => 'Bahan Cukup', 'supplier_id' => $supplier->id]);

        $this->rekomendasi($target, $perlu, 50, 'perlu_beli');
        $this->rekomendasi($target, $cukup, 0, 'cukup');

        $this->actingAs($this->pengguna())
            ->get(route('pembelian.rekomendasi.index'))
            ->assertOk()
            ->assertSee('Bahan Kurang')
            ->assertDontSee('Bahan Cukup');
    }

    public function test_order_dibuat_dari_rekomendasi_terpilih(): void
    {
        $target = $this->target();
        $supplier = Supplier::factory()->create();
        $bahanA = Barang::factory()->create(['supplier_id' => $supplier->id, 'harga_beli' => 1000]);
        $bahanB = Barang::factory()->create(['supplier_id' => $supplier->id, 'harga_beli' => 2000]);

        $a = $this->rekomendasi($target, $bahanA, 50);
        $b = $this->rekomendasi($target, $bahanB, 25);

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.rekomendasi.store'), ['kebutuhan' => [$a->id, $b->id]])
            ->assertSessionHas('sukses')
            ->assertRedirect();

        $pembelian = Pembelian::first();

        $this->assertSame('dipesan', $pembelian->status);
        $this->assertSame($supplier->id, $pembelian->supplier_id);
        $this->assertCount(2, $pembelian->detail);
        // 50*1000 + 25*2000 = 100.000
        $this->assertEquals(100000, $pembelian->total_harga);

        // Stok belum bertambah: order masih menunggu penerimaan.
        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_jumlah_pecahan_dibulatkan_ke_atas(): void
    {
        $target = $this->target();
        $supplier = Supplier::factory()->create();
        $bahan = Barang::factory()->create(['supplier_id' => $supplier->id, 'harga_beli' => 1000]);

        $r = $this->rekomendasi($target, $bahan, 12.3);

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.rekomendasi.store'), ['kebutuhan' => [$r->id]]);

        // Memesan 12,3 lembar tidak mungkin; dibulatkan ke atas jadi 13.
        $this->assertDatabaseHas('detail_pembelian', ['barang_id' => $bahan->id, 'jumlah' => 13]);
    }

    public function test_barang_yang_muncul_dua_kali_digabung_jadi_satu_baris(): void
    {
        $target = $this->target();
        $supplier = Supplier::factory()->create();
        $bahan = Barang::factory()->create(['supplier_id' => $supplier->id, 'harga_beli' => 1000]);

        // Bahan yang sama dipakai pada dua tahapan berbeda.
        $a = $this->rekomendasi($target, $bahan, 30);
        $b = $this->rekomendasi($target, $bahan, 20);

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.rekomendasi.store'), ['kebutuhan' => [$a->id, $b->id]]);

        $this->assertDatabaseCount('detail_pembelian', 1);
        $this->assertDatabaseHas('detail_pembelian', ['barang_id' => $bahan->id, 'jumlah' => 50]);
    }

    public function test_bahan_dari_dua_supplier_ditolak(): void
    {
        $target = $this->target();
        $a = $this->rekomendasi($target, Barang::factory()->create(['supplier_id' => Supplier::factory()->create()->id]), 10);
        $b = $this->rekomendasi($target, Barang::factory()->create(['supplier_id' => Supplier::factory()->create()->id]), 10);

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.rekomendasi.store'), ['kebutuhan' => [$a->id, $b->id]])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_bahan_tanpa_supplier_dipisahkan_dan_tidak_dapat_diorder(): void
    {
        $target = $this->target();
        $bahan = Barang::factory()->setengahJadi()->create(['nama_barang' => 'Bahan Tanpa Pemasok']);
        $r = $this->rekomendasi($target, $bahan, 10);

        $this->actingAs($this->pengguna())
            ->get(route('pembelian.rekomendasi.index'))
            ->assertOk()
            ->assertSee('Belum Punya Supplier')
            ->assertSee('Bahan Tanpa Pemasok');

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.rekomendasi.store'), ['kebutuhan' => [$r->id]])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_tanpa_pilihan_ditolak(): void
    {
        $this->actingAs($this->pengguna())
            ->post(route('pembelian.rekomendasi.store'), [])
            ->assertSessionHasErrors('kebutuhan');
    }
}
