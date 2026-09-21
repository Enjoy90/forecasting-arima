# Pembagian Modul untuk 2 Orang

**Rancang Bangun Sistem Peramalan Penjualan Barang Berbasis Web Menggunakan Metode Box-Jenkins (ARIMA) untuk Perencanaan Stok**
CV. Pande Sejahtera

---

## 1. Prinsip Pembagian

Satu aturan yang menentukan lancar tidaknya kerja berdua:

> **Dua orang tidak boleh pernah mengubah file yang sama.**

Kalau aturan ini dipegang, tidak akan pernah ada konflik saat menggabungkan pekerjaan. Semua keputusan struktur di bawah ini dibuat untuk menegakkan aturan itu — termasuk memecah `routes/web.php` dan menu sidebar menjadi file terpisah, yang normalnya tidak perlu dilakukan pada proyek satu orang.

---

## 2. Fase 0 — Fondasi Bersama (dikerjakan lebih dulu, lalu DIBEKUKAN)

Bagian ini dipakai kedua orang, jadi harus selesai **sebelum** pembagian dimulai, dan setelah itu tidak boleh diubah sepihak.

| Item | Isi | Status |
|---|---|---|
| Migration | 29 file, 27 tabel domain | ✅ Selesai |
| Model Eloquent | 27 model + seluruh relasi | Fase 0 |
| Seeder | Master data, BOM, tahapan produksi, penjualan historis 36 bulan | Fase 0 |
| Layout & auth | `layouts/app`, sidebar, `RoleMiddleware` | Fase 0 |
| Pemecahan route & sidebar | `routes/operasional.php`, `routes/analisis.php` | Fase 0 |

**Setelah fase 0 selesai, model dan migration dibekukan.** Kalau salah satu butuh kolom baru: buat *migration baru* dengan timestamp sendiri, jangan mengubah migration lama, dan beri tahu yang lain.

---

## 3. Pembagian Modul

### Modul A — Operasional Pabrik

Semua yang berhubungan dengan menjalankan pabrik sehari-hari.

| Cakupan | File yang dimiliki |
|---|---|
| Master Data | `app/Http/Controllers/Master/*` |
| Pembelian & penerimaan | `app/Http/Controllers/Pembelian/*` |
| Produksi & BOM | `app/Http/Controllers/Produksi/*` |
| Persediaan & mutasi stok | `app/Http/Controllers/Persediaan/*` |
| Penjualan | `app/Http/Controllers/Penjualan/*` |
| Import Excel | `app/Imports/*` |
| Service operasional | `app/Services/Produksi/ProduksiProcessor.php`, `app/Services/Stok/StockMutator.php` |
| Observer stok | `app/Observers/*` |
| Laporan operasional | `app/Http/Controllers/Laporan/*` (pembelian, produksi, persediaan, penjualan) |
| View | `resources/views/master/`, `pembelian/`, `produksi/`, `persediaan/`, `penjualan/`, `laporan/` |
| Route | `routes/operasional.php` |
| Menu | `resources/views/partials/sidebar-operasional.blade.php` |

**Bobot:** lebih banyak jumlah halaman, tapi polanya berulang (CRUD). Yang paling menantang: observer mutasi stok dan alur 5 tahapan produksi.

### Modul B — Analisis & Peramalan

Inti skripsi. Semua yang ditanya penguji ada di sini.

| Cakupan | File yang dimiliki |
|---|---|
| Perhitungan ARIMA | `app/Services/Arima/*` (8 class) |
| Waktu tunggu operasional | `app/Services/Produksi/LeadTimeCalculator.php` |
| Ledak BOM | `app/Services/Produksi/BomExploder.php` |
| Rencana stok | `app/Services/Stok/TargetProduksiPlanner.php` |
| **Simulasi pengujian** | `app/Services/Simulasi/*` (4 class) |
| Helper matematika | `app/Support/Math/*` |
| Controller | `app/Http/Controllers/Peramalan/*`, `app/Http/Controllers/Simulasi/*` |
| Laporan hasil simulasi | `app/Http/Controllers/Laporan/LaporanSimulasiController.php` |
| View | `resources/views/peramalan/`, `resources/views/simulasi/` |
| Route | `routes/analisis.php` |
| Menu | `resources/views/partials/sidebar-analisis.blade.php` |
| Pengujian unit | `tests/Unit/Arima/`, `tests/Unit/Simulasi/` |

**Bobot:** jumlah halaman lebih sedikit, tapi jauh lebih berat secara logika. Ini bagian yang menentukan nilai sidang.

### Saran Penempatan Orang

**Penulis skripsi sebaiknya mengambil Modul B.** Alasannya bukan soal berat-ringan, tapi karena saat sidang yang ditanya penguji adalah ARIMA, waktu tunggu, dan hasil simulasi — dan penulis harus bisa menjelaskan tiap baris perhitungannya sendiri. Modul A lebih aman didelegasikan karena polanya standar dan mudah dijelaskan.

---

## 4. Aturan File yang Dipakai Bersama

| File | Aturan |
|---|---|
| `app/Models/*` | **Beku.** Perlu tambahan relasi? Kabari dulu, salah satu saja yang mengedit |
| `database/migrations/*` | **Beku.** Perubahan hanya lewat migration baru |
| `database/seeders/*` | Modul A pemilik seeder operasional; Modul B boleh menambah seeder sendiri dengan nama file berbeda |
| `routes/web.php` | **Jangan disentuh.** Isinya cuma memanggil dua file route lain |
| `resources/views/layouts/app.blade.php` | Modul A pemilik. Modul B minta kalau perlu ubah |
| `resources/views/partials/sidebar.blade.php` | **Jangan disentuh.** Hanya memanggil dua partial terpisah |
| `.env` | Tidak ikut Git. Masing-masing punya sendiri |
| `composer.json` / `package.json` | Kabari sebelum menambah package, supaya `composer.lock` tidak bentrok |

---

## 5. Kontrak Antar Modul

Ini yang membuat kedua orang bisa jalan **bersamaan tanpa saling menunggu**.

### 5.1 Yang boleh diasumsikan Modul B dari Modul A

Modul B tidak perlu menunggu halaman Modul A jadi, karena **seeder fase 0 sudah menyediakan seluruh data yang dibutuhkan**:

| Kebutuhan B | Sumber | Sudah tersedia dari seeder? |
|---|---|---|
| Deret penjualan bulanan | `detail_penjualan` + `penjualan` | ✅ 36 bulan |
| Barang jadi yang diramalkan | `barang` dengan `is_diramalkan = true` | ✅ |
| Komposisi bahan | `bom` + `detail_bom` | ✅ |
| Waktu proses tiap tahapan | `tahapan_produksi.waktu_proses_hari` | ✅ |
| Lead time bahan | `barang.lead_time_hari` | ✅ |
| Stok berjalan | `barang.stok_tersedia` | ✅ |

### 5.2 Batas tulis-baca

| Modul | Boleh MENULIS ke | Boleh MEMBACA |
|---|---|---|
| A | Seluruh tabel master, transaksi, produksi, persediaan | Semua |
| B | `data_time_series`, `peramalan`, `uji_stasioneritas`, `korelasi_lag`, `kandidat_model`, `parameter_model`, `hasil_peramalan`, `target_produksi`, `kebutuhan_bahan`, `simulasi`, `simulasi_detail` | Semua |

**Modul B tidak pernah menulis ke tabel operasional.** Ini batas yang membuat kedua pekerjaan tidak bisa saling merusak data.

### 5.3 Satu titik temu

Ada satu tempat kedua modul bertemu: **rekomendasi pembelian dari `kebutuhan_bahan` menjadi transaksi pembelian**. Kesepakatannya:

> Modul B hanya **menulis rekomendasi** ke tabel `kebutuhan_bahan`.
> Modul A menyediakan tombol "Buat Order dari Rekomendasi" yang membaca tabel itu.

Jadi tetap tidak ada file yang dikerjakan berdua.

---

## 6. Alur Git

```bash
# sekali di awal
git init
git add .
git commit -m "Fondasi: migration, model, seeder, layout"
git branch modul-a-operasional
git branch modul-b-analisis
```

Kerja harian:

```bash
git checkout modul-a-operasional     # atau modul-b-analisis
# ... kerjakan ...
git add .
git commit -m "Master data barang: CRUD + validasi"
```

Menggabungkan (seminggu sekali saja, jangan tiap hari):

```bash
git checkout main
git merge modul-a-operasional
git merge modul-b-analisis
```

Kalau aturan kepemilikan file di atas dipatuhi, `merge` tidak akan pernah minta penyelesaian konflik.

---

## 7. Urutan Pengerjaan yang Disarankan

| Minggu | Modul A | Modul B |
|---|---|---|
| 1 | CRUD master data (kategori, barang, supplier, pelanggan, tahapan) | `Support/Math` + `AutoCorrelation` (ACF/PACF) + unit test |
| 2 | Pembelian + penerimaan + mutasi stok masuk | `StationarityTest` (ADF) + `ArimaEstimator` |
| 3 | BOM + perintah produksi 5 tahapan | `DiagnosticChecker` + `Forecaster` + `BoxJenkinsPipeline` |
| 4 | Penjualan + import Excel + kartu stok | Halaman peramalan + grafik Chart.js |
| 5 | Laporan operasional + export PDF | `LeadTimeCalculator` + `BomExploder` + `TargetProduksiPlanner` |
| 6 | Dashboard + perapian | **`SimulationRunner` + halaman perbandingan skenario** |
| 7 | Uji coba bersama, perbaikan | Laporan hasil simulasi |
| 8 | Deploy ke hosting | Penulisan bab IV |

**Catatan:** Modul B minggu 6 adalah yang paling menentukan kelulusan (tolak ukur utama revisi sidang). Jangan sampai tergeser ke minggu terakhir.

---

## 8. Hal yang Sering Bikin Kacau di Proyek Berdua

| Masalah | Pencegahan di proyek ini |
|---|---|
| Dua orang edit `routes/web.php` | Sudah dipecah jadi 2 file route |
| Dua orang tambah menu di sidebar | Sudah dipecah jadi 2 partial |
| Model diubah sepihak, yang lain error | Model dibekukan setelah fase 0 |
| Database beda isi, hasil beda | Semua pakai seeder yang sama: `php artisan migrate:fresh --seed` |
| `.env` ikut ter-commit | Sudah masuk `.gitignore` bawaan Laravel |
| `vendor/` ikut ter-commit | Sudah masuk `.gitignore` bawaan Laravel |
| Merge tiap hari, tiap kali konflik | Sepakati: merge seminggu sekali |
