# Sistem Peramalan Penjualan & Perencanaan Stok (ARIMA)

Rancang Bangun Sistem Peramalan Penjualan Barang Berbasis Web Menggunakan Metode Box-Jenkins (ARIMA) untuk Perencanaan Stok. Skripsi untuk CV Pande Sejahtera, produsen sekop.

Sistem ini punya dua bagian utama:

- **Modul Operasional**: master data, pembelian, produksi, persediaan, penjualan, import Excel, dan laporan.
- **Modul Analisis & Peramalan**: peramalan penjualan dengan metode Box-Jenkins (ARIMA), perhitungan waktu tunggu & target produksi, serta simulasi backtest yang membandingkan kebijakan stok sistem dengan kebijakan perusahaan saat ini.

Tolak ukur keberhasilan sistem bukan akurasi ramalan (MAPE), melainkan apakah rencana stok yang dihasilkan sistem, saat diuji kembali (backtest) terhadap 12 bulan data historis, menghasilkan lebih sedikit overstock dan stockout dibanding kebijakan lama perusahaan.

## Teknologi

- Laravel 12 (PHP 8.2)
- MySQL
- Tailwind CSS + Alpine.js
- Chart.js (grafik deret waktu, correlogram ACF/PACF, hasil forecast)
- dompdf (laporan PDF), Laravel Excel (import/export)

Seluruh perhitungan statistik (regresi, uji ADF, ACF/PACF, dsb) ditulis manual dalam PHP tanpa library statistik eksternal. Lihat `app/Support/Math/` dan `app/Services/Arima/`.

## Instalasi

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Buat database MySQL kosong, lalu isi `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` di `.env` sesuai environment lokal.

```bash
php artisan migrate --seed
npm run build

php artisan serve
```

`migrate --seed` akan mengisi database dengan data dummy: master data, BOM, 36 bulan data penjualan historis, dan 4 akun pengguna (role `admin`, `produksi`, `gudang`, `pimpinan`), semua dengan password `password`.

## Dokumentasi

Dokumen desain sistem ada di folder [`docs/`](docs/):

- [`01-alur-kerja-sistem.md`](docs/01-alur-kerja-sistem.md): alur kerja & rumus perhitungan
- [`02-erd.md`](docs/02-erd.md): ERD
- [`03-struktur-folder.md`](docs/03-struktur-folder.md): struktur folder & konvensi
- [`04-pembagian-modul.md`](docs/04-pembagian-modul.md): pembagian modul
- [`05-roadmap-modul-a.md`](docs/05-roadmap-modul-a.md) & [`06-todolist-modul-a.md`](docs/06-todolist-modul-a.md): progres Modul Operasional
- [`06-todolist-modul-b.md`](docs/06-todolist-modul-b.md): progres Modul Analisis & Peramalan

## Menjalankan Test

```bash
php artisan test
```
