{{--
    Template cetak satu simulasi (dompdf): kesimpulan, ringkasan berdampingan,
    dan rincian bulanan kedua skenario. Bukti pengujian utama untuk sidang.

    Sengaja tidak memakai Tailwind: dompdf tidak menjalankan pembangun CSS,
    jadi gayanya ditulis langsung sebagai CSS sederhana yang memang didukung.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Simulasi {{ $simulasi->kode_simulasi }}</title>
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111827;
        }

        .kop {
            text-align: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .kop .perusahaan { font-size: 14px; font-weight: bold; letter-spacing: 0.5px; }
        .kop .alamat { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .kop .judul { font-size: 12px; font-weight: bold; margin-top: 8px; text-transform: uppercase; }
        .kop .periode { font-size: 9px; color: #374151; margin-top: 2px; }

        .kesimpulan {
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .kesimpulan .label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        h3.subjudul {
            font-size: 10px;
            font-weight: bold;
            margin: 12px 0 4px 0;
            text-transform: uppercase;
        }

        table.ringkasan { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        table.ringkasan td {
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
        }
        table.ringkasan td.label { color: #6b7280; width: 18%; }
        table.ringkasan td.nilai { font-weight: bold; width: 15%; }

        table.banding { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.banding th {
            background: #e5e7eb;
            border: 1px solid #9ca3af;
            padding: 5px 6px;
            font-size: 8px;
            text-transform: uppercase;
            text-align: left;
        }
        table.banding td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
        }
        table.banding tr:nth-child(even) td { background: #f9fafb; }

        table.isi { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.isi th {
            background: #e5e7eb;
            border: 1px solid #9ca3af;
            padding: 5px 4px;
            font-size: 8px;
            text-transform: uppercase;
            text-align: left;
        }
        table.isi td {
            border: 1px solid #d1d5db;
            padding: 4px;
            vertical-align: top;
        }
        table.isi tr:nth-child(even) td { background: #f9fafb; }
        table.isi tr.stockout td { background: #fef2f2; }

        .kanan { text-align: right; }
        .tengah { text-align: center; }
        .merah { color: #b91c1c; font-weight: bold; }
        .kuning { color: #b45309; font-weight: bold; }

        .kaki {
            margin-top: 14px;
            font-size: 8px;
            color: #6b7280;
        }
    </style>
</head>
<body>

<div class="kop">
    <div class="perusahaan">CV. PANDE SEJAHTERA</div>
    <div class="alamat">Produsen Sekop &middot; Mojokerto, Jawa Timur</div>
    <div class="judul">Laporan Simulasi &amp; Pengujian Rencana Stok</div>
    <div class="periode">
        {{ $simulasi->kode_simulasi }} &middot; {{ $simulasi->barang->nama_barang }} &middot;
        Periode {{ $simulasi->periode_awal }} s/d {{ $simulasi->periode_akhir }} &middot;
        Pembanding: {{ str_replace('_', ' ', $simulasi->metode_pembanding) }}
    </div>
</div>

<div class="kesimpulan">
    <div class="label">
        {{ $simulasi->is_sistem_lebih_baik ? 'Kesimpulan: Sistem Lebih Baik' : 'Kesimpulan: Sistem Belum Lebih Baik' }}
    </div>
    <div>{{ $simulasi->kesimpulan }}</div>
</div>

<h3 class="subjudul">Ringkasan Berdampingan</h3>
<table class="banding">
    <thead>
        <tr>
            <th style="width: 40%;">Metrik</th>
            <th class="kanan">Perusahaan ({{ str_replace('_', ' ', $simulasi->metode_pembanding) }})</th>
            <th class="kanan">Sistem</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Total Stockout (unit)</td>
            <td class="kanan">{{ number_format($simulasi->pb_total_stockout_unit, 0, ',', '.') }}</td>
            <td class="kanan">{{ number_format($simulasi->sis_total_stockout_unit, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Bulan Terjadi Stockout</td>
            <td class="kanan">{{ $simulasi->pb_bulan_stockout }}</td>
            <td class="kanan">{{ $simulasi->sis_bulan_stockout }}</td>
        </tr>
        <tr>
            <td>Total Overstock (unit)</td>
            <td class="kanan">{{ number_format($simulasi->pb_total_overstock_unit, 0, ',', '.') }}</td>
            <td class="kanan">{{ number_format($simulasi->sis_total_overstock_unit, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Rata-rata Stok Akhir</td>
            <td class="kanan">{{ number_format($simulasi->pb_rata_stok_akhir, 1) }}</td>
            <td class="kanan">{{ number_format($simulasi->sis_rata_stok_akhir, 1) }}</td>
        </tr>
        <tr>
            <td>Service Level Tercapai</td>
            <td class="kanan">{{ number_format($simulasi->pb_service_level_tercapai, 2) }}%</td>
            <td class="kanan">{{ number_format($simulasi->sis_service_level_tercapai, 2) }}%</td>
        </tr>
        <tr>
            <td>Perputaran Persediaan</td>
            <td class="kanan">{{ number_format($simulasi->pb_perputaran_persediaan, 2) }}</td>
            <td class="kanan">{{ number_format($simulasi->sis_perputaran_persediaan, 2) }}</td>
        </tr>
        <tr>
            <td>Total Biaya</td>
            <td class="kanan">Rp {{ number_format($simulasi->pb_total_biaya, 0, ',', '.') }}</td>
            <td class="kanan">Rp {{ number_format($simulasi->sis_total_biaya, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<table class="ringkasan">
    <tr>
        <td class="label">Stok Awal Simulasi</td>
        <td class="nilai">{{ number_format($simulasi->stok_awal_simulasi, 0, ',', '.') }}</td>
        <td class="label">Waktu Tunggu Total</td>
        <td class="nilai">{{ number_format($simulasi->lead_time_total_hari, 1) }} hari</td>
    </tr>
    <tr>
        <td class="label">Nilai Z / Service Level</td>
        <td class="nilai">{{ number_format($simulasi->nilai_z, 4) }} ({{ number_format($simulasi->service_level, 1) }}%)</td>
        <td class="label">Biaya Simpan / Stockout per Unit</td>
        <td class="nilai">Rp {{ number_format($simulasi->biaya_simpan_per_unit, 0, ',', '.') }} / Rp {{ number_format($simulasi->biaya_stockout_per_unit, 0, ',', '.') }}</td>
    </tr>
</table>

@foreach ([['Kebijakan Perusahaan', $detailPerusahaan], ['Rekomendasi Sistem', $detailSistem]] as [$judulTabel, $baris])
    <h3 class="subjudul">Rincian Bulanan: {{ $judulTabel }}</h3>
    <table class="isi">
        <thead>
            <tr>
                <th>Periode</th>
                <th class="kanan">Stok Awal</th>
                <th class="kanan">Permintaan</th>
                <th class="kanan">Rencana Produksi</th>
                <th class="kanan">Barang Masuk</th>
                <th class="kanan">Terpenuhi</th>
                <th class="kanan">Stockout</th>
                <th class="kanan">Stok Akhir</th>
                <th class="kanan">Overstock</th>
                <th class="kanan">Biaya</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $b)
                <tr @class(['stockout' => $b->is_stockout])>
                    <td>{{ $b->periode }}</td>
                    <td class="kanan">{{ number_format($b->stok_awal, 0, ',', '.') }}</td>
                    <td class="kanan">{{ number_format($b->permintaan_aktual, 0, ',', '.') }}</td>
                    <td class="kanan">{{ number_format($b->rencana_produksi, 0, ',', '.') }}</td>
                    <td class="kanan">{{ number_format($b->barang_masuk, 0, ',', '.') }}</td>
                    <td class="kanan">{{ number_format($b->terpenuhi, 0, ',', '.') }}</td>
                    <td class="kanan @if ($b->stockout_unit > 0) merah @endif">{{ number_format($b->stockout_unit, 0, ',', '.') }}</td>
                    <td class="kanan">{{ number_format($b->stok_akhir, 0, ',', '.') }}</td>
                    <td class="kanan @if ($b->overstock_unit > 0) kuning @endif">{{ number_format($b->overstock_unit, 0, ',', '.') }}</td>
                    <td class="kanan">{{ number_format($b->total_biaya, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="tengah" colspan="10">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endforeach

<div class="kaki">
    dicetak {{ now()->translatedFormat('d F Y H:i') }} oleh {{ auth()->user()?->name ?? 'sistem' }}
</div>

</body>
</html>
