{{--
    Menu Modul A - Operasional Pabrik
    Pemilik file ini: pengerjaan Modul A. Modul B tidak menambah apa pun di sini.

    Cara mengaktifkan menu: ganti href="#" menjadi route yang sudah dibuat
    di routes/operasional.php, contoh: {{ route('master.barang.index') }}
--}}

@php
    // Sementara semua href diisi '#'. Ganti satu per satu saat halamannya jadi.
    $itemClass = 'block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white';
    $headClass = 'px-3 mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500';
    $role = auth()->user()?->role;
@endphp

<div>
    <p class="{{ $headClass }}">Master Data</p>
    <div class="space-y-0.5">
        {{-- Kategori/Barang/Supplier/Pelanggan: admin penuh, gudang & pimpinan lihat saja, produksi tidak boleh akses. --}}
        @if (in_array($role, ['admin', 'gudang', 'pimpinan'], true))
            <a href="{{ route('master.kategori.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('master.kategori.*')])>Data Kategori</a>
            <a href="{{ route('master.barang.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('master.barang.*')])>Data Barang</a>
            <a href="{{ route('master.supplier.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('master.supplier.*')])>Data Supplier</a>
            <a href="{{ route('master.pelanggan.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('master.pelanggan.*')])>Data Pelanggan</a>
        @endif

        {{-- Tahapan Produksi: admin & produksi penuh, pimpinan lihat saja, gudang tidak boleh akses. --}}
        @if (in_array($role, ['admin', 'produksi', 'pimpinan'], true))
            <a href="{{ route('master.tahapan-produksi.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('master.tahapan-produksi.*')])>Tahapan Produksi</a>
        @endif

        @if ($role === 'admin')
            <a href="{{ route('master.pengguna.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('master.pengguna.*')])>Pengguna</a>
        @endif
    </div>
</div>

<div>
    <p class="{{ $headClass }}">Transaksi &amp; Operasional</p>
    <div class="space-y-0.5">
        {{-- Pembelian: admin & gudang penuh, pimpinan lihat saja, produksi tidak boleh akses. --}}
        @if (in_array($role, ['admin', 'gudang', 'pimpinan'], true))
            <a href="{{ route('pembelian.order.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('pembelian.order.*')])>Pembelian</a>
            <a href="{{ route('pembelian.riwayat') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('pembelian.riwayat')])>Riwayat Pembelian Bahan</a>
            <a href="{{ route('pembelian.rekomendasi.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('pembelian.rekomendasi.*')])>Rekomendasi Pembelian</a>
        @endif
        {{-- Import Pembelian mengubah data (bukan cuma lihat), jadi pimpinan yang cuma "lihat saja" tidak dapat menu ini. --}}
        @if (in_array($role, ['admin', 'gudang'], true))
            <a href="{{ route('pembelian.import.form') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('pembelian.import.*')])>Import Pembelian</a>
        @endif

        <p class="px-3 pt-3 pb-1 text-xs font-medium text-gray-500 uppercase tracking-wide">Produksi</p>
        {{-- BOM: admin & produksi penuh, pimpinan lihat saja, gudang tidak boleh akses. --}}
        @if (in_array($role, ['admin', 'produksi', 'pimpinan'], true))
            <a href="{{ route('produksi.bom.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('produksi.bom.*')])>BOM / Komposisi</a>
        @endif

        {{--
            Menu tahapan dibangkitkan dari master Tahapan Produksi, bukan ditulis
            satu per satu. Dengan begitu menambah atau mengganti nama tahapan
            cukup dilakukan lewat halaman master, tanpa menyentuh berkas ini.
            Keempat role setidaknya bisa lihat (admin & produksi penuh, gudang
            & pimpinan lihat saja), jadi menu ini tidak digate per role.
        --}}
        @foreach (\App\Models\TahapanProduksi::where('is_aktif', true)->orderBy('urutan')->get() as $tahapanMenu)
            <a href="{{ route('produksi.perintah.index', $tahapanMenu->kode_tahapan) }}"
               @class([
                   $itemClass,
                   'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('produksi.perintah.*')
                       && request()->route('tahapan')?->id === $tahapanMenu->id,
               ])>{{ $tahapanMenu->nama_tahapan }}</a>
        @endforeach

        {{-- Persediaan: keempat role setidaknya bisa lihat (admin & gudang penuh, produksi & pimpinan lihat saja). --}}
        <p class="px-3 pt-3 pb-1 text-xs font-medium text-gray-500 uppercase tracking-wide">Persediaan</p>
        <a href="{{ route('persediaan.stok') }}"
           @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('persediaan.stok')])>Stok Saat Ini</a>
        <a href="{{ route('persediaan.mutasi') }}"
           @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('persediaan.mutasi')])>Mutasi Stok</a>
        <a href="{{ route('persediaan.opname.index') }}"
           @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('persediaan.opname.*')])>Stok Opname</a>

        {{-- Penjualan: admin penuh, pimpinan lihat saja, produksi & gudang tidak boleh akses. --}}
        @if (in_array($role, ['admin', 'pimpinan'], true))
            <p class="px-3 pt-3 pb-1 text-xs font-medium text-gray-500 uppercase tracking-wide">Penjualan</p>
            <a href="{{ route('penjualan.faktur.index') }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('penjualan.faktur.*')])>Transaksi Penjualan</a>
            @if ($role === 'admin')
                <a href="{{ route('penjualan.import.form') }}"
                   @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs('penjualan.import.*')])>Import Penjualan</a>
            @endif
        @endif
    </div>
</div>

<div>
    <p class="{{ $headClass }}">Laporan</p>
    <div class="space-y-0.5">
        @foreach (['pembelian' => 'Laporan Pembelian', 'produksi' => 'Laporan Produksi', 'persediaan' => 'Laporan Persediaan', 'penjualan' => 'Laporan Penjualan'] as $kunci => $label)
            <a href="{{ route("laporan.{$kunci}") }}"
               @class([$itemClass, 'bg-indigo-600 text-white hover:bg-indigo-600' => request()->routeIs("laporan.{$kunci}")])>{{ $label }}</a>
        @endforeach
    </div>
</div>
