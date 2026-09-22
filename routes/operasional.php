<?php

/*
|--------------------------------------------------------------------------
| Route Modul A - Operasional Pabrik
|--------------------------------------------------------------------------
|
| Pemilik file ini: pengerjaan Modul A. Modul B tidak menambah apa pun di sini.
|
| Cakupan : master data, pembelian, produksi, persediaan, penjualan,
|           laporan operasional
| Folder  : app/Http/Controllers/{Master,Pembelian,Produksi,Persediaan,
|           Penjualan,Laporan}
|
*/

use App\Http\Controllers\Master\BarangController;
use App\Http\Controllers\Master\KategoriController;
use App\Http\Controllers\Laporan\LaporanPembelianController;
use App\Http\Controllers\Laporan\LaporanPenjualanController;
use App\Http\Controllers\Laporan\LaporanPersediaanController;
use App\Http\Controllers\Laporan\LaporanProduksiController;
use App\Http\Controllers\Master\PelangganController;
use App\Http\Controllers\Master\PenggunaController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\TahapanProduksiController;
use App\Http\Controllers\Pembelian\ImportPembelianController;
use App\Http\Controllers\Pembelian\PembelianController;
use App\Http\Controllers\Pembelian\PenerimaanController;
use App\Http\Controllers\Pembelian\RekomendasiController;
use App\Http\Controllers\Penjualan\ImportPenjualanController;
use App\Http\Controllers\Penjualan\PenjualanController;
use App\Http\Controllers\Persediaan\MutasiStokController;
use App\Http\Controllers\Persediaan\OpnameController;
use App\Http\Controllers\Persediaan\StokController;
use App\Http\Controllers\Produksi\BomController;
use App\Http\Controllers\Produksi\ProduksiController;
use App\Models\TahapanProduksi;
use Illuminate\Support\Facades\Route;

/*
 * Parameter {tahapan} pada menu produksi diikat ke kolom kode_tahapan
 * (mis. TP-01), bukan ke id. Kode lebih stabil daripada nama yang boleh
 * diubah pengguna, dan lebih mudah dibaca di alamat halaman daripada id.
 */
Route::bind('tahapan', fn (string $nilai) => TahapanProduksi::query()
    ->where('kode_tahapan', strtoupper($nilai))
    ->firstOrFail());

Route::middleware(['auth', 'verified'])->group(function () {

    // -----------------------------------------------------------------
    // MASTER DATA
    //
    // Hak akses per docs/01-alur-kerja-sistem.md §10: kategori/barang/
    // supplier/pelanggan -> admin penuh, gudang & pimpinan lihat saja,
    // produksi tidak boleh akses. Tahapan Produksi -> admin & produksi
    // penuh, pimpinan lihat saja, gudang tidak boleh akses. Pengguna ->
    // admin saja.
    // -----------------------------------------------------------------
    Route::prefix('master')->name('master.')->group(function () {
        // Penting: grup "tulis" (punya rute literal /create) harus didaftar
        // LEBIH DULU daripada grup "baca" (punya rute /{id} buat show). Kalau
        // kebalik, rute show yang wildcard-nya "{id}" keburu menangkap kata
        // "create" sebagai id sebelum sempat sampai ke rute create aslinya,
        // dan Laravel berhenti di situ (bukan lanjut ke rute berikutnya yang
        // juga cocok) sehingga hasilnya 404 alih-alih halaman create.
        Route::middleware('role:admin')->group(function () {
            Route::resource('kategori', KategoriController::class)->except(['index', 'show']);
            Route::resource('barang', BarangController::class)->except(['index', 'show']);
            Route::resource('supplier', SupplierController::class)->except(['index', 'show']);
            Route::resource('pelanggan', PelangganController::class)->except(['index', 'show']);
            Route::resource('pengguna', PenggunaController::class);
        });

        Route::middleware('role:admin,gudang,pimpinan')->group(function () {
            Route::resource('kategori', KategoriController::class)->only(['index', 'show']);
            Route::resource('barang', BarangController::class)->only(['index', 'show']);
            Route::resource('supplier', SupplierController::class)->only(['index', 'show']);
            Route::resource('pelanggan', PelangganController::class)->only(['index', 'show']);
        });

        Route::middleware('role:admin,produksi')->group(function () {
            Route::resource('tahapan-produksi', TahapanProduksiController::class)->except(['index', 'show']);
        });

        Route::middleware('role:admin,produksi,pimpinan')->group(function () {
            Route::resource('tahapan-produksi', TahapanProduksiController::class)->only(['index', 'show']);
        });
    });

    // -----------------------------------------------------------------
    // PEMBELIAN
    //
    // Hak akses: admin & gudang penuh, pimpinan lihat saja, produksi
    // tidak boleh akses sama sekali.
    // -----------------------------------------------------------------
    Route::prefix('pembelian')->name('pembelian.')->group(function () {
        // Sama seperti Master Data: grup "tulis" (rute literal /order/create)
        // wajib didaftar sebelum grup "baca" (rute /order/{id} buat show),
        // supaya show tidak menangkap "create" duluan sebagai id.
        Route::middleware('role:admin,gudang')->group(function () {
            Route::resource('order', PembelianController::class)->except(['index', 'show']);

            // Dua tindakan yang mengubah keadaan order, di luar CRUD biasa.
            Route::post('order/{order}/terima', [PenerimaanController::class, 'store'])->name('order.terima');
            Route::post('order/{order}/batal', [PembelianController::class, 'batal'])->name('order.batal');

            // Titik temu dengan Modul B: membaca tabel kebutuhan_bahan yang diisi
            // perhitungan target produksi, lalu mengubahnya menjadi order pembelian.
            Route::post('rekomendasi', [RekomendasiController::class, 'store'])->name('rekomendasi.store');

            Route::get('import', [ImportPembelianController::class, 'form'])->name('import.form');
            Route::get('import/template', [ImportPembelianController::class, 'template'])->name('import.template');
            Route::post('import', [ImportPembelianController::class, 'store'])->name('import.store');
        });

        Route::middleware('role:admin,gudang,pimpinan')->group(function () {
            Route::get('riwayat', [PembelianController::class, 'riwayat'])->name('riwayat');
            Route::get('rekomendasi', [RekomendasiController::class, 'index'])->name('rekomendasi.index');
            Route::resource('order', PembelianController::class)->only(['index', 'show']);
        });
    });

    // -----------------------------------------------------------------
    // PRODUKSI
    //
    // Hak akses: BOM -> admin & produksi penuh, pimpinan lihat saja, gudang
    // tidak boleh akses. Perintah (5 tahap) -> admin & produksi penuh,
    // gudang & pimpinan lihat saja. Sama seperti Master Data/Pembelian,
    // grup "tulis" (punya rute literal /create) wajib didaftar sebelum
    // grup "baca" (punya rute /{id} buat show).
    // -----------------------------------------------------------------
    Route::prefix('produksi')->name('produksi.')->group(function () {
        Route::middleware('role:admin,produksi')->group(function () {
            Route::resource('bom', BomController::class)->except(['index', 'show']);
        });

        Route::middleware('role:admin,produksi,pimpinan')->group(function () {
            Route::resource('bom', BomController::class)->only(['index', 'show']);
        });

        // Lima menu tahapan memakai SATU controller yang sama, dibedakan oleh
        // parameter {tahapan} yang diikat ke kolom kode_tahapan (lihat Route::bind
        // di bawah berkas ini). Menambah tahapan cukup lewat master Tahapan
        // Produksi — tidak perlu menyentuh kode maupun route.
        Route::middleware('role:admin,produksi')->group(function () {
            Route::prefix('perintah/{tahapan}')->name('perintah.')->group(function () {
                Route::post('{perintah}/mulai', [ProduksiController::class, 'mulai'])->name('mulai');
                Route::post('{perintah}/realisasi', [ProduksiController::class, 'realisasi'])->name('realisasi');
                Route::post('{perintah}/selesaikan', [ProduksiController::class, 'selesaikan'])->name('selesaikan');
                Route::post('{perintah}/batal', [ProduksiController::class, 'batal'])->name('batal');
            });

            Route::resource('perintah/{tahapan}', ProduksiController::class)
                ->parameters(['{tahapan}' => 'perintah'])
                ->names('perintah')
                ->except(['index', 'show']);
        });

        Route::middleware('role:admin,produksi,gudang,pimpinan')->group(function () {
            Route::resource('perintah/{tahapan}', ProduksiController::class)
                ->parameters(['{tahapan}' => 'perintah'])
                ->names('perintah')
                ->only(['index', 'show']);
        });
    });

    // -----------------------------------------------------------------
    // PERSEDIAAN
    //
    // Hak akses: admin & gudang penuh (termasuk mencatat opname), produksi
    // & pimpinan lihat saja.
    // -----------------------------------------------------------------
    Route::prefix('persediaan')->name('persediaan.')->group(function () {
        Route::middleware('role:admin,gudang')->group(function () {
            // Hanya create/store: catatan opname tidak boleh diubah atau
            // dihapus. Salah hitung diperbaiki dengan mencatat opname baru.
            Route::resource('opname', OpnameController::class)->only(['create', 'store']);
        });

        Route::middleware('role:admin,gudang,produksi,pimpinan')->group(function () {
            Route::get('stok', [StokController::class, 'index'])->name('stok');
            Route::get('mutasi', [MutasiStokController::class, 'index'])->name('mutasi');
            Route::resource('opname', OpnameController::class)->only(['index']);
        });
    });

    // -----------------------------------------------------------------
    // PENJUALAN
    //
    // Hak akses: admin penuh, pimpinan lihat saja, produksi & gudang tidak
    // boleh akses sama sekali.
    // -----------------------------------------------------------------
    Route::prefix('penjualan')->name('penjualan.')->group(function () {
        Route::middleware('role:admin')->group(function () {
            Route::resource('faktur', PenjualanController::class)->except(['index', 'show']);

            // Import Excel: jalan masuk data penjualan masa lalu untuk bahan ARIMA.
            Route::get('import', [ImportPenjualanController::class, 'form'])->name('import.form');
            Route::get('import/template', [ImportPenjualanController::class, 'template'])->name('import.template');
            Route::post('import', [ImportPenjualanController::class, 'store'])->name('import.store');
        });

        Route::middleware('role:admin,pimpinan')->group(function () {
            Route::resource('faktur', PenjualanController::class)->only(['index', 'show']);
        });
    });

    // -----------------------------------------------------------------
    // LAPORAN OPERASIONAL
    // -----------------------------------------------------------------
    Route::prefix('laporan')->name('laporan.')->group(function () {
        // Keempatnya mewarisi LaporanController: satu halaman yang sama dapat
        // ditampilkan di layar, diunduh PDF (?unduh=pdf), atau Excel (?unduh=excel).
        Route::get('pembelian', [LaporanPembelianController::class, 'index'])->name('pembelian');
        Route::get('produksi', [LaporanProduksiController::class, 'index'])->name('produksi');
        Route::get('persediaan', [LaporanPersediaanController::class, 'index'])->name('persediaan');
        Route::get('penjualan', [LaporanPenjualanController::class, 'index'])->name('penjualan');
    });

});
