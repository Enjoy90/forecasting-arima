<?php

namespace App\Services\Simulasi;

/**
 * Metrik perbandingan skenario backtesting (docs/01 §7.3): stockout,
 * overstock, service level, perputaran persediaan, dan total biaya.
 */
class SimulationMetric
{
    /**
     * @param  array<int, array{permintaan_aktual: float, terpenuhi: float, stockout_unit: float, stok_akhir: float, overstock_unit: float, biaya_simpan: float, biaya_stockout: float}>  $detailBulanan
     * @return array{total_stockout_unit: float, bulan_stockout: int, rata_stok_akhir: float, total_overstock_unit: float, service_level_tercapai: float, perputaran_persediaan: float, total_biaya: float}
     */
    public function hitung(array $detailBulanan): array
    {
        $n = count($detailBulanan);

        $totalStockout = 0.0;
        $bulanStockout = 0;
        $totalStokAkhir = 0.0;
        $totalOverstock = 0.0;
        $totalTerpenuhi = 0.0;
        $totalPermintaan = 0.0;
        $totalBiaya = 0.0;

        foreach ($detailBulanan as $bulan) {
            $totalStockout += $bulan['stockout_unit'];
            $bulanStockout += $bulan['stockout_unit'] > 0 ? 1 : 0;
            $totalStokAkhir += $bulan['stok_akhir'];
            $totalOverstock += $bulan['overstock_unit'];
            $totalTerpenuhi += $bulan['terpenuhi'];
            $totalPermintaan += $bulan['permintaan_aktual'];
            $totalBiaya += $bulan['biaya_simpan'] + $bulan['biaya_stockout'];
        }

        $rataStokAkhir = $n > 0 ? $totalStokAkhir / $n : 0.0;

        return [
            'total_stockout_unit' => $totalStockout,
            'bulan_stockout' => $bulanStockout,
            'rata_stok_akhir' => $rataStokAkhir,
            'total_overstock_unit' => $totalOverstock,
            'service_level_tercapai' => $totalPermintaan > 0 ? ($totalTerpenuhi / $totalPermintaan) * 100 : 100.0,
            'perputaran_persediaan' => $rataStokAkhir > 0 ? $totalPermintaan / $rataStokAkhir : 0.0,
            'total_biaya' => $totalBiaya,
        ];
    }
}
