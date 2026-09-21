<?php

namespace App\Exports;

use App\Models\Barang;
use App\Models\Pelanggan;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Berkas contoh untuk import penjualan.
 *
 * Dibuat di tempat (bukan berkas statis yang disimpan di repositori) supaya
 * susunan kolomnya dijamin selalu sama dengan yang dibaca PenjualanImport, dan
 * contoh isinya memakai kode barang serta nama pelanggan yang benar-benar ada
 * di master milik pengguna.
 */
class TemplatePenjualanExport implements FromArray, WithHeadings, WithTitle
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['tanggal', 'no_faktur', 'nama_pelanggan', 'kode_barang', 'jumlah', 'harga_satuan'];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $barangJadi = Barang::barangJadi()->orderBy('kode_barang')->first();
        $kode = $barangJadi?->kode_barang ?? 'BJ-01';
        $harga = $barangJadi ? (int) $barangJadi->harga_jual : 95000;

        $pelanggan = Pelanggan::orderBy('kode_pelanggan')->take(2)->pluck('nama_pelanggan')->all();
        $pelangganA = $pelanggan[0] ?? 'Toko Bangunan Sumber Rejeki';
        $pelangganB = $pelanggan[1] ?? 'UD Tani Makmur';

        $bulanLalu = Carbon::now()->subMonth();

        // Dua faktur contoh; faktur pertama sengaja punya dua baris untuk
        // menunjukkan bahwa no_faktur yang sama akan digabung jadi satu faktur.
        return [
            [$bulanLalu->copy()->day(5)->format('Y-m-d'), 'FJ-CONTOH-001', $pelangganA, $kode, 25, $harga],
            [$bulanLalu->copy()->day(5)->format('Y-m-d'), 'FJ-CONTOH-001', $pelangganA, $kode, 10, $harga],
            [$bulanLalu->copy()->day(18)->format('Y-m-d'), 'FJ-CONTOH-002', $pelangganB, $kode, 40, $harga],
        ];
    }

    public function title(): string
    {
        return 'Penjualan';
    }
}
