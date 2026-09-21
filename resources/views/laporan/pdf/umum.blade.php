{{--
    Template cetak untuk keempat laporan operasional (dompdf).

    Sengaja tidak memakai Tailwind: dompdf tidak menjalankan pembangun CSS,
    jadi gayanya ditulis langsung sebagai CSS sederhana yang memang didukung.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
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

        table.ringkasan { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        table.ringkasan td {
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
        }
        table.ringkasan td.label { color: #6b7280; width: 18%; }
        table.ringkasan td.nilai { font-weight: bold; width: 15%; }

        table.isi { width: 100%; border-collapse: collapse; }
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
        .kanan { text-align: right; }
        .tengah { text-align: center; }

        .kosong { text-align: center; padding: 20px; color: #6b7280; }

        .kaki {
            margin-top: 14px;
            font-size: 8px;
            color: #6b7280;
        }
        .ttd {
            margin-top: 30px;
            width: 100%;
        }
        .ttd td { width: 50%; font-size: 9px; vertical-align: top; }
        .ttd .garis { margin-top: 45px; border-top: 1px solid #111827; width: 55%; }
    </style>
</head>
<body>

<div class="kop">
    <div class="perusahaan">CV. PANDE SEJAHTERA</div>
    <div class="alamat">Produsen Sekop &middot; Mojokerto, Jawa Timur</div>
    <div class="judul">{{ $judul }}</div>
    <div class="periode">
        Periode {{ $dari->translatedFormat('d F Y') }} s/d {{ $sampai->translatedFormat('d F Y') }}
    </div>
</div>

@if ($laporan['ringkasan'] !== [])
    <table class="ringkasan">
        <tr>
            @foreach ($laporan['ringkasan'] as $label => $nilai)
                <td class="label">{{ $label }}</td>
                <td class="nilai">{{ $nilai }}</td>
                @if ($loop->iteration % 2 === 0 && ! $loop->last)
        </tr><tr>
                @endif
            @endforeach
        </tr>
    </table>
@endif

<table class="isi">
    <thead>
        <tr>
            <th style="width: 4%;">No</th>
            @foreach ($laporan['kolom'] as $i => $kolom)
                <th class="{{ ($laporan['perataan'][$i] ?? 'left') === 'right' ? 'kanan' : '' }}">{{ $kolom }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($laporan['baris'] as $n => $baris)
            <tr>
                <td class="tengah">{{ $n + 1 }}</td>
                @foreach ($baris as $i => $sel)
                    <td class="{{ ($laporan['perataan'][$i] ?? 'left') === 'right' ? 'kanan' : '' }}">{{ $sel }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td class="kosong" colspan="{{ count($laporan['kolom']) + 1 }}">
                    Tidak ada data pada rentang tanggal ini.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="kaki">
    {{ count($laporan['baris']) }} baris data &middot;
    dicetak {{ now()->translatedFormat('d F Y H:i') }} oleh {{ auth()->user()?->name ?? 'sistem' }}
</div>

<table class="ttd">
    <tr>
        <td>Mengetahui,<br>Pimpinan
            <div class="garis"></div>
        </td>
        <td>Mojokerto, {{ now()->translatedFormat('d F Y') }}<br>Petugas
            <div class="garis"></div>
        </td>
    </tr>
</table>

</body>
</html>
