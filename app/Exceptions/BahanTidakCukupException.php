<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar saat perintah produksi hendak diselesaikan tetapi stok bahannya
 * tidak mencukupi.
 *
 * Berbeda dengan StokTidakCukupException yang berbicara tentang satu barang,
 * pengecualian ini membawa SELURUH kekurangan sekaligus. Staf produksi perlu
 * tahu semua bahan yang kurang dalam sekali lihat, bukan menemukannya satu per
 * satu lewat percobaan berulang.
 */
class BahanTidakCukupException extends RuntimeException
{
    /**
     * @param  list<array{nama:string, satuan:string, dibutuhkan:float, tersedia:float, kurang:float}>  $kekurangan
     */
    public function __construct(public readonly array $kekurangan)
    {
        $daftar = array_map(
            fn (array $k) => sprintf(
                '%s kurang %s %s (butuh %s, ada %s)',
                $k['nama'],
                $this->angka($k['kurang']),
                $k['satuan'],
                $this->angka($k['dibutuhkan']),
                $this->angka($k['tersedia']),
            ),
            $kekurangan
        );

        parent::__construct('Stok bahan tidak mencukupi: '.implode('; ', $daftar).'.');
    }

    private function angka(float $nilai): string
    {
        return rtrim(rtrim(number_format($nilai, 4, ',', '.'), '0'), ',');
    }
}
