<?php

namespace App\Services\Arima;

use InvalidArgumentException;

/**
 * Metrik akurasi peramalan (docs/01 §5.1, Tahap 4): MAPE, RMSE, MAE.
 * MAPE juga jadi ukuran kualitas alat, bukan keberhasilan sistem (lihat
 * docs/01 §1.1), diterjemahkan ke kategori lewat kategoriMape().
 */
class AccuracyMetric
{
    /**
     * @param  array<int, float>  $aktual
     * @param  array<int, float>  $prediksi
     */
    public function mape(array $aktual, array $prediksi): float
    {
        $this->validasi($aktual, $prediksi);

        // Persen error tidak terdefinisi saat aktual nol (pembagian oleh nol) --
        // bulan itu dilewati, sama seperti hasil_peramalan.persen_error yang
        // disimpan null untuk kasus ini (lihat BoxJenkinsPipeline::simpanHasilPeramalan()).
        $jumlah = 0.0;
        $n = 0;
        foreach ($aktual as $i => $y) {
            if ($y == 0.0) {
                continue;
            }
            $jumlah += abs(($y - $prediksi[$i]) / $y);
            $n++;
        }

        return $n > 0 ? (100 / $n) * $jumlah : 0.0;
    }

    /**
     * @param  array<int, float>  $aktual
     * @param  array<int, float>  $prediksi
     */
    public function rmse(array $aktual, array $prediksi): float
    {
        $n = $this->validasi($aktual, $prediksi);

        $jumlah = 0.0;
        foreach ($aktual as $i => $y) {
            $jumlah += ($y - $prediksi[$i]) ** 2;
        }

        return sqrt($jumlah / $n);
    }

    /**
     * @param  array<int, float>  $aktual
     * @param  array<int, float>  $prediksi
     */
    public function mae(array $aktual, array $prediksi): float
    {
        $n = $this->validasi($aktual, $prediksi);

        $jumlah = 0.0;
        foreach ($aktual as $i => $y) {
            $jumlah += abs($y - $prediksi[$i]);
        }

        return $jumlah / $n;
    }

    /**
     * Kategori kualitas peramalan berdasarkan nilai MAPE (docs/01 §5.1).
     */
    public function kategoriMape(float $mape): string
    {
        return match (true) {
            $mape < 10 => 'Sangat Baik',
            $mape < 20 => 'Baik',
            $mape < 50 => 'Cukup',
            default => 'Buruk',
        };
    }

    private function validasi(array $aktual, array $prediksi): int
    {
        $n = count($aktual);
        if ($n === 0 || $n !== count($prediksi)) {
            throw new InvalidArgumentException('Jumlah data aktual dan prediksi harus sama dan tidak boleh kosong.');
        }

        return $n;
    }
}
