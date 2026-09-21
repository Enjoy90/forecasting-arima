<?php

namespace App\Http\Controllers\Peramalan;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\LogAktivitas;
use App\Models\Peramalan;
use App\Services\Arima\BoxJenkinsPipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Proses Forecasting: menjalankan pipeline 4 tahap Box-Jenkins (docs/01 §5)
 * dan menampilkan seluruh bukti perhitungan sebagai laporan.
 */
class ForecastingController extends Controller
{
    private const MODUL = 'Proses Forecasting';

    public function index(): View
    {
        $daftarBarang = Barang::diramalkan()->aktif()->orderBy('kode_barang')->get();

        $riwayat = Peramalan::with('barang')
            ->latest()
            ->paginate(15);

        return view('peramalan.forecasting.index', [
            'daftarBarang' => $daftarBarang,
            'riwayat' => $riwayat,
        ]);
    }

    public function proses(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'barang_id' => ['required', 'exists:barang,id'],
            'horizon' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $barang = Barang::findOrFail($data['barang_id']);

        try {
            $peramalan = (new BoxJenkinsPipeline())->jalankan($barang, (int) $data['horizon'], auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        LogAktivitas::catat(self::MODUL, "Menjalankan peramalan {$barang->nama_barang}: {$peramalan->nama_model}");

        return redirect()
            ->route('peramalan.forecasting.show', $peramalan)
            ->with('sukses', "Peramalan selesai: {$peramalan->nama_model}, MAPE {$peramalan->mape}% ({$peramalan->kategori_akurasi}).");
    }

    public function show(Peramalan $peramalan): View
    {
        $peramalan->load([
            'barang',
            'ujiStasioneritas' => fn ($q) => $q->orderBy('differencing_ke'),
            'korelasiLag' => fn ($q) => $q->orderBy('jenis')->orderBy('lag'),
            'kandidatModel' => fn ($q) => $q->orderBy('ordo_p')->orderBy('ordo_q'),
            'parameterModel' => fn ($q) => $q->orderBy('lag'),
            'hasil' => fn ($q) => $q->orderBy('urutan_t'),
        ]);

        $hasilInSample = $peramalan->hasil->where('tipe', 'in_sample')->values();
        $hasilForecast = $peramalan->hasil->where('tipe', 'forecast')->values();

        return view('peramalan.forecasting.show', [
            'peramalan' => $peramalan,
            'hasilInSample' => $hasilInSample,
            'hasilForecast' => $hasilForecast,
            'dataAcf' => $this->dataCorrelogram($peramalan->korelasiLag->where('jenis', 'ACF')),
            'dataPacf' => $this->dataCorrelogram($peramalan->korelasiLag->where('jenis', 'PACF')),
            'dataForecastChart' => $this->dataForecastChart($hasilInSample, $hasilForecast),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dataCorrelogram($korelasiLag): array
    {
        return $korelasiLag->values()->map(fn ($d) => [
            'lag' => $d->lag,
            'nilai' => (float) $d->nilai,
            'batasAtas' => (float) $d->batas_atas,
            'batasBawah' => (float) $d->batas_bawah,
            'signifikan' => $d->is_signifikan,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function dataForecastChart($hasilInSample, $hasilForecast): array
    {
        $kosongSepanjangForecast = array_fill(0, $hasilForecast->count(), null);

        return [
            'labels' => [...$hasilInSample->pluck('periode')->all(), ...$hasilForecast->pluck('periode')->all()],
            'aktual' => [
                ...$hasilInSample->pluck('nilai_aktual')->map(fn ($v) => (float) $v)->all(),
                ...$kosongSepanjangForecast,
            ],
            'prediksi' => [
                ...$hasilInSample->pluck('nilai_prediksi')->map(fn ($v) => (float) $v)->all(),
                ...$hasilForecast->pluck('nilai_prediksi')->map(fn ($v) => (float) $v)->all(),
            ],
            'batasAtas' => [
                ...array_fill(0, $hasilInSample->count(), null),
                ...$hasilForecast->pluck('batas_atas')->map(fn ($v) => (float) $v)->all(),
            ],
            'batasBawah' => [
                ...array_fill(0, $hasilInSample->count(), null),
                ...$hasilForecast->pluck('batas_bawah')->map(fn ($v) => (float) $v)->all(),
            ],
        ];
    }
}
