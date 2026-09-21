<?php

namespace App\Services\Arima;

use App\Models\Barang;
use App\Models\HasilPeramalan;
use App\Models\KandidatModel;
use App\Models\KorelasiLag;
use App\Models\ParameterModel;
use App\Models\Peramalan;
use App\Models\UjiStasioneritas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orkestrator 4 tahap Box-Jenkins (docs/01 §5): identifikasi -> estimasi ->
 * diagnostic checking -> peramalan. Menjalankan seluruh service Arima/*
 * berurutan lalu menyimpan tiap jejak perhitungan ke tabel terkait, supaya
 * dapat ditampilkan ulang sebagai bukti perhitungan di laporan.
 *
 * Catatan cakupan: bila diagnostic checking (Ljung-Box) tidak lolos, docs/01
 * menggambarkan alur kembali ke identifikasi. Pipeline ini TIDAK mengulang
 * otomatis (menghindari risiko loop tanpa kriteria berhenti yang jelas).
 * Hasilnya tetap disimpan dengan `lolos_ljung_box=false` supaya pengguna bisa
 * meninjau ulang dan menjalankan ulang secara manual dengan parameter lain.
 */
class BoxJenkinsPipeline
{
    public function jalankan(Barang $barang, int $horizon = 6, ?int $userId = null): Peramalan
    {
        $deret = (new TimeSeriesBuilder())->ambilDeret($barang);

        if (count($deret) < 24) {
            throw new RuntimeException('Data historis minimal 24 periode untuk membentuk model ARIMA.');
        }

        return DB::transaction(function () use ($barang, $deret, $horizon, $userId) {
            $identifikasi = $this->identifikasi($deret);
            $estimasi = $this->estimasi($identifikasi['deret_stasioner']);
            $forecast = (new Forecaster())->forecast($deret, $identifikasi['ordo_d'], $estimasi['terpilih'], $horizon);
            $diagnostik = (new DiagnosticChecker())->ljungBox(
                array_values($forecast['residual_in_sample']),
                $estimasi['terpilih']['ordo_p'],
                $estimasi['terpilih']['ordo_q']
            );
            $akurasi = $this->hitungAkurasi($deret, $forecast);

            $peramalan = $this->simpanHeader($barang, $userId, $deret, $identifikasi, $estimasi, $akurasi, $horizon);

            $this->simpanUjiStasioneritas($peramalan, $identifikasi['riwayat']);
            $this->simpanKorelasiLag($peramalan, $identifikasi);
            $this->simpanKandidatModel($peramalan, $identifikasi['ordo_d'], $estimasi['grid'], $diagnostik);
            $this->simpanParameterModel($peramalan, $estimasi['terpilih']['parameter']);
            $this->simpanHasilPeramalan($peramalan, $barang, $deret, $forecast);

            return $peramalan->fresh();
        });
    }

    /**
     * Tahap 1 (identifikasi): ordo differencing + ACF/PACF pada deret stasioner.
     */
    private function identifikasi(array $deret): array
    {
        $ordo = (new StationarityTest())->tentukanOrdo($deret);

        $ac = new AutoCorrelation();
        $deretStasioner = $ordo['deret_stasioner'];
        $maxLag = max(1, min(20, intdiv(count($deretStasioner), 2)));

        return $ordo + [
            'acf' => $ac->acf($deretStasioner, $maxLag),
            'pacf' => $ac->pacf($deretStasioner, $maxLag),
            'batas_signifikansi' => $ac->batasSignifikansi(count($deretStasioner)),
        ];
    }

    /**
     * Tahap 2 (estimasi): grid search, ambil model dengan AIC terkecil.
     */
    private function estimasi(array $deretStasioner): array
    {
        $grid = (new ArimaEstimator())->gridSearch($deretStasioner);
        $terpilih = collect($grid)->firstWhere('is_terpilih', true);

        if ($terpilih === null) {
            throw new RuntimeException('Tidak ada model ARIMA yang berhasil diestimasi dari data ini.');
        }

        return ['grid' => $grid, 'terpilih' => $terpilih];
    }

    private function hitungAkurasi(array $deret, array $forecast): array
    {
        $aktual = [];
        $prediksi = [];
        foreach ($forecast['fitted_in_sample'] as $t => $nilai) {
            $aktual[] = $deret[$t - 1];
            $prediksi[] = $nilai;
        }

        $acc = new AccuracyMetric();
        $mape = $acc->mape($aktual, $prediksi);

        return [
            'mape' => $mape,
            'rmse' => $acc->rmse($aktual, $prediksi),
            'mae' => $acc->mae($aktual, $prediksi),
            'kategori' => $acc->kategoriMape($mape),
        ];
    }

    private function simpanHeader(Barang $barang, ?int $userId, array $deret, array $identifikasi, array $estimasi, array $akurasi, int $horizon): Peramalan
    {
        $titikWaktu = $barang->dataTimeSeries()->orderBy('urutan_t')->get();
        $terpilih = $estimasi['terpilih'];
        $konstanta = collect($terpilih['parameter'])->firstWhere('jenis', 'KONSTANTA')['koefisien'] ?? 0.0;

        return Peramalan::create([
            'kode_peramalan' => sprintf('PRM-%s-%s', $barang->kode_barang, now()->format('YmdHis')),
            'barang_id' => $barang->id,
            'user_id' => $userId,
            'periode_awal' => $titikWaktu->first()->periode,
            'periode_akhir' => $titikWaktu->last()->periode,
            'jumlah_data' => count($deret),
            'ordo_p' => $terpilih['ordo_p'],
            'ordo_d' => $identifikasi['ordo_d'],
            'ordo_q' => $terpilih['ordo_q'],
            'konstanta' => $konstanta,
            'aic' => $terpilih['aic'],
            'bic' => $terpilih['bic'],
            'sigma_kuadrat' => $terpilih['sigma_kuadrat'],
            'mape' => $akurasi['mape'],
            'rmse' => $akurasi['rmse'],
            'mae' => $akurasi['mae'],
            'kategori_akurasi' => $akurasi['kategori'],
            'horizon' => $horizon,
            'status' => 'draft',
        ]);
    }

    private function simpanUjiStasioneritas(Peramalan $peramalan, array $riwayat): void
    {
        foreach ($riwayat as $r) {
            UjiStasioneritas::create(['peramalan_id' => $peramalan->id] + $r);
        }
    }

    private function simpanKorelasiLag(Peramalan $peramalan, array $identifikasi): void
    {
        $ac = new AutoCorrelation();
        $n = count($identifikasi['deret_stasioner']);
        $batas = $identifikasi['batas_signifikansi'];

        foreach (['ACF' => $identifikasi['acf'], 'PACF' => $identifikasi['pacf']] as $jenis => $nilaiPerLag) {
            foreach ($nilaiPerLag as $lag => $nilai) {
                KorelasiLag::create([
                    'peramalan_id' => $peramalan->id,
                    'jenis' => $jenis,
                    'lag' => $lag,
                    'nilai' => $nilai,
                    'batas_atas' => $batas,
                    'batas_bawah' => -$batas,
                    'is_signifikan' => $ac->isSignifikan($nilai, $n),
                ]);
            }
        }
    }

    private function simpanKandidatModel(Peramalan $peramalan, int $ordoD, array $grid, array $diagnostik): void
    {
        foreach ($grid as $kandidat) {
            $baris = [
                'peramalan_id' => $peramalan->id,
                'ordo_p' => $kandidat['ordo_p'],
                'ordo_d' => $ordoD,
                'ordo_q' => $kandidat['ordo_q'],
                'aic' => $kandidat['aic'],
                'bic' => $kandidat['bic'],
                'sse' => $kandidat['sse'],
                'sigma_kuadrat' => $kandidat['sigma_kuadrat'],
                'semua_signifikan' => $kandidat['semua_signifikan'],
                'is_terpilih' => $kandidat['is_terpilih'],
            ];

            // Hanya kandidat terpilih yang benar-benar didiagnosa; kolom ini
            // NOT NULL (default false), jadi yang lain dibiarkan pakai default,
            // bukan diisi false yang bisa disalahartikan sebagai "sudah diuji, gagal".
            if ($kandidat['is_terpilih']) {
                $baris['lolos_ljung_box'] = $diagnostik['lolos'];
            }

            KandidatModel::create($baris);
        }
    }

    private function simpanParameterModel(Peramalan $peramalan, array $parameter): void
    {
        foreach ($parameter as $par) {
            ParameterModel::create(['peramalan_id' => $peramalan->id] + $par);
        }
    }

    private function simpanHasilPeramalan(Peramalan $peramalan, Barang $barang, array $deret, array $forecast): void
    {
        $titikWaktu = $barang->dataTimeSeries()->orderBy('urutan_t')->get();

        foreach ($forecast['fitted_in_sample'] as $t => $prediksi) {
            $aktual = $deret[$t - 1];
            $residual = $forecast['residual_in_sample'][$t];

            HasilPeramalan::create([
                'peramalan_id' => $peramalan->id,
                'periode' => $titikWaktu[$t - 1]->periode,
                'urutan_t' => $t,
                'tipe' => 'in_sample',
                'nilai_aktual' => $aktual,
                'nilai_prediksi' => $prediksi,
                'residual' => $residual,
                'persen_error' => $aktual != 0 ? abs($residual / $aktual) * 100 : null,
            ]);
        }

        $periodeAkhir = Carbon::parse($titikWaktu->last()->periode.'-01');
        foreach ($forecast['forecast'] as $h => $nilai) {
            $periode = $periodeAkhir->copy()->addMonths($h + 1);

            HasilPeramalan::create([
                'peramalan_id' => $peramalan->id,
                'periode' => $periode->format('Y-m'),
                'urutan_t' => count($deret) + $h + 1,
                'tipe' => 'forecast',
                'nilai_prediksi' => $nilai,
                'batas_bawah' => $forecast['interval'][$h]['batas_bawah'],
                'batas_atas' => $forecast['interval'][$h]['batas_atas'],
            ]);
        }
    }
}
