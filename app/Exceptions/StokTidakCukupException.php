<?php

namespace App\Exceptions;

use App\Models\Barang;
use RuntimeException;

/**
 * Dilempar saat sebuah mutasi KELUAR akan membuat stok menjadi minus.
 *
 * Stok minus tidak pernah masuk akal secara fisik: barang tidak bisa diambil
 * dari gudang bila barangnya tidak ada. Membiarkannya lolos akan merusak
 * seluruh perhitungan rencana stok di kemudian hari.
 */
class StokTidakCukupException extends RuntimeException
{
    public function __construct(
        public readonly Barang $barang,
        public readonly float $diminta,
        public readonly float $tersedia,
    ) {
        parent::__construct(sprintf(
            'Stok %s tidak mencukupi. Diminta %s %s, tersedia %s %s.',
            $barang->nama_barang,
            rtrim(rtrim(number_format($diminta, 4, ',', '.'), '0'), ','),
            $barang->satuan,
            rtrim(rtrim(number_format($tersedia, 4, ',', '.'), '0'), ','),
            $barang->satuan,
        ));
    }
}
