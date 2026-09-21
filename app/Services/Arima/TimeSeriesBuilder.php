<?php

namespace App\Services\Arima;

use App\Models\Barang;
use App\Models\DataTimeSeries;
use App\Models\DetailPenjualan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agregasi SUM(detail_penjualan.jumlah) per bulan untuk satu barang jadi
 * menjadi deret Zt, disimpan permanen ke data_time_series (docs/01 §5).
 */
class TimeSeriesBuilder
{
    /**
     * Bangun ulang deret Zt satu barang jadi dari seluruh transaksi penjualan.
     * Bulan tanpa penjualan tetap disertakan bernilai 0 agar deret tidak bolong,
     * karena urutan_t harus berkesinambungan untuk pipeline Box-Jenkins.
     *
     * @return Collection<int, DataTimeSeries>
     */
    public function bangun(Barang $barang): Collection
    {
        $agregatPerBulan = DetailPenjualan::query()
            ->join('penjualan', 'penjualan.id', '=', 'detail_penjualan.penjualan_id')
            ->where('detail_penjualan.barang_id', $barang->id)
            ->selectRaw("DATE_FORMAT(penjualan.tanggal_penjualan, '%Y-%m-01') as awal_bulan")
            ->selectRaw('SUM(detail_penjualan.jumlah) as total')
            ->groupBy('awal_bulan')
            ->pluck('total', 'awal_bulan');

        if ($agregatPerBulan->isEmpty()) {
            return collect();
        }

        $bulanMulai = Carbon::parse($agregatPerBulan->keys()->min())->startOfMonth();
        $bulanAkhir = Carbon::parse($agregatPerBulan->keys()->max())->startOfMonth();

        $hasil = collect();
        $urutan = 0;

        for ($bulan = $bulanMulai->copy(); $bulan->lessThanOrEqualTo($bulanAkhir); $bulan->addMonth()) {
            $urutan++;
            $kunciBulan = $bulan->format('Y-m-01');
            $nilaiZt = (float) ($agregatPerBulan[$kunciBulan] ?? 0);

            $hasil->push(DataTimeSeries::updateOrCreate(
                [
                    'barang_id' => $barang->id,
                    'periode' => $bulan->format('Y-m'),
                ],
                [
                    'tahun' => (int) $bulan->format('Y'),
                    'bulan' => (int) $bulan->format('n'),
                    'urutan_t' => $urutan,
                    'nilai_zt' => $nilaiZt,
                    'dihitung_pada' => now(),
                ]
            ));
        }

        return $hasil;
    }

    /**
     * Ambil deret Zt yang sudah tersimpan sebagai array angka murni,
     * terurut menurut urutan_t, siap dipakai tahap identifikasi Box-Jenkins.
     *
     * @return array<int, float>
     */
    public function ambilDeret(Barang $barang): array
    {
        return DataTimeSeries::query()
            ->where('barang_id', $barang->id)
            ->orderBy('urutan_t')
            ->pluck('nilai_zt')
            ->map(fn ($nilai) => (float) $nilai)
            ->all();
    }
}
