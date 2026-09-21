<?php

namespace App\Services\Simulasi;

use App\Models\Barang;
use App\Models\Simulasi;
use App\Models\SimulasiDetail;
use App\Services\Arima\ArimaEstimator;
use App\Services\Arima\Forecaster;
use App\Services\Arima\StationarityTest;
use App\Services\Arima\TimeSeriesBuilder;
use App\Services\Produksi\LeadTimeCalculator;
use App\Support\Math\Distribution;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orkestrator backtesting rencana stok (docs/01 §7) -- tolak ukur utama
 * keberhasilan sistem menurut revisi sidang. 24 bulan pertama (dari 36 bulan
 * terakhir data historis) membentuk model ARIMA, 12 bulan terakhir dipakai
 * menjalankan dua skenario berdampingan dengan stok_awal, permintaan_aktual,
 * dan waktu tunggu yang identik -- yang berbeda hanya cara menentukan
 * rencana_produksi (KebijakanPerusahaan vs KebijakanSistem).
 */
class SimulationRunner
{
    /**
     * @param  array<int, float>|null  $produksiAktualSimulasi  wajib bila $metodePembanding='produksi_aktual'
     */
    public function jalankan(
        Barang $barang,
        string $metodePembanding,
        float $stokAwalSimulasi,
        float $biayaSimpanPerUnit,
        float $biayaStockoutPerUnit,
        ?array $produksiAktualSimulasi = null,
        ?int $userId = null
    ): Simulasi {
        $deret = (new TimeSeriesBuilder())->ambilDeret($barang);
        $n = count($deret);

        if ($n < 36) {
            throw new RuntimeException('Data historis minimal 36 periode untuk simulasi (24 bulan model + 12 bulan uji).');
        }

        $periode = $barang->dataTimeSeries()->orderBy('urutan_t')->pluck('periode')->all();
        $inSample = array_slice($deret, $n - 36, 24);
        $simulasiAktual = array_slice($deret, $n - 12, 12);
        $periodeSimulasi = array_slice($periode, $n - 12, 12);

        // Tahap 1-2 Box-Jenkins HANYA pada 24 bulan in-sample (docs/01 §7.5:
        // model tidak boleh melihat 12 bulan terakhir saat dibentuk).
        $ordo = (new StationarityTest())->tentukanOrdo($inSample);
        $grid = (new ArimaEstimator())->gridSearch($ordo['deret_stasioner']);
        $terpilih = collect($grid)->firstWhere('is_terpilih', true);
        if ($terpilih === null) {
            throw new RuntimeException('Tidak ada model ARIMA yang berhasil diestimasi dari data in-sample.');
        }

        $sigmaError = sqrt($terpilih['sigma_kuadrat']);
        $nilaiZ = Distribution::zScore((float) $barang->service_level / 100);

        // Waktu tunggu dihitung sekali dari rata-rata permintaan in-sample sebagai
        // jumlah representatif -- docs/01 memakai satu L_total tetap sepanjang
        // simulasi, bukan L_total yang berubah tiap bulan.
        $jumlahRataRata = array_sum($inSample) / count($inSample);
        $leadTimeTotal = (new LeadTimeCalculator())->hitung($barang, $jumlahRataRata)['lead_time_total_hari'];
        $safetyStock = $nilaiZ * $sigmaError * sqrt($leadTimeTotal / 30);
        $delay = (int) ceil($leadTimeTotal / 30);

        // Forecast diperpanjang $delay bulan supaya rencana_produksi bisa
        // menetralkan permintaan SEPANJANG waktu tunggu (docs/01 §7.2 & §7.5
        // poin 4), bukan cuma forecast bulan itu saja. Kolom pencatatan
        // prediksi_permintaan tetap memakai 12 bulan asli ($forecast).
        $forecastLengkap = (new Forecaster())->forecast($inSample, $ordo['ordo_d'], $terpilih, horizon: 12 + $delay)['forecast'];
        $forecast = array_slice($forecastLengkap, 0, 12);

        $rencanaPerusahaan = (new KebijakanPerusahaan())
            ->hitungSemua($inSample, $simulasiAktual, $metodePembanding, $produksiAktualSimulasi);

        $detailPerusahaan = $this->simulasikan(
            $simulasiAktual, $stokAwalSimulasi, $delay, $biayaSimpanPerUnit, $biayaStockoutPerUnit,
            fn (int $t, float $stokAwalT, array $rencanaSoFar): float => $rencanaPerusahaan[$t]
        );

        $kebijakanSistem = new KebijakanSistem();
        $detailSistem = $this->simulasikan(
            $simulasiAktual, $stokAwalSimulasi, $delay, $biayaSimpanPerUnit, $biayaStockoutPerUnit,
            function (int $t, float $stokAwalT, array $rencanaSoFar) use ($kebijakanSistem, $forecastLengkap, $safetyStock, $delay): float {
                // WIP(t) = pesanan yang sudah diputuskan tapi belum tiba per awal
                // bulan t: k memenuhi (k+delay >= t, belum tiba) dan (k < t, sudah
                // diputuskan) -> k di rentang [t-delay, t-1].
                $wip = 0.0;
                for ($k = max(0, $t - $delay); $k < $t; $k++) {
                    $wip += $rencanaSoFar[$k] ?? 0.0;
                }

                $forecastSepanjangWaktuTunggu = array_slice($forecastLengkap, $t, $delay + 1);

                return $kebijakanSistem->rencanaProduksi($forecastSepanjangWaktuTunggu, $safetyStock, $stokAwalT, $wip);
            }
        );

        $metric = new SimulationMetric();
        $metricPerusahaan = $metric->hitung($detailPerusahaan);
        $metricSistem = $metric->hitung($detailSistem);

        $penurunanOverstock = $metricPerusahaan['total_overstock_unit'] > 0
            ? ($metricPerusahaan['total_overstock_unit'] - $metricSistem['total_overstock_unit']) / $metricPerusahaan['total_overstock_unit'] * 100
            : 0.0;
        $penurunanStockout = $metricPerusahaan['total_stockout_unit'] > 0
            ? ($metricPerusahaan['total_stockout_unit'] - $metricSistem['total_stockout_unit']) / $metricPerusahaan['total_stockout_unit'] * 100
            : 0.0;
        $penghematanBiaya = $metricPerusahaan['total_biaya'] - $metricSistem['total_biaya'];
        $sistemLebihBaik = $metricSistem['total_overstock_unit'] <= $metricPerusahaan['total_overstock_unit']
            && $metricSistem['total_stockout_unit'] <= $metricPerusahaan['total_stockout_unit'];

        return DB::transaction(function () use (
            $barang, $userId, $metodePembanding, $nilaiZ, $leadTimeTotal, $stokAwalSimulasi,
            $biayaSimpanPerUnit, $biayaStockoutPerUnit, $metricPerusahaan, $metricSistem,
            $penurunanOverstock, $penurunanStockout, $penghematanBiaya, $sistemLebihBaik,
            $detailPerusahaan, $detailSistem, $safetyStock, $forecast, $periodeSimulasi
        ) {
            $simulasi = Simulasi::create([
                'kode_simulasi' => sprintf('SIM-%s-%s', $barang->kode_barang, now()->format('YmdHis')),
                'barang_id' => $barang->id,
                'user_id' => $userId,
                'periode_awal' => $periodeSimulasi[0],
                'periode_akhir' => $periodeSimulasi[11],
                'jumlah_periode' => 12,
                'stok_awal_simulasi' => $stokAwalSimulasi,
                'metode_pembanding' => $metodePembanding,
                'nilai_z' => $nilaiZ,
                'service_level' => (float) $barang->service_level,
                'lead_time_total_hari' => $leadTimeTotal,
                'biaya_simpan_per_unit' => $biayaSimpanPerUnit,
                'biaya_stockout_per_unit' => $biayaStockoutPerUnit,
                'pb_total_stockout_unit' => $metricPerusahaan['total_stockout_unit'],
                'pb_bulan_stockout' => $metricPerusahaan['bulan_stockout'],
                'pb_rata_stok_akhir' => $metricPerusahaan['rata_stok_akhir'],
                'pb_total_overstock_unit' => $metricPerusahaan['total_overstock_unit'],
                'pb_service_level_tercapai' => $metricPerusahaan['service_level_tercapai'],
                'pb_perputaran_persediaan' => $metricPerusahaan['perputaran_persediaan'],
                'pb_total_biaya' => $metricPerusahaan['total_biaya'],
                'sis_total_stockout_unit' => $metricSistem['total_stockout_unit'],
                'sis_bulan_stockout' => $metricSistem['bulan_stockout'],
                'sis_rata_stok_akhir' => $metricSistem['rata_stok_akhir'],
                'sis_total_overstock_unit' => $metricSistem['total_overstock_unit'],
                'sis_service_level_tercapai' => $metricSistem['service_level_tercapai'],
                'sis_perputaran_persediaan' => $metricSistem['perputaran_persediaan'],
                'sis_total_biaya' => $metricSistem['total_biaya'],
                'penurunan_overstock_persen' => $penurunanOverstock,
                'penurunan_stockout_persen' => $penurunanStockout,
                'penghematan_biaya' => $penghematanBiaya,
                'is_sistem_lebih_baik' => $sistemLebihBaik,
                'kesimpulan' => $this->buatKesimpulan($barang, $periodeSimulasi, $metricPerusahaan, $metricSistem, $penurunanOverstock, $penurunanStockout),
            ]);

            $this->simpanDetail($simulasi, Simulasi::SKENARIO_PERUSAHAAN, $detailPerusahaan, $periodeSimulasi, null, 0.0);
            $this->simpanDetail($simulasi, Simulasi::SKENARIO_SISTEM, $detailSistem, $periodeSimulasi, $forecast, $safetyStock);

            return $simulasi->fresh();
        });
    }

    /**
     * Jalankan alur bulanan (docs/01 §7.2) untuk satu skenario. Perbedaan
     * antar skenario hanya pada cara $tentukanRencanaProduksi menentukan
     * rencana_produksi(t); mekanisme stok-nya sama persis.
     *
     * @param  array<int, float>  $permintaanAktual  12 bulan
     * @param  callable(int, float, array<int, float>): float  $tentukanRencanaProduksi
     * @return array<int, array<string, float>>
     */
    private function simulasikan(array $permintaanAktual, float $stokAwalAwal, int $delay, float $biayaSimpanPerUnit, float $biayaStockoutPerUnit, callable $tentukanRencanaProduksi): array
    {
        $jumlahBulan = count($permintaanAktual);
        $rencanaProduksi = [];
        $hasil = [];
        $stokAwal = $stokAwalAwal;

        for ($t = 0; $t < $jumlahBulan; $t++) {
            $rencanaProduksi[$t] = $tentukanRencanaProduksi($t, $stokAwal, $rencanaProduksi);

            $indeksSumber = $t - $delay;
            $barangMasuk = $indeksSumber >= 0 ? $rencanaProduksi[$indeksSumber] : 0.0;

            $tersedia = $stokAwal + $barangMasuk;
            $terpenuhi = min($permintaanAktual[$t], $tersedia);
            $stockoutUnit = $permintaanAktual[$t] - $terpenuhi;
            $stokAkhir = $tersedia - $terpenuhi;
            $permintaanBerikutnya = $permintaanAktual[$t + 1] ?? 0.0;
            $overstockUnit = max(0.0, $stokAkhir - $permintaanBerikutnya);

            $hasil[$t] = [
                'stok_awal' => $stokAwal,
                'permintaan_aktual' => $permintaanAktual[$t],
                'rencana_produksi' => $rencanaProduksi[$t],
                'barang_masuk' => $barangMasuk,
                'terpenuhi' => $terpenuhi,
                'stockout_unit' => $stockoutUnit,
                'stok_akhir' => $stokAkhir,
                'overstock_unit' => $overstockUnit,
                'biaya_simpan' => $stokAkhir * $biayaSimpanPerUnit,
                'biaya_stockout' => $stockoutUnit * $biayaStockoutPerUnit,
            ];

            $stokAwal = $stokAkhir;
        }

        return $hasil;
    }

    /**
     * @param  array<int, array<string, float>>  $detail
     * @param  array<int, string>  $periodeSimulasi
     * @param  array<int, float>|null  $forecast
     */
    private function simpanDetail(Simulasi $simulasi, string $skenario, array $detail, array $periodeSimulasi, ?array $forecast, float $safetyStock): void
    {
        foreach ($detail as $t => $bulan) {
            SimulasiDetail::create([
                'simulasi_id' => $simulasi->id,
                'skenario' => $skenario,
                'periode' => $periodeSimulasi[$t],
                'urutan_t' => $t + 1,
                'stok_awal' => $bulan['stok_awal'],
                'permintaan_aktual' => $bulan['permintaan_aktual'],
                'prediksi_permintaan' => $forecast !== null ? $forecast[$t] : null,
                'safety_stock' => $safetyStock,
                'rencana_produksi' => $bulan['rencana_produksi'],
                'barang_masuk' => $bulan['barang_masuk'],
                'terpenuhi' => $bulan['terpenuhi'],
                'stockout_unit' => $bulan['stockout_unit'],
                'stok_akhir' => $bulan['stok_akhir'],
                'overstock_unit' => $bulan['overstock_unit'],
                'biaya_simpan' => $bulan['biaya_simpan'],
                'biaya_stockout' => $bulan['biaya_stockout'],
                'is_stockout' => $bulan['stockout_unit'] > 0,
            ]);
        }
    }

    /**
     * @param  array<int, string>  $periodeSimulasi
     * @param  array<string, float>  $metricPerusahaan
     * @param  array<string, float>  $metricSistem
     */
    private function buatKesimpulan(Barang $barang, array $periodeSimulasi, array $metricPerusahaan, array $metricSistem, float $penurunanOverstock, float $penurunanStockout): string
    {
        return sprintf(
            'Pada simulasi 12 bulan (%s s.d. %s) untuk %s, rekomendasi sistem %s kelebihan stok sebesar %.1f%% (dari %s unit menjadi %s unit) dan %s pesanan yang batal sebesar %.1f%% (dari %s unit menjadi %s unit), dengan service level %s dari %.1f%% menjadi %.1f%%.',
            $periodeSimulasi[0],
            $periodeSimulasi[11],
            $barang->nama_barang,
            $penurunanOverstock >= 0 ? 'menurunkan' : 'menaikkan',
            abs($penurunanOverstock),
            number_format($metricPerusahaan['total_overstock_unit'], 0, ',', '.'),
            number_format($metricSistem['total_overstock_unit'], 0, ',', '.'),
            $penurunanStockout >= 0 ? 'menurunkan' : 'menaikkan',
            abs($penurunanStockout),
            number_format($metricPerusahaan['total_stockout_unit'], 0, ',', '.'),
            number_format($metricSistem['total_stockout_unit'], 0, ',', '.'),
            $metricSistem['service_level_tercapai'] >= $metricPerusahaan['service_level_tercapai'] ? 'naik' : 'turun',
            $metricPerusahaan['service_level_tercapai'],
            $metricSistem['service_level_tercapai']
        );
    }
}
