<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji ringkasan dashboard.
 *
 * Menggantikan uji asap lama yang hanya memeriksa layout ter-render; sekarang
 * angkanya ikut diperiksa karena dashboard sudah punya isi.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_aktif' => true]);
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_ringkasan_stok_dihitung_benar(): void
    {
        Barang::factory()->create(['stok_tersedia' => 100, 'stok_minimum' => 10, 'harga_beli' => 1000]);
        Barang::factory()->create(['stok_tersedia' => 5, 'stok_minimum' => 50, 'harga_beli' => 2000]);
        Barang::factory()->nonaktif()->create(['stok_tersedia' => 999, 'stok_minimum' => 0, 'harga_beli' => 5000]);

        $this->actingAs($this->pengguna())
            ->get(route('dashboard'))
            ->assertOk()
            // Barang nonaktif tidak ikut dihitung di mana pun.
            ->assertViewHas('jumlahBarangAktif', 2)
            ->assertViewHas('jumlahMenipis', 1)
            ->assertViewHas('nilaiPersediaan', 110000.0);
    }

    public function test_pekerjaan_yang_menunggu_ditampilkan(): void
    {
        $supplier = Supplier::factory()->create();

        Pembelian::create([
            'no_pembelian' => 'PB-MENUNGGU', 'tanggal_pembelian' => now(),
            'supplier_id' => $supplier->id, 'status' => 'dipesan', 'total_harga' => 0,
        ]);
        Pembelian::create([
            'no_pembelian' => 'PB-SELESAI', 'tanggal_pembelian' => now(), 'tanggal_terima' => now(),
            'supplier_id' => $supplier->id, 'status' => 'diterima', 'total_harga' => 0,
        ]);

        $this->actingAs($this->pengguna())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('orderMenunggu', 1)
            ->assertSee('menunggu penerimaan barang');
    }

    public function test_tren_penjualan_selalu_berisi_dua_belas_bulan(): void
    {
        $response = $this->actingAs($this->pengguna())->get(route('dashboard'))->assertOk();

        $tren = $response->viewData('trenPenjualan');

        // Bulan tanpa transaksi tetap ada dengan nilai nol, supaya bentuk
        // grafiknya tidak menyesatkan.
        $this->assertCount(12, $tren);
        $this->assertSame(0.0, $tren[0]['jumlah']);
    }

    public function test_penjualan_bulan_ini_dihitung_dari_faktur_bulan_berjalan(): void
    {
        $barang = Barang::factory()->barangJadi()->create();

        $bulanIni = Penjualan::create([
            'no_faktur' => 'FJ-BULAN-INI', 'tanggal_penjualan' => now(),
            'total_harga' => 500000,
        ]);
        $bulanIni->detail()->create(['barang_id' => $barang->id, 'jumlah' => 5, 'harga_satuan' => 100000, 'subtotal' => 500000]);

        $lalu = Penjualan::create([
            'no_faktur' => 'FJ-BULAN-LALU', 'tanggal_penjualan' => now()->subMonths(2),
            'total_harga' => 900000,
        ]);
        $lalu->detail()->create(['barang_id' => $barang->id, 'jumlah' => 9, 'harga_satuan' => 100000, 'subtotal' => 900000]);

        $response = $this->actingAs($this->pengguna())->get(route('dashboard'))->assertOk();

        $ringkas = $response->viewData('penjualanBulanIni');
        $this->assertSame(1, $ringkas['faktur']);
        $this->assertSame(5.0, $ringkas['jumlah']);
        $this->assertSame(500000.0, $ringkas['nilai']);
    }

    public function test_menu_kedua_modul_tetap_tampil(): void
    {
        $response = $this->actingAs($this->pengguna())->get(route('dashboard'))->assertOk();

        // Modul A
        $response->assertSee('Data Barang');
        $response->assertSee('BOM / Komposisi');
        $response->assertSee('Laporan Penjualan');
        // Modul B. "Jalankan Simulasi" tidak dicek: sejak RBAC diterapkan
        // (2026-09-22), menu itu cuma tampil untuk Pimpinan, pengguna di sini
        // admin yang cuma "lihat saja" (docs/01-alur-kerja-sistem.md §10).
        $response->assertSee('Proses Forecasting');
        $response->assertSee('Perbandingan Skenario');
    }
}
