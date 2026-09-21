{{--
    Menu Modul B - Analisis, Peramalan & Simulasi
    Pemilik file ini: pengerjaan Modul B. Modul A tidak menambah apa pun di sini.

    Cara mengaktifkan menu: ganti href="#" menjadi route yang sudah dibuat
    di routes/analisis.php, contoh: {{ route('peramalan.forecasting.index') }}
--}}

@php
    $itemClass = 'block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white';
    $headClass = 'px-3 mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500';
@endphp

<div>
    <p class="{{ $headClass }}">Peramalan (ARIMA)</p>
    <div class="space-y-0.5">
        <a href="{{ route('peramalan.historis.index') }}" class="{{ $itemClass }}">Data Historis</a>
        <a href="{{ route('peramalan.forecasting.index') }}" class="{{ $itemClass }}">Proses Forecasting</a>
        <a href="{{ route('peramalan.waktu-tunggu.index') }}" class="{{ $itemClass }}">Waktu Tunggu</a>
        <a href="{{ route('peramalan.target.index') }}" class="{{ $itemClass }}">Target Produksi</a>
    </div>
</div>

<div>
    <p class="{{ $headClass }}">Simulasi &amp; Pengujian</p>
    <div class="space-y-0.5">
        <a href="{{ route('simulasi.create') }}" class="{{ $itemClass }}">Jalankan Simulasi</a>
        <a href="{{ route('simulasi.index') }}" class="{{ $itemClass }}">Perbandingan Skenario</a>
        <a href="{{ route('simulasi.index') }}" class="{{ $itemClass }}">Rincian Bulanan</a>
        <a href="{{ route('laporan.simulasi') }}" class="{{ $itemClass }}">Laporan Hasil Simulasi</a>
    </div>
</div>
