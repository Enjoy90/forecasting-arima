# Struktur Folder Project

**Sistem Peramalan Penjualan Barang (Box-Jenkins / ARIMA) - CV. Pande Sejahtera**
Lokasi: `C:\laragon\www\peramalan-arima`

Folder disusun **mengikuti menu aplikasi**, bukan satu tumpukan besar di `Controllers/`, supaya mudah dicari saat bimbingan dan saat menulis bab implementasi.

---

## 1. Peta Folder

```
peramalan-arima/
|
|-- app/
|   |-- Http/
|   |   |-- Controllers/
|   |   |   |-- DashboardController.php
|   |   |   |-- Master/          <- Kategori, Barang, Supplier, Pelanggan, Pengguna
|   |   |   |-- Pembelian/       <- PembelianController, PenerimaanController,
|   |   |   |                      ImportPembelianController
|   |   |   |-- Produksi/        <- BomController,
|   |   |   |                      ProduksiController (melayani 5 tahapan sekaligus)
|   |   |   |-- Persediaan/      <- StokController, MutasiStokController, OpnameController
|   |   |   |-- Penjualan/       <- PenjualanController, ImportPenjualanController
|   |   |   |-- Peramalan/       <- DataHistorisController, ForecastingController,
|   |   |   |                      WaktuTungguController, TargetProduksiController
|   |   |   |-- Simulasi/        <- SimulasiController, PerbandinganController
|   |   |   |                      (pengujian rencana stok dengan data masa lalu)
|   |   |   |-- Laporan/         <- LaporanPembelianController, LaporanProduksiController,
|   |   |                          LaporanPersediaanController, LaporanPenjualanController
|   |   |-- Requests/            <- validasi form, dipisah per modul
|   |   |   |-- Master/  Pembelian/  Produksi/  Penjualan/
|   |   |-- Middleware/          <- RoleMiddleware.php (admin | produksi | gudang | pimpinan)
|   |
|   |-- Models/                  <- 25 model sesuai ERD
|   |
|   |-- Services/
|   |   |-- Arima/               <-- INTI SKRIPSI
|   |   |   |-- TimeSeriesBuilder.php     agregasi penjualan -> deret Zt
|   |   |   |-- StationarityTest.php      uji ADF + differencing
|   |   |   |-- AutoCorrelation.php       ACF & PACF (Durbin-Levinson)
|   |   |   |-- ArimaEstimator.php        estimasi koefisien, AIC, BIC, uji t
|   |   |   |-- DiagnosticChecker.php     Ljung-Box + normalitas residual
|   |   |   |-- Forecaster.php            forecast h-step + interval kepercayaan
|   |   |   |-- AccuracyMetric.php        MAPE, RMSE, MAE
|   |   |   |-- BoxJenkinsPipeline.php    orkestrator tahap 1-4
|   |   |-- Produksi/
|   |   |   |-- BomExploder.php           ledak BOM -> kebutuhan bahan
|   |   |   |-- LeadTimeCalculator.php    L_beli + L_produksi + tanggal mulai
|   |   |   |-- ProduksiProcessor.php     eksekusi perintah produksi + mutasi stok
|   |   |-- Stok/
|   |   |   |-- StockMutator.php          pencatatan mutasi stok terpusat
|   |   |   |-- TargetProduksiPlanner.php Safety Stock, ROP, target produksi
|   |   |-- Simulasi/            <-- TOLAK UKUR KEBERHASILAN (revisi sidang)
|   |       |-- SimulationRunner.php      orkestrator backtesting
|   |       |-- KebijakanPerusahaan.php   skenario pembanding
|   |       |-- KebijakanSistem.php       skenario rekomendasi sistem
|   |       |-- SimulationMetric.php      stockout, overstock, service level, biaya
|   |
|   |-- Support/Math/            <- helper matematika murni
|   |   |-- Matrix.php                    operasi matriks (OLS)
|   |   |-- Distribution.php              tabel z, t, chi-square
|   |
|   |-- Imports/                 <- PenjualanImport.php, PembelianImport.php, BarangImport.php
|   |-- Exports/                 <- export Excel tiap laporan
|   |-- Observers/               <- update barang.stok_tersedia otomatis dari mutasi_stok
|   |-- Policies/                <- otorisasi per model
|
|-- database/
|   |-- migrations/              <- 29 migration (27 tabel domain + bawaan Laravel)
|   |-- seeders/                 <- UserSeeder, KategoriSeeder, BarangSeeder,
|   |                              TahapanProduksiSeeder, BomSeeder,
|   |                              PenjualanHistorisSeeder (36 bulan untuk uji ARIMA)
|   |-- factories/
|
|-- resources/
|   |-- views/
|   |   |-- layouts/             <- app.blade.php, sidebar, navbar (dari Breeze)
|   |   |-- components/          <- komponen Blade reusable
|   |   |-- dashboard/
|   |   |-- master/{kategori,barang,supplier,pelanggan,pengguna}/
|   |   |-- pembelian/
|   |   |-- produksi/
|   |   |   |-- bom/             BOM / komposisi
|   |   |   |-- perintah/        satu set view untuk 5 tahapan
|   |   |-- persediaan/
|   |   |   |-- stok/            Stok Saat Ini
|   |   |   |-- mutasi/          Mutasi Stok
|   |   |-- penjualan/
|   |   |-- peramalan/
|   |   |   |-- historis/        tabel & grafik time series Zt
|   |   |   |-- forecasting/     4 tahap Box-Jenkins
|   |   |   |-- waktu-tunggu/    rincian lead time beli + produksi
|   |   |   |-- target/          target produksi + kebutuhan bahan
|   |   |-- simulasi/            perbandingan skenario + rincian bulanan
|   |   |-- laporan/
|   |       |-- pdf/             template khusus cetak (dompdf)
|   |-- js/charts/               <- konfigurasi Chart.js per jenis grafik
|   |-- css/
|
|-- public/assets/
|   |-- img/
|   |-- template/                <- template Excel untuk import penjualan & pembelian
|
|-- storage/app/
|   |-- imports/                 <- file Excel yang di-upload
|   |-- exports/                 <- hasil export sementara
|
|-- routes/web.php               <- dikelompokkan per modul, prefix + middleware role
|
|-- tests/
|   |-- Unit/Arima/              <- uji ACF, PACF, ADF, MAPE dibanding hitungan manual
|   |-- Unit/Produksi/           <- uji BOM explosion & perhitungan waktu tunggu
|   |-- Unit/Simulasi/           <- uji metrik stockout, overstock, service level
|   |-- Feature/                 <- uji alur CRUD, produksi, dan proses peramalan
|
|-- docs/
    |-- 01-alur-kerja-sistem.md
    |-- 02-erd.md
    |-- 03-struktur-folder.md
```

---

## 2. Alasan Pembagian (untuk bab implementasi)

| Keputusan | Alasan |
|---|---|
| Controller dikelompokkan mengikuti menu | Struktur folder langsung mencerminkan struktur menu di bab implementasi, jadi tidak perlu peta terpisah |
| **Satu `ProduksiController` untuk 5 tahapan** | Kepala, Handle, Coating, Perakitan, dan Pengemasan punya alur identik (pilih BOM, catat bahan, catat hasil) dan hanya berbeda `tahapan_id`. Membuat 5 controller terpisah berarti menyalin kode yang sama lima kali |
| Logika ARIMA di `Services/Arima/`, bukan di Controller | Controller hanya mengatur request-response. Seluruh perhitungan statistik terkumpul di satu folder sehingga mudah ditunjukkan ke pembimbing dan mudah diuji |
| `BomExploder` terpisah dari `TargetProduksiPlanner` | Ledak BOM adalah operasi rekursif yang berdiri sendiri dan dipakai juga di luar konteks peramalan (mis. simulasi produksi manual) |
| `Support/Math/` terpisah dari `Services/Arima/` | Operasi matriks dan tabel distribusi bersifat umum, bukan bagian dari Box-Jenkins. Memisahkannya membuat `ArimaEstimator` lebih mudah dibaca |
| `tests/Unit/Arima/` | Hasil hitung sistem dibandingkan dengan perhitungan manual atau software statistik. Ini jadi bukti validasi di bab pengujian |
| View peramalan dipecah 3 submenu | Persis mengikuti menu: Data Historis, Proses Forecasting, Target Produksi |

---

## 3. Konfigurasi VS Code

Sudah disiapkan di `.vscode/`:

| File | Isi |
|---|---|
| `settings.json` | `vendor/` & `node_modules/` disembunyikan dari explorer dan pencarian, file nesting aktif, Blade dikenali, tab size PHP 4 spasi |
| `extensions.json` | Rekomendasi ekstensi: Intelephense, Laravel Blade, Laravel Artisan, PHP Namespace Resolver, dan **Markdown Preview Mermaid** untuk melihat diagram ERD langsung di VS Code |

**Melihat diagram ERD di VS Code:** buka `docs/02-erd.md`, tekan `Ctrl+Shift+V`. Diagram Mermaid akan ter-render (perlu ekstensi `bierner.markdown-mermaid`).

---

## 4. Konvensi Penamaan

| Objek | Konvensi | Contoh |
|---|---|---|
| Tabel database | `snake_case` | `detail_produksi_bahan`, `hasil_peramalan` |
| Model | `PascalCase` tunggal | `DetailProduksiBahan`, `HasilPeramalan` |
| Controller | `PascalCase` + `Controller` | `TargetProduksiController` |
| Route name | `modul.submodul.aksi` | `peramalan.forecasting.proses` |
| View | `kebab-case` folder | `peramalan/forecasting/identifikasi.blade.php` |
| Service | `PascalCase` kata benda/pelaku | `StationarityTest`, `BomExploder` |

---

## 5. Database

Seluruh lingkungan memakai **MySQL/MariaDB** (Laragon), tidak ada lagi SQLite.

| Lingkungan | Database | Diatur di |
|---|---|---|
| Aplikasi (development) | `db_peramalan_pande` | `.env` |
| Pengujian (`php artisan test`) | `db_peramalan_pande_test` | `phpunit.xml` |

Database pengujian sengaja **dipisah** karena test memakai `RefreshDatabase`:
seluruh tabel dihapus dan dibuat ulang setiap kali test dijalankan. Bila
diarahkan ke `db_peramalan_pande`, data seeder penjualan historis 36 bulan yang
dipakai untuk uji ARIMA akan ikut terhapus.

**Menyiapkan di komputer baru** (sekali saja, sebelum `php artisan test`):

```sql
CREATE DATABASE db_peramalan_pande_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Migration tidak perlu dijalankan manual pada database test — `RefreshDatabase`
yang mengurusnya.

---

## 6. Status Pengerjaan

| Tahap | Status |
|---|---|
| Laravel 12.69 + Breeze (Blade/Tailwind) terpasang | Selesai |
| `maatwebsite/excel` + `barryvdh/laravel-dompdf` terpasang | Selesai |
| Database `db_peramalan_pande` dibuat | Selesai |
| 29 migration dijalankan & tervalidasi | Selesai |
| Struktur folder per modul | Selesai |
| Pemecahan route & sidebar per modul (kerja 2 orang) | Selesai |
| `RoleMiddleware` + role pada tabel users | Selesai |
| 27 model Eloquent + relasi | Selesai |
| Seeder master, BOM, & penjualan historis 36 bulan | Selesai |
| Uji asap layout + sidebar (27 test lolos) | Selesai |
| CRUD master data (kategori, supplier, pelanggan, tahapan, barang, pengguna) | Selesai |
| Fondasi stok (`StockMutator` + observer + halaman persediaan) | Selesai |
| Transaksi pembelian + penerimaan barang | Selesai |
| Transaksi penjualan | Selesai |
| Import Excel penjualan & pembelian | Selesai |
| Modul produksi + BOM | Selesai |
| Perhitungan waktu tunggu operasional | Belum |
| Service ARIMA (Box-Jenkins) | Belum |
| Target produksi + BOM explosion | Belum |
| **Modul simulasi & perbandingan skenario** | **Belum — prioritas utama** |
| Laporan & export PDF/Excel | Selesai |
| Dashboard, manajemen pengguna, tombol rekomendasi | Selesai |
| Deploy ke hosting | Belum |
