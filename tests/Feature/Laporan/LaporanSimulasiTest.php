<?php

namespace Tests\Feature\Laporan;

use App\Models\Barang;
use App\Models\Bom;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Models\Simulasi;
use App\Models\TahapanProduksi;
use App\Models\User;
use App\Services\Arima\TimeSeriesBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Uji Laporan Hasil Simulasi: rekap seluruh backtesting lintas simulasi,
 * berbeda dari halaman simulasi.show yang hanya menampilkan satu simulasi.
 */
class LaporanSimulasiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int> */
    private array $dataBj01 = [1044, 1228, 1266, 1222, 1146, 1132, 1068, 987, 951, 906, 1008, 1053,
        1180, 1405, 1533, 1452, 1487, 1421, 1217, 1271, 1203, 1106, 1274, 1205,
        1475, 1709, 1684, 1668, 1808, 1604, 1475, 1392, 1416, 1468, 1447, 1485];

    private function pengguna(): User
    {
        return User::factory()->create(['role' => User::ROLE_PIMPINAN, 'is_aktif' => true]);
    }

    /**
     * Bangun barang jadi lengkap dengan 36 bulan deret penjualan supaya
     * simulasi (butuh minimal 36 periode) bisa dijalankan lewat store().
     */
    private function barangDenganDeret(string $namaBarang = 'Sekop Uji Laporan'): Barang
    {
        $tahapan = TahapanProduksi::factory()->create(['waktu_proses_hari' => 1, 'kapasitas_per_hari' => 1000]);
        $barangJadi = Barang::factory()->barangJadi()->create(['nama_barang' => $namaBarang, 'service_level' => 95]);
        $bahanBaku = Barang::factory()->create(['lead_time_hari' => 9]);

        $bom = Bom::create([
            'kode_bom' => 'BOM-'.uniqid(), 'nama_bom' => 'Resep', 'barang_id' => $barangJadi->id,
            'tahapan_id' => $tahapan->id, 'jumlah_output' => 1, 'is_aktif' => true,
        ]);
        $bom->detail()->create(['barang_id' => $bahanBaku->id, 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'satuan' => 'Pcs']);

        $bulan = Carbon::parse('2023-01-01');
        foreach ($this->dataBj01 as $jumlah) {
            $penjualan = Penjualan::create([
                'no_faktur' => 'FK-'.uniqid(),
                'tanggal_penjualan' => $bulan->format('Y-m-15'),
                'total_harga' => $jumlah * 10000,
            ]);
            DetailPenjualan::create([
                'penjualan_id' => $penjualan->id, 'barang_id' => $barangJadi->id,
                'jumlah' => $jumlah, 'harga_satuan' => 10000, 'subtotal' => $jumlah * 10000,
            ]);
            $bulan->addMonth();
        }
        (new TimeSeriesBuilder())->bangun($barangJadi);

        return $barangJadi;
    }

    private function jalankanSimulasi(User $pengguna, Barang $barang): Simulasi
    {
        $this->actingAs($pengguna)->post(route('simulasi.store'), [
            'barang_id' => $barang->id,
            'metode_pembanding' => 'naif_bulan_lalu',
            'stok_awal_simulasi' => 200,
            'biaya_simpan_per_unit' => 500,
            'biaya_stockout_per_unit' => 2000,
        ]);

        return Simulasi::where('barang_id', $barang->id)->latest('id')->firstOrFail();
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('laporan.simulasi'))->assertRedirect(route('login'));
    }

    public function test_halaman_menampilkan_ringkasan_dan_baris_simulasi(): void
    {
        $pengguna = $this->pengguna();
        $barang = $this->barangDenganDeret();
        $simulasi = $this->jalankanSimulasi($pengguna, $barang);

        $dari = now()->startOfDay()->format('Y-m-d');
        $sampai = now()->endOfDay()->format('Y-m-d');

        $this->actingAs($pengguna)
            ->get(route('laporan.simulasi', ['dari' => $dari, 'sampai' => $sampai]))
            ->assertOk()
            ->assertSee($simulasi->kode_simulasi)
            ->assertSee('Sekop Uji Laporan')
            ->assertSee('Jumlah simulasi dijalankan')
            ->assertSee('Rata-rata penurunan stockout')
            ->assertSee('Total penghematan biaya');
    }

    public function test_unduh_pdf_menghasilkan_berkas_pdf(): void
    {
        $pengguna = $this->pengguna();
        $barang = $this->barangDenganDeret();
        $this->jalankanSimulasi($pengguna, $barang);

        // dompdf mengembalikan response biasa (bukan streamed), jadi isinya
        // dibaca lewat getContent(). Diperiksa lewat tanda tangan berkas
        // "%PDF" agar yakin yang terkirim benar-benar PDF, bukan halaman galat.
        $pdf = $this->actingAs($pengguna)->get(route('laporan.simulasi', ['unduh' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_unduh_excel_dapat_diunduh(): void
    {
        $pengguna = $this->pengguna();
        $barang = $this->barangDenganDeret();
        $this->jalankanSimulasi($pengguna, $barang);

        $this->actingAs($pengguna)
            ->get(route('laporan.simulasi', ['unduh' => 'excel']))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_penyaring_barang_mempersempit_hasil(): void
    {
        $pengguna = $this->pengguna();

        $barangA = $this->barangDenganDeret('Sekop A');
        $simulasiA = $this->jalankanSimulasi($pengguna, $barangA);

        $barangB = $this->barangDenganDeret('Sekop B');
        $simulasiB = $this->jalankanSimulasi($pengguna, $barangB);

        $this->actingAs($pengguna)
            ->get(route('laporan.simulasi', ['barang' => $barangA->id]))
            ->assertOk()
            ->assertSee($simulasiA->kode_simulasi)
            ->assertDontSee($simulasiB->kode_simulasi);
    }
}
