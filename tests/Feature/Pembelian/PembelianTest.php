<?php

namespace Tests\Feature\Pembelian;

use App\Models\Barang;
use App\Models\MutasiStok;
use App\Models\Pembelian;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji alur order pembelian dan penerimaan barang.
 *
 * Titik terpenting: stok baru boleh berubah saat PENERIMAAN, bukan saat
 * order dibuat.
 */
class PembelianTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_GUDANG,
            'is_aktif' => true,
        ]);
    }

    /**
     * @return array{0: Supplier, 1: Barang, 2: Barang}
     */
    private function bahan(): array
    {
        return [
            Supplier::factory()->create(),
            Barang::factory()->create(['stok_tersedia' => 100]),
            Barang::factory()->create(['stok_tersedia' => 20]),
        ];
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('pembelian.order.index'))->assertRedirect(route('login'));
        $this->get(route('pembelian.riwayat'))->assertRedirect(route('login'));
    }

    public function test_order_baru_tidak_mengubah_stok(): void
    {
        [$supplier, $barangA, $barangB] = $this->bahan();

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.order.store'), [
                'tanggal_pembelian' => '2026-09-01',
                'supplier_id' => $supplier->id,
                'keterangan' => 'Order uji',
                'detail' => [
                    ['barang_id' => $barangA->id, 'jumlah' => 50, 'harga_satuan' => 1000],
                    ['barang_id' => $barangB->id, 'jumlah' => 10, 'harga_satuan' => 2500],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $pembelian = Pembelian::first();

        $this->assertSame('dipesan', $pembelian->status);
        // 50*1000 + 10*2500 = 75.000
        $this->assertEquals(75000, $pembelian->total_harga);
        $this->assertCount(2, $pembelian->detail);

        // Stok belum boleh bergerak sedikit pun.
        $this->assertSame(100, $barangA->fresh()->stok_tersedia);
        $this->assertSame(20, $barangB->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_nomor_dokumen_dibuat_otomatis_dan_berurutan(): void
    {
        [$supplier, $barang] = $this->bahan();
        $pengguna = $this->pengguna();

        foreach ([1, 2] as $ke) {
            $this->actingAs($pengguna)->post(route('pembelian.order.store'), [
                'tanggal_pembelian' => '2026-09-05',
                'supplier_id' => $supplier->id,
                'detail' => [['barang_id' => $barang->id, 'jumlah' => 1, 'harga_satuan' => 100]],
            ]);
        }

        $this->assertDatabaseHas('pembelian', ['no_pembelian' => 'PB-202609-0001']);
        $this->assertDatabaseHas('pembelian', ['no_pembelian' => 'PB-202609-0002']);
    }

    public function test_penerimaan_menambah_stok_dan_mencatat_mutasi(): void
    {
        [$supplier, $barangA, $barangB] = $this->bahan();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('pembelian.order.store'), [
            'tanggal_pembelian' => '2026-09-01',
            'supplier_id' => $supplier->id,
            'detail' => [
                ['barang_id' => $barangA->id, 'jumlah' => 50, 'harga_satuan' => 1000],
                ['barang_id' => $barangB->id, 'jumlah' => 10, 'harga_satuan' => 2500],
            ],
        ]);

        $pembelian = Pembelian::first();

        $this->actingAs($pengguna)
            ->post(route('pembelian.order.terima', $pembelian), ['tanggal_terima' => '2026-09-10'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pembelian.order.show', $pembelian));

        $pembelian->refresh();
        $this->assertSame('diterima', $pembelian->status);
        $this->assertSame('2026-09-10', $pembelian->tanggal_terima->format('Y-m-d'));

        $this->assertSame(150, $barangA->fresh()->stok_tersedia);
        $this->assertSame(30, $barangB->fresh()->stok_tersedia);

        // Setiap baris menghasilkan satu mutasi MASUK yang menunjuk ke notanya.
        $this->assertDatabaseCount('mutasi_stok', 2);
        $this->assertDatabaseHas('mutasi_stok', [
            'barang_id' => $barangA->id,
            'jenis_mutasi' => MutasiStok::MASUK,
            'sumber' => 'pembelian',
            'referensi_tipe' => Pembelian::class,
            'referensi_id' => $pembelian->id,
            'jumlah' => 50,
        ]);
    }

    public function test_order_tidak_dapat_diterima_dua_kali(): void
    {
        [$supplier, $barang] = $this->bahan();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('pembelian.order.store'), [
            'tanggal_pembelian' => '2026-09-01',
            'supplier_id' => $supplier->id,
            'detail' => [['barang_id' => $barang->id, 'jumlah' => 25, 'harga_satuan' => 1000]],
        ]);

        $pembelian = Pembelian::first();

        $this->actingAs($pengguna)->post(route('pembelian.order.terima', $pembelian), ['tanggal_terima' => '2026-09-10']);
        $this->assertSame(125, $barang->fresh()->stok_tersedia);

        // Percobaan kedua harus ditolak, stok tidak boleh bertambah lagi.
        $this->actingAs($pengguna)
            ->post(route('pembelian.order.terima', $pembelian), ['tanggal_terima' => '2026-09-11'])
            ->assertSessionHas('gagal');

        $this->assertSame(125, $barang->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 1);
    }

    public function test_order_yang_sudah_diterima_tidak_dapat_diubah_atau_dihapus(): void
    {
        [$supplier, $barang] = $this->bahan();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('pembelian.order.store'), [
            'tanggal_pembelian' => '2026-09-01',
            'supplier_id' => $supplier->id,
            'detail' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 100]],
        ]);

        $pembelian = Pembelian::first();
        $this->actingAs($pengguna)->post(route('pembelian.order.terima', $pembelian), ['tanggal_terima' => '2026-09-10']);

        $this->actingAs($pengguna)->get(route('pembelian.order.edit', $pembelian))->assertSessionHas('gagal');

        $this->actingAs($pengguna)
            ->put(route('pembelian.order.update', $pembelian), [
                'tanggal_pembelian' => '2026-09-02',
                'supplier_id' => $supplier->id,
                'detail' => [['barang_id' => $barang->id, 'jumlah' => 999, 'harga_satuan' => 100]],
            ])
            ->assertSessionHas('gagal');

        $this->actingAs($pengguna)->delete(route('pembelian.order.destroy', $pembelian))->assertSessionHas('gagal');

        $pembelian->refresh();
        $this->assertSame('diterima', $pembelian->status);
        $this->assertEquals(500, $pembelian->total_harga);
        $this->assertDatabaseHas('pembelian', ['id' => $pembelian->id]);
    }

    public function test_order_dipesan_dapat_diubah_dan_dihapus(): void
    {
        [$supplier, $barang] = $this->bahan();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('pembelian.order.store'), [
            'tanggal_pembelian' => '2026-09-01',
            'supplier_id' => $supplier->id,
            'detail' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 100]],
        ]);

        $pembelian = Pembelian::first();

        $this->actingAs($pengguna)
            ->put(route('pembelian.order.update', $pembelian), [
                'tanggal_pembelian' => '2026-09-02',
                'supplier_id' => $supplier->id,
                'detail' => [['barang_id' => $barang->id, 'jumlah' => 8, 'harga_satuan' => 250]],
            ])
            ->assertSessionHasNoErrors();

        $pembelian->refresh();
        $this->assertEquals(2000, $pembelian->total_harga);
        $this->assertCount(1, $pembelian->detail);

        $this->actingAs($pengguna)->delete(route('pembelian.order.destroy', $pembelian))->assertSessionHas('sukses');
        $this->assertDatabaseMissing('pembelian', ['id' => $pembelian->id]);
        $this->assertDatabaseCount('detail_pembelian', 0);
    }

    public function test_order_dapat_dibatalkan_dan_tidak_menyentuh_stok(): void
    {
        [$supplier, $barang] = $this->bahan();
        $pengguna = $this->pengguna();

        $this->actingAs($pengguna)->post(route('pembelian.order.store'), [
            'tanggal_pembelian' => '2026-09-01',
            'supplier_id' => $supplier->id,
            'detail' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 100]],
        ]);

        $pembelian = Pembelian::first();

        $this->actingAs($pengguna)->post(route('pembelian.order.batal', $pembelian))->assertSessionHas('sukses');

        $this->assertSame('batal', $pembelian->fresh()->status);
        $this->assertSame(100, $barang->fresh()->stok_tersedia);
        $this->assertDatabaseCount('mutasi_stok', 0);

        // Order batal juga tidak boleh diterima.
        $this->actingAs($pengguna)
            ->post(route('pembelian.order.terima', $pembelian), ['tanggal_terima' => '2026-09-10'])
            ->assertSessionHas('gagal');

        $this->assertDatabaseCount('mutasi_stok', 0);
    }

    public function test_barang_kembar_dalam_satu_order_ditolak(): void
    {
        [$supplier, $barang] = $this->bahan();

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.order.store'), [
                'tanggal_pembelian' => '2026-09-01',
                'supplier_id' => $supplier->id,
                'detail' => [
                    ['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 100],
                    ['barang_id' => $barang->id, 'jumlah' => 3, 'harga_satuan' => 100],
                ],
            ])
            ->assertSessionHasErrors('detail.1.barang_id');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_order_tanpa_baris_barang_ditolak(): void
    {
        [$supplier] = $this->bahan();

        $this->actingAs($this->pengguna())
            ->post(route('pembelian.order.store'), [
                'tanggal_pembelian' => '2026-09-01',
                'supplier_id' => $supplier->id,
            ])
            ->assertSessionHasErrors('detail');

        $this->assertDatabaseCount('pembelian', 0);
    }

    public function test_riwayat_hanya_menampilkan_order_yang_diterima(): void
    {
        [$supplier, $barang] = $this->bahan();
        $pengguna = $this->pengguna();

        // Order pertama: diterima.
        $this->actingAs($pengguna)->post(route('pembelian.order.store'), [
            'tanggal_pembelian' => '2026-09-01',
            'supplier_id' => $supplier->id,
            'detail' => [['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 111]],
        ]);
        $diterima = Pembelian::first();
        $this->actingAs($pengguna)->post(route('pembelian.order.terima', $diterima), ['tanggal_terima' => '2026-09-10']);

        // Order kedua: masih dipesan.
        $this->actingAs($pengguna)->post(route('pembelian.order.store'), [
            'tanggal_pembelian' => '2026-09-02',
            'supplier_id' => $supplier->id,
            'detail' => [['barang_id' => $barang->id, 'jumlah' => 7, 'harga_satuan' => 222]],
        ]);

        $this->actingAs($pengguna)
            ->get(route('pembelian.riwayat'))
            ->assertOk()
            ->assertSee($diterima->no_pembelian)
            ->assertSee('111')
            ->assertDontSee('222');
    }
}
