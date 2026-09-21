<?php

namespace App\Exports;

use App\Models\Barang;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Berkas contoh untuk import pembelian.
 *
 * Dibuat di tempat (bukan berkas statis yang disimpan di repositori) supaya
 * susunan kolomnya dijamin selalu sama dengan yang dibaca PembelianImport, dan
 * contoh isinya memakai kode supplier serta kode barang yang benar-benar ada
 * di master milik pengguna.
 */
class TemplatePembelianExport implements FromArray, WithHeadings, WithTitle
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['tanggal', 'no_pembelian', 'kode_supplier', 'kode_barang', 'jumlah', 'harga_satuan'];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $bahanBaku = Barang::bahanBaku()->orderBy('kode_barang')->take(2)->get(['kode_barang', 'harga_beli', 'supplier_id']);
        $supplier = Supplier::orderBy('kode_supplier')->first();
        $barangA = $bahanBaku->get(0);
        $barangB = $bahanBaku->get(1);

        $kodeSupplier = $supplier?->kode_supplier ?? 'SUP-01';
        $kodeBarangA = $barangA?->kode_barang ?? 'BB-01';
        $kodeBarangB = $barangB?->kode_barang ?? 'BB-02';
        $hargaA = $barangA ? (int) $barangA->harga_beli : 15000;
        $hargaB = $barangB ? (int) $barangB->harga_beli : 25000;

        $bulanLalu = Carbon::now()->subMonth();

        // Satu order contoh dengan dua baris barang, satu order lain dengan
        // satu baris, untuk menunjukkan bahwa no_pembelian yang sama akan
        // digabung jadi satu order.
        return [
            [$bulanLalu->copy()->day(5)->format('Y-m-d'), 'PB-CONTOH-001', $kodeSupplier, $kodeBarangA, 100, $hargaA],
            [$bulanLalu->copy()->day(5)->format('Y-m-d'), 'PB-CONTOH-001', $kodeSupplier, $kodeBarangB, 50, $hargaB],
            [$bulanLalu->copy()->day(18)->format('Y-m-d'), 'PB-CONTOH-002', $kodeSupplier, $kodeBarangA, 200, $hargaA],
        ];
    }

    public function title(): string
    {
        return 'Pembelian';
    }
}
