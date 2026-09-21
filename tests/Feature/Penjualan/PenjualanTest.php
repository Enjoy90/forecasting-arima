<?php

namespace Tests\Feature\Penjualan;

use App\Models\Barang;
use App\Models\MutasiStok;
use App\Models\Pelanggan;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji transaksi penjualan.
 *
 * Titik terpenting: stok berkurang seketika saat faktur dibuat, dan faktur
 * ditolak bila stok tidak mencukupi.
 */
class PenjualanTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_aktif' => true,
        ]);
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('penjualan.faktur.index'))->assertRedirect(route('login'));
    }

    public function test_faktur_baru_mengurangi_stok_dan_mencatat_mutasi(): void
    {
        $barang = Barang::factory()->barangJadi()->create(['stok_tersedia' => 100]);
        $pelanggan = Pelanggan::factory()->create();

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.faktur.store'), [
                'tanggal_penjualan' => '2026-09-05',
                'pelanggan_id' => $pelanggan->id,
                'detail' => [['barang_id' => $barang->id, 'jumlah' => 30, 'harga_satuan' => 95000]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $faktur = Penjualan::first();

        $this->assertSame('FJ-202609-0001', $faktur->no_faktur);
        $this->assertEquals(2850000, $faktur->total_harga);
        $this->assertSame(70, $barang->fresh()->stok_tersedia);

        $this->assertDatabaseHas('mutasi_stok', [
            'barang_id' => $barang->id,
            'jenis_mutasi' => MutasiStok::KELUAR,
            'sumber' => 'penjualan',
            'referensi_tipe' => Penjualan::class,
            'referensi_id' => $faktur->id,
            'jumlah' => 30,
        ]);
    }

    public function test_faktur_ditolak_bila_stok_tidak_cukup(): void
    {
        $barang = Barang::factory()->barangJadi()->create(['stok_tersedia' => 10]);

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.faktur.store'), [
                'tanggal_penjualan' => '2026-09-05',
                'detail' => [['barang_id' => $barang->id, 'jumlah' => 25, 'harga_satuan' => 95000]],
            ])
            ->assertSessionHas('gagal');

        // Tidak boleh ada sisa apa pun: faktur, baris, maupun mutasi.
        $this->assertDatabaseCount('penjualan', 0);
        $this->assertDatabaseCount('detail_penjualan', 0);
        $this->assertDatabaseCount('mutasi_stok', 0);
        $this->assertSame(10, $barang->fresh()->stok_tersedia);
    }

    public function test_seluruh_faktur_dibatalkan_bila_satu_baris_kekurangan_stok(): void
    {
        $cukup = Barang::factory()->barangJadi()->create(['stok_tersedia' => 100]);
        $kurang = Barang::factory()->barangJadi()->create(['stok_tersedia' => 5]);

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.faktur.store'), [
                'tanggal_penjualan' => '2026-09-05',
                'detail' => [
                    ['barang_id' => $cukup->id, 'jumlah' => 10, 'harga_satuan' => 1000],
                    ['barang_id' => $kurang->id, 'jumlah' => 50, 'harga_satuan' => 1000],
                ],
            ])
            ->assertSessionHas('gagal');

        // Baris pertama sempat diproses, tetapi transaksi mengembalikan semuanya.
        $this->assertDatabaseCount('penjualan', 0);
        $this->assertDatabaseCount('mutasi_stok', 0);
        $this->assertSame(100, $cukup->fresh()->stok_tersedia);
        $this->assertSame(5, $kurang->fresh()->stok_tersedia);
    }

    public function test_pembeli_boleh_diisi_nama_bebas(): void
    {
        $barang = Barang::factory()->barangJadi()->create(['stok_tersedia' => 100]);

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.faktur.store'), [
                'tanggal_penjualan' => '2026-09-05',
                'nama_pelanggan_manual' => 'Pembeli Eceran',
                'detail' => [['barang_id' => $barang->id, 'jumlah' => 2, 'harga_satuan' => 95000]],
            ])
            ->assertSessionHasNoErrors();

        $faktur = Penjualan::first();
        $this->assertNull($faktur->pelanggan_id);
        $this->assertSame('Pembeli Eceran', $faktur->nama_pelanggan_manual);
        $this->assertSame('Pembeli Eceran', $faktur->nama_pembeli);
    }

    public function test_ubah_faktur_hanya_menyentuh_kepala_bukan_baris_barang(): void
    {
        $barang = Barang::factory()->barangJadi()->create(['stok_tersedia' => 100]);
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('penjualan.faktur.store'), [
            'tanggal_penjualan' => '2026-09-05',
            'detail' => [['barang_id' => $barang->id, 'jumlah' => 30, 'harga_satuan' => 95000]],
        ]);

        $faktur = Penjualan::first();

        $this->actingAs($pengguna)
            ->put(route('penjualan.faktur.update', $faktur), [
                'tanggal_penjualan' => '2026-09-06',
                'nama_pelanggan_manual' => 'Nama Diperbaiki',
                'keterangan' => 'Koreksi keterangan',
                // Kiriman baris sengaja disertakan; harus diabaikan.
                'detail' => [['barang_id' => $barang->id, 'jumlah' => 999, 'harga_satuan' => 1]],
            ])
            ->assertSessionHasNoErrors();

        $faktur->refresh();
        $this->assertSame('Nama Diperbaiki', $faktur->nama_pelanggan_manual);
        $this->assertEquals(2850000, $faktur->total_harga);
        $this->assertSame(30, $faktur->detail->first()->jumlah);
        // Stok tidak ikut bergerak karena baris tidak berubah.
        $this->assertSame(70, $barang->fresh()->stok_tersedia);
    }

    public function test_hapus_faktur_mengembalikan_stok_lewat_mutasi_pengimbang(): void
    {
        $barang = Barang::factory()->barangJadi()->create(['stok_tersedia' => 100]);
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('penjualan.faktur.store'), [
            'tanggal_penjualan' => '2026-09-05',
            'detail' => [['barang_id' => $barang->id, 'jumlah' => 30, 'harga_satuan' => 95000]],
        ]);

        $faktur = Penjualan::first();
        $this->assertSame(70, $barang->fresh()->stok_tersedia);

        $this->actingAs($pengguna)
            ->delete(route('penjualan.faktur.destroy', $faktur))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('penjualan', ['id' => $faktur->id]);
        $this->assertSame(100, $barang->fresh()->stok_tersedia);

        // Mutasi lama tidak dihapus; ditambahkan mutasi masuk pengimbang.
        $this->assertDatabaseCount('mutasi_stok', 2);
        $this->assertDatabaseHas('mutasi_stok', [
            'jenis_mutasi' => MutasiStok::MASUK,
            'keterangan' => "Pembatalan faktur {$faktur->no_faktur}",
        ]);
    }

    public function test_hapus_faktur_lama_tanpa_mutasi_tidak_menambah_stok(): void
    {
        // Menirukan faktur hasil seeder / import: ada di tabel, tetapi
        // stoknya memang tidak pernah dikurangi.
        $barang = Barang::factory()->barangJadi()->create(['stok_tersedia' => 100]);

        $faktur = Penjualan::create([
            'no_faktur' => 'FJ-LAMA-0001',
            'tanggal_penjualan' => '2024-01-10',
            'total_harga' => 100000,
            'sumber_data' => 'import',
        ]);
        $faktur->detail()->create([
            'barang_id' => $barang->id,
            'jumlah' => 10,
            'harga_satuan' => 10000,
            'subtotal' => 100000,
        ]);

        $this->actingAs($this->pengguna())
            ->delete(route('penjualan.faktur.destroy', $faktur))
            ->assertSessionHas('sukses');

        $this->assertSame(100, $barang->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_barang_kembar_dalam_satu_faktur_ditolak(): void
    {
        $barang = Barang::factory()->barangJadi()->create(['stok_tersedia' => 100]);

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.faktur.store'), [
                'tanggal_penjualan' => '2026-09-05',
                'detail' => [
                    ['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000],
                    ['barang_id' => $barang->id, 'jumlah' => 3, 'harga_satuan' => 1000],
                ],
            ])
            ->assertSessionHasErrors('detail.1.barang_id');

        $this->assertDatabaseCount('penjualan', 0);
    }

    public function test_tanggal_masa_depan_ditolak(): void
    {
        $barang = Barang::factory()->barangJadi()->create(['stok_tersedia' => 100]);

        $this->actingAs($this->pengguna())
            ->post(route('penjualan.faktur.store'), [
                'tanggal_penjualan' => now()->addWeek()->format('Y-m-d'),
                'detail' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 1000]],
            ])
            ->assertSessionHasErrors('tanggal_penjualan');

        $this->assertDatabaseCount('penjualan', 0);
    }
}
