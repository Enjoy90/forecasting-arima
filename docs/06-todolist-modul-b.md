# To-Do List Modul B: Analisis & Peramalan

Daftar kerja untuk mengisi `app/Services/Arima/`, `app/Services/Produksi/{LeadTimeCalculator,BomExploder}.php`, `app/Services/Stok/TargetProduksiPlanner.php`, dan `app/Services/Simulasi/*` (lihat `docs/01-alur-kerja-sistem.md`, `docs/02-erd.md`, `docs/04-pembagian-modul.md`).

Urutan mengikuti ketergantungan: fondasi matematika lebih dulu, lalu 4 tahap Box-Jenkins, baru waktu tunggu & rencana stok, dan simulasi backtest paling akhir (karena butuh semua yang sebelumnya).

Status: `[ ]` belum, `[~]` sedang dikerjakan, `[x]` selesai. Dokumen ini diperbarui setiap ada progres.

---

## Fase 1: Fondasi Matematika & Data

- [x] **1.1** `app/Support/Math/Matrix.php` + `Distribution.php`: operasi matriks (untuk OLS/CLS) dan tabel z / t / chi-square
- [x] **1.2** `app/Services/Arima/TimeSeriesBuilder.php`: agregasi `detail_penjualan` bulanan per barang jadi -> deret Zt, simpan ke `data_time_series` + unit test dasar

## Fase 2: Identifikasi & Estimasi Model (Tahap 1-2 Box-Jenkins)

- [x] **2.1** `app/Services/Arima/AutoCorrelation.php`: ACF & PACF (Durbin-Levinson) + unit test
- [x] **2.2** `app/Services/Arima/StationarityTest.php`: uji ADF + differencing bertahap + unit test
- [x] **2.3** `app/Services/Arima/ArimaEstimator.php`: grid search p/q, estimasi CLS, AIC/BIC, uji-t signifikansi + unit test

## Fase 3: Diagnostic & Forecasting (Tahap 3-4 Box-Jenkins)

- [x] **3.1** `app/Services/Arima/DiagnosticChecker.php`: uji Ljung-Box + normalitas residual + unit test
- [x] **3.2** `app/Services/Arima/Forecaster.php` + `AccuracyMetric.php`: forecast h-step, interval kepercayaan, MAPE/RMSE/MAE + unit test
- [x] **3.3** `app/Services/Arima/BoxJenkinsPipeline.php`: orkestrator tahap 1-4 + integration test (bandingkan dengan hitungan manual/software statistik)

## Fase 4: UI Peramalan

- [x] **4.1** `DataHistorisController` + `ForecastingController` (`app/Http/Controllers/Peramalan/`) + view `resources/views/peramalan/historis/` & `forecasting/`: tampilkan seluruh bukti perhitungan (ADF, ACF/PACF, kandidat model, parameter, hasil ramalan)

## Fase 5: Waktu Tunggu & Rencana Stok

- [x] **5.1** `app/Services/Produksi/LeadTimeCalculator.php` + `BomExploder.php` + unit test
- [x] **5.2** `app/Services/Stok/TargetProduksiPlanner.php` (Safety Stock, ROP, target produksi) + unit test
- [x] **5.3** Controller + view: Waktu Tunggu & Target Produksi (`resources/views/peramalan/waktu-tunggu/`, `target/`)

## Fase 6: Simulasi Backtest (tolak ukur utama keberhasilan sistem)

- [x] **6.1** `app/Services/Simulasi/SimulationMetric.php` + `KebijakanPerusahaan.php` + `KebijakanSistem.php` + unit test
- [x] **6.2** `app/Services/Simulasi/SimulationRunner.php`: orkestrator backtest 24/12 bulan, dua skenario berdampingan + integration test
- [x] **6.3** Controller + view: Jalankan Simulasi, Perbandingan Skenario, Rincian Bulanan (`resources/views/simulasi/`)

## Fase 7: Penutup

- [x] **7.1** `LaporanSimulasiController` + `SimulasiController::cetak()` (PDF per simulasi) + export PDF hasil simulasi
- [x] **7.2** Wiring akhir: `routes/analisis.php` + `resources/views/partials/sidebar-analisis.blade.php` untuk seluruh menu Modul B

---

## Catatan Progres

### 2026-09-19: Fase 1 selesai

- **1.1 Support/Math**: `Matrix.php` (transpose, kali, kaliVektor, invers via Gauss-Jordan pivot parsial, `ols()`: regresi OLS lengkap dengan standard error per koefisien) dan `Distribution.php` (`zScore` via algoritma Acklam, `tTabel`: tabel-t baku alpha=0.05 df 1-30/40/60/120/~ + interpolasi, `chiSquareTabel` via pendekatan Wilson-Hilferty, `nilaiKritisAdf`: tabel Dickey-Fuller konstanta+tren gaya Gujarati, diinterpolasi menurut n). Semua murni PHP, tanpa library statistik eksternal, sesuai batasan di docs/01 §11.
- **1.2 TimeSeriesBuilder**: agregasi `detail_penjualan` → `data_time_series`. Keputusan penting: bulan tanpa transaksi tetap disimpan dengan `nilai_zt = 0` (bukan dilewati), supaya `urutan_t` tidak bolong, ini krusial karena ACF/PACF dan differencing di tahap berikutnya butuh deret yang benar-benar berurutan. `updateOrCreate` dipakai supaya `bangun()` bisa dipanggil ulang tanpa duplikasi saat ada transaksi susulan.
- Unit test: `tests/Unit/Support/Math/{MatrixTest,DistributionTest}.php` dan `tests/Unit/Arima/TimeSeriesBuilderTest.php`: 21 test, semua lolos (`php artisan test --filter "MatrixTest|DistributionTest|TimeSeriesBuilderTest"`).
- Environment lokal disiapkan untuk pertama kali: `composer install`, `.env` dari `.env.example`, `php artisan key:generate`, database `db_peramalan_pande` + `db_peramalan_pande_test` dibuat di MySQL XAMPP.
- Catatan: full `php artisan test` menunjukkan 28 test **Feature** gagal (login, dashboard, CRUD master, dll), semuanya karena `public/build/manifest.json` belum ada (belum pernah `npm run build`), bukan disebabkan pekerjaan Fase 1. Belum diperbaiki karena di luar cakupan Modul B; perlu `npm install && npm run build` bila mau menjalankan seluruh test suite Feature.

**Lanjut ke Fase 2** (AutoCorrelation, StationarityTest, ArimaEstimator).

### 2026-09-20: Sinkronisasi update Modul A dari repo GitHub

Repo `https://github.com/LanzAb/forecasting-arima` (kerjaan rekan Modul A) di-clone ke folder terpisah dan dibandingkan manual dengan project lokal (project ini sendiri belum jadi git repository, jadi bukan `git pull` biasa). Update yang ditarik masuk, murni "Tahap 6 Modul A" (Import Excel Pembelian, Laporan, Dashboard, Manajemen Pengguna, tombol "Buat Order dari Rekomendasi"):

- Baru: `ImportPembelianController` + `PembelianImport` + `TemplatePembelianExport` + view import; `LaporanPersediaanController` + `LaporanProduksiController` + view laporan; `docs/06-todolist-modul-a.md`; test `DashboardTest`, `PenggunaTest`, `ImportPembelianTest`, `RekomendasiTest`, `Laporan/LaporanTest`.
- Ditimpa versi terbaru: `PenggunaController`, `routes/operasional.php`, `dashboard.blade.php`, `layouts/{app,navigation}.blade.php`, `partials/sidebar*.blade.php`, `pembelian/index.blade.php`, `package-lock.json`, `docs/03-struktur-folder.md`, `docs/05-roadmap-modul-a.md`.
- **Tidak disentuh** (kerjaan Modul B Fase 1 tetap): `app/Support/Math/*`, `app/Services/Arima/TimeSeriesBuilder.php`, `docs/06-todolist-modul-b.md`, `tests/Unit/Arima/TimeSeriesBuilderTest.php`, `tests/Unit/Support/*`.
- Migration masih 29 file di kedua sisi, **tidak ada perubahan skema**, model tetap beku, jadi tidak mempengaruhi rencana kerja Fase 2 dst.
- Catatan penting buat Fase 6 nanti: `docs/05-roadmap-modul-a.md` menyebut model `KebutuhanBahan` sudah punya `scopePerluBeli()` yang dipakai tombol "Buat Order dari Rekomendasi", tapi **belum pernah diuji dengan data asli Modul B** (test Modul A masih pakai baris `kebutuhan_bahan` buatan sendiri). Ini titik temu yang wajib dicek ulang begitu `TargetProduksiPlanner`/`BomExploder` (Fase 5) mulai menulis ke tabel itu.
- `npm install` + `npm run build` dijalankan ulang setelah sinkronisasi (lockfile ikut berubah).
- Verifikasi akhir: `php artisan test` penuh → **217 passed, 0 failed** (percobaan pertama sempat 26 gagal karena MySQL XAMPP mati di tengah jalan, bukan akibat sinkronisasi, setelah MySQL dinyalakan ulang seluruh test lolos dalam 16.8 detik).

### 2026-09-21: Fase 2 selesai

- **2.1 AutoCorrelation**: `acf()` (rumus persis docs/01 §5.1) dan `pacf()` (rekursi Durbin-Levinson dari nilai ACF). `batasSignifikansi()` pakai `Distribution::zScore(0.975)/sqrt(n)` (bukan hardcode 1.96, konsisten dengan Fase 1).
- **2.2 StationarityTest**: `differencing()` (W_t=Z_t-Z_(t-1) berulang) dan `ujiAdf()`: regresi ADF `dZt = alpha + beta*t + gamma*Z(t-1) + delta*dZ(t-1) + et` (1 suku lag tambahan, default) lewat `Matrix::ols()`, lalu `tentukanOrdo()` mengulang differencing sampai stasioner atau `maxD` tercapai (default 2), sesuai alur A2-A6 di docs/01.
- **2.3 ArimaEstimator**: karena CLS penuh untuk model ber-MA itu nonlinear (residual saling bergantung), dipakai pendekatan **Hannan-Rissanen dua-langkah**: (1) fit AR orde tinggi buat proksi residual white-noise, (2) regresi akhir Z_t atas lag Z (AR) + lag residual proksi (MA) lewat OLS biasa. Ini pendekatan standar yang implementasinya murni OLS berulang (tanpa solver nonlinear), didokumentasikan di komentar kelasnya. Konvensi tanda MA disesuaikan ke `theta(B)=1-theta_1 B-...` sesuai docs (koefisien regresi mentah dinegasikan). `gridSearch()` p=0..3,q=0..3, pilih AIC terkecil di antara yang semua parameternya signifikan (fallback ke AIC terkecil keseluruhan bila tidak ada yang lolos, teramati sering terjadi karena konstanta pada deret yang sudah di-differencing biasanya tidak signifikan, wajar karena mean deret differenced ~0).
- Ditambah ke `Distribution.php`: `pValueT()` (p-value dari statistik-t, dicari lewat bisection ke `tTabel()` sendiri biar konsisten) dan `pValueAdf()` (interpolasi/ekstrapolasi linear di antara 3 titik `nilaiKritisAdf()`).
- Semua hasil dites dulu manual lewat `php artisan tinker` memakai data riil BJ-01 (36 bulan) sebelum ditulis jadi unit test, hasilnya masuk akal: ACF meluruh (data trending), ADF baru stasioner di d=2, grid search milih ARMA(2,0) dengan AIC terendah.
- **Bug ketemu & diperbaiki saat menulis test**: hitungan manual ACF lag-2 di draft test salah (kurang satu suku penjumlahan), nilai yang benar dari rumus (dan dikonfirmasi kode sudah benar) adalah 0.1, bukan 0.2. Ini murni salah hitung manual di test, bukan bug di `AutoCorrelation.php`.
- Unit test baru: `AutoCorrelationTest`, `StationarityTestTest`, `ArimaEstimatorTest` (22 test). `StationarityTestTest` sengaja pakai data riil BJ-01 sebagai fixture tetap (bukan data sintetis kecil) karena uji unit root punya power rendah di sampel kecil, data sintetis yang dicoba malah terdeteksi stasioner secara tak terduga.
- Verifikasi akhir: `php artisan test` penuh → **239 passed, 0 failed**.
- **Catatan buat Fase 3**: `DiagnosticChecker` (Ljung-Box) nanti butuh p-value dari chi-square statistic, pola yang sama seperti `pValueT()` (bisection ke `chiSquareTabel()`) bisa dipakai lagi, belum ditambahkan sekarang supaya tidak menambah fungsi yang belum dipakai.

**Lanjut ke Fase 3** (DiagnosticChecker, Forecaster + AccuracyMetric, BoxJenkinsPipeline).

### 2026-09-21: Fase 3 selesai (Modul B logic ARIMA lengkap, siap masuk UI)

- **3.1 DiagnosticChecker**: `ljungBox()` (statistik Q dari ACF residual, uji H0=white noise) dan `normalitas()` (Jarque-Bera, pakai skewness/kurtosis). Ditambah `Distribution::pValueChiSquare()` (bisection ke `chiSquareTabel()`, pola sama seperti `pValueT()`). Test butuh fixture "white noise" yang benar, percobaan pertama pakai kombinasi sin/cos ternyata GAGAL karena sinusoid deterministik itu sendiri sangat autokorelasi (bukan white noise beneran); diganti generator LCG (Linear Congruential Generator) berseed tetap yang perilakunya lebih mirip derau asli tapi tetap deterministik/reproducible.
- **3.2 AccuracyMetric**: MAPE/RMSE/MAE + `kategoriMape()`: paling sederhana di Fase 3, langsung sesuai rumus docs.
- **3.2 Forecaster**: bagian paling berisiko salah. Alih-alih forecast di skala W (differenced) lalu "un-differencing" manual, dipakai trik **gabungkan polinomial phi(B) dan (1-B)^d jadi satu phi\*(B) berorde p+d** (konvolusi biasa), jadi seluruh forecast, fitted-value, dan residual dalam-sampel dihitung LANGSUNG di skala Z asli tanpa perlu jaga-jaga indeks W vs Z secara terpisah. Interval kepercayaan pakai psi-weight (rumus rekursi Wei) dari phi\* dan theta yang sama. Divalidasi pakai 2 kasus hitung tangan: AR(1) murni (fitted/residual/forecast/interval cocok persis manual) dan ARIMA(0,1,0) murni (differencing tanpa AR/MA) yang terbukti menghasilkan "forecast naif" (nilai terakhir diulang), ini hasil teoretis yang sudah dikenal di buku Box-Jenkins, jadi sekaligus jadi bukti independen bahwa penggabungan polinomialnya benar.
- **3.3 BoxJenkinsPipeline**: orkestrator yang manggil TimeSeriesBuilder → StationarityTest → AutoCorrelation → ArimaEstimator → DiagnosticChecker → Forecaster → AccuracyMetric, lalu simpan semuanya ke `peramalan` + 5 tabel detail dalam satu `DB::transaction`. **Keputusan cakupan penting**: kalau Ljung-Box gagal, docs/01 menggambarkan alur "kembali ke identifikasi", pipeline ini TIDAK mengulang otomatis (supaya tidak ada risiko loop tanpa kriteria berhenti yang jelas), hasilnya tetap disimpan dengan `lolos_ljung_box=false` supaya bisa ditinjau manual. Kolom `kandidat_model.lolos_ljung_box` cuma diisi utk kandidat yang terpilih (kandidat lain memang tidak pernah didiagnosa, bukan "diuji lalu gagal").
- **Bug ketemu & diperbaiki**: (1) `lolos_ljung_box` di migration `NOT NULL default(false)`, kode awal ngirim `null` utk kandidat non-terpilih → bakal error SQL kalau nggak dites duluan; (2) test `BoxJenkinsPipelineTest` awalnya lupa manggil `TimeSeriesBuilder::bangun()` sebelum pipeline jalan (pipeline cuma baca `ambilDeret()`, memang didesain 2 langkah terpisah sesuai menu "Data Historis" vs "Proses Forecasting" di docs).
- Seluruh pipeline dites dulu manual lewat `php artisan tinker` end-to-end pakai data riil BJ-01 sebelum ditulis jadi test formal: hasilnya ARIMA(3,2,0) terpilih dari 16 kandidat, MAPE 6.42% ("Sangat Baik"), forecast 6 bulan ke depan dengan interval yang melebar wajar seiring horizon.
- Unit test baru: `DiagnosticCheckerTest` (5), `AccuracyMetricTest` (6), `ForecasterTest` (5), `BoxJenkinsPipelineTest` (4), plus 3 test tambahan utk `Distribution::pValueChiSquare`: total 23 test baru.
- Verifikasi akhir: `php artisan test` penuh → **262 passed, 0 failed**.

**Logic ARIMA Modul B (Fase 1-3) sudah selesai semua dan teruji end-to-end.** Lanjut ke **Fase 4** (bikin UI: controller + view halaman Peramalan) supaya semua ini bisa dilihat/dipakai lewat browser.

### 2026-09-21: Fase 4 selesai (UI Peramalan pertama kali bisa diakses lewat browser)

Dikerjakan mengikuti kerangka yang **sudah di-scaffold sebelumnya** (route di `routes/analisis.php` sudah berupa komentar siap-aktif, folder controller & nama menu di sidebar sudah ditentukan), tidak menyimpang dari rencana yang ada.

- **Chart.js** dipasang (`npm install chart.js`), didaftarkan global di `resources/js/app.js` (`window.Chart`) lewat build `chart.js/auto`, dipakai di halaman lewat `@push('skrip')` (sudah ada mekanismenya di `layouts/app.blade.php`). Sesuai rencana awal `docs/01 §11` (Chart.js untuk time series & correlogram ACF/PACF), bukan gaya SVG manual Modul A yang khusus dibuat untuk grafik batang sederhana di dashboard.
- **`DataHistorisController`** (`index`, `agregasi`) + view `peramalan/historis/index.blade.php`: pilih barang jadi, tombol jalanin `TimeSeriesBuilder::bangun()`, tabel + line chart deret Zt.
- **`ForecastingController`** (`index`, `proses`, `show`) + view `peramalan/forecasting/{index,show}.blade.php`: form jalanin `BoxJenkinsPipeline::jalankan()`, riwayat peramalan, dan halaman hasil lengkap: ringkasan model, tabel uji ADF tiap differencing, correlogram ACF & PACF (bar chart + garis batas signifikansi), tabel kandidat model (grid search, baris terpilih ditandai), tabel parameter model, status diagnostic Ljung-Box, chart aktual vs prediksi vs forecast dengan pita interval kepercayaan.
- Route diaktifkan di `routes/analisis.php`, 2 menu sidebar (`Data Historis`, `Proses Forecasting`) diaktifkan di `sidebar-analisis.blade.php`. Menu `Waktu Tunggu` & `Target Produksi` sengaja dibiarkan `#`: itu Fase 5, belum dikerjakan.
- **Bug ketemu & diperbaiki**: halaman `show` sempat 500 karena parse error Blade, ekspresi `@json($koleksi->map(fn ($d) => [...])->values())` yang bersarang dalam array JS multi-baris membuat compiler Blade salah mencocokkan kurung. Diperbaiki dengan memindahkan seluruh transformasi data chart ke method privat di controller (`dataCorrelogram()`, `dataForecastChart()`), view tinggal `@json($variabelSederhana)`. Sekalian lebih sesuai clean code (logic tidak numpuk di view).
- Unit/Feature test baru: `DataHistorisTest` (6), `ForecastingTest` (6), mencakup guest ditolak, render halaman dengan data asli, validasi input, dan penolakan data <24 periode.
- Verifikasi akhir: `php artisan test` penuh → **274 passed, 0 failed**.

**Modul B sekarang punya UI yang bisa diakses browser**, login sebagai `pimpinan@pande.test`, menu "Peramalan (ARIMA)" di sidebar → Data Historis / Proses Forecasting. Lanjut ke **Fase 5** (Waktu Tunggu & Rencana Stok).

### 2026-09-21: Fase 5 selesai (Waktu Tunggu & Rencana Stok)

- **Temuan penting sebelum menulis kode**: Modul A ternyata sudah menanam dua rumus kunci langsung di model Eloquent sejak fondasi Fase 0: `TahapanProduksi::hariUntuk($jumlah)` (persis rumus "Hari tahapan-i" di docs/01 §4) dan `Bom::kebutuhanUntuk($jumlahOutput)` (persis rumus "Kebutuhan Bahan-i" satu level di docs/01 §6.1, sudah termasuk persen susut). Jadi kerjaan Fase 5 fokus ke orkestrasi rekursif BOM bertingkat, bukan menulis ulang rumus dasarnya.
- **`BomExploder`**: jalan rekursif dari barang jadi turun lewat `Barang::bomAktif()` + `Bom::kebutuhanUntuk()` di tiap level, akumulasi bahan baku di ujung rantai (gabung kalau bahan sama muncul di jalur berbeda) dan tahapan yang dilewati (gabung kalau tahapan sama dipakai ulang). Barang setengah jadi tanpa BOM aktif sengaja `throw` (bukan diam-diam dilewati), karena diam-diam melewatkannya akan bikin kebutuhan bahan understated tanpa peringatan.
- **`LeadTimeCalculator`**: pakai hasil `BomExploder` untuk `L_beli = MAX(lead_time_hari)` bahan baku dan `L_produksi = SUM(hariUntuk())` tiap tahapan, lalu `L_total`, tanggal mulai produksi, dan tanggal pesan bahan (mundur dari awal periode penjualan).
- Semua dites dulu manual via `php artisan tinker` pakai rantai BOM asli BJ-01 (5 tahapan berantai, 6 bahan baku) sebelum ditulis test: hasilnya **persis** cocok komentar seeder sendiri (`L_beli=14` dari Plat Besi, `L_produksi=6` hari untuk target kecil), bukti independen bahwa rekursinya benar.
- **`TargetProduksiPlanner`**: forecast → Safety Stock (`Z * sigma_e * sqrt(LT_periode)`, `Z` dihitung dinamis dari `Distribution::zScore(barang.service_level/100)`, bukan di-hardcode 1.65) → Reorder Point → Target Produksi → ledak BOM jadi `kebutuhan_bahan`. Beberapa keputusan cakupan karena docs tidak memberi rumus eksplisit: (1) "stok setengah jadi siap rakit" diambil dari komponen setengah-jadi langsung pada BOM barang jadi; (2) "Safety Stock bahan" per komponen dihitung dengan meledakkan BOM yang sama memakai jumlah = Safety Stock (bukan formula statistik baru, cuma pakai ulang mekanisme ledak BOM yang sudah ada); (3) status `kritis` pada `target_produksi` dan `mendesak` pada `kebutuhan_bahan` didefinisikan sendiri (docs cuma menyebut dua status eksplisit di masing-masing, bukan tiga) berdasarkan perbandingan ke `stok_minimum`.
- Dites end-to-end pakai forecast BJ-01 asli: dengan kapasitas tahapan dummy (150-300 unit/hari) dan forecast ~1500 unit/bulan, `L_total` jadi 57 hari (bukan bug, angka kapasitas seeder memang masih asumsi kasar, tercatat di komentar seedernya sendiri).
- **`WaktuTungguController`** + **`TargetProduksiController`**: dibangun persis mengikuti route yang sudah di-scaffold di `routes/analisis.php` (nama method `index`/`hitung`/`setujui` sudah ditentukan duluan). Target Produksi pakai pola master-detail satu halaman (`?barang=`, `?target=`) yang sama seperti Data Historis di Fase 4, bukan bikin halaman `show` terpisah karena scaffold rute memang tidak menyediakannya.
- 2 menu sidebar terakhir di grup "Peramalan (ARIMA)" (Waktu Tunggu, Target Produksi) diaktifkan; grup "Simulasi & Pengujian" masih `#` semua, itu Fase 6.
- Unit/Feature test baru: `BomExploderTest` (4), `LeadTimeCalculatorTest` (4), `TargetProduksiPlannerTest` (7), `WaktuTungguTest` (5), `TargetProduksiTest` (7): total 27 test baru.
- Verifikasi akhir: `php artisan test` penuh → **301 passed, 0 failed**.

**Semua menu di grup "Peramalan (ARIMA)" sudah aktif dan berfungsi penuh.** Lanjut ke **Fase 6** (Simulasi Backtest), tolak ukur utama keberhasilan sistem menurut revisi sidang.

### 2026-09-21: Fase 6 selesai (Simulasi Backtest, tolak ukur utama skripsi)

- **`SimulationMetric`**: total stockout, jumlah bulan stockout, rata-rata stok akhir, total overstock, service level tercapai, perputaran persediaan, total biaya: 7 rumus persis docs/01 §7.3, dites hitung tangan.
- **`KebijakanPerusahaan`**: 3 metode pembanding (`produksi_aktual`, `naif_bulan_lalu`, `rata_rata_bergerak`). Tidak bergantung pada simulasi stok, jadi bisa dihitung di muka untuk 12 bulan sekaligus (beda dari `KebijakanSistem`).
- **`KebijakanSistem`**: satu fungsi murni `rencanaProduksi(forecastSepanjangWaktuTunggu, SS, stokAwal, barangDalamProses)` (lihat revisi di bawah), hasilnya selalu dibulatkan ke bawah minimal 0 (tidak mungkin produksi negatif).
- **`SimulationRunner`**: orkestrator paling kompleks di Modul B. Data historis dibagi 24 bulan pertama (bentuk model ARIMA lewat StationarityTest + ArimaEstimator + Forecaster langsung, TANPA lewat BoxJenkinsPipeline supaya tidak menyimpan baris peramalan yang tidak perlu) dan 12 bulan terakhir (diuji). Kedua skenario dijalankan lewat satu method `simulasikan()` yang sama, dibedakan lewat callback penentu `rencana_produksi`, jadi mekanisme stok (barang_masuk, terpenuhi, stockout, overstock, biaya) dijamin identik persis antar skenario sesuai docs/01 §7.5.
- **Temuan awal, bukan bug**: pas dites pertama kali pakai data BJ-01 asli, skenario Sistem hasilnya **lebih buruk** dari kebijakan naif (`is_sistem_lebih_baik=false`), stockout-nya jauh lebih tinggi. Sudah ditelusuri manual step-by-step: penyebabnya rumus sistem di docs (`forecast(t) + SS - stok_awal(t) - barang_dalam_proses`) cuma menetralkan permintaan SATU bulan, padahal waktu tunggu di data ini setara 1-2 bulan penuh. Begitu ada 1 bulan "kosong" tanpa kiriman (delay pipa produksi), formulanya keliru menganggap kiriman yang sedang berjalan sudah cukup untuk bulan berikutnya juga, sehingga muncul pola bergantian order-besar/order-nol yang bikin stockout menumpuk.
- **`SimulasiController`** (`index`, `create`, `store`, `show`) + **`PerbandinganController`** (`show`), dibangun sesuai scaffold route yang sudah ada. `cetak` sengaja dibiarkan off, itu Fase 7 (`LaporanSimulasiController`). Sidebar: "Jalankan Simulasi" → `create`; "Perbandingan Skenario" dan "Rincian Bulanan" sama-sama → `index` (pilih baris dulu, baru bercabang ke halaman perbandingan atau rincian masing-masing) karena scaffold tidak menyediakan cara menunjuk simulasi tertentu tanpa memilih dulu.
- Unit/Feature test baru: `SimulationMetricTest` (3), `KebijakanPerusahaanTest` (6), `KebijakanSistemTest` (2), `SimulationRunnerTest` (5, regression-lock pakai fixture yang sama persis dengan yang dicapture manual lewat tinker), `SimulasiTest` (7): total 23 test baru.
- Verifikasi akhir: `php artisan test` penuh → **324 passed, 0 failed**.

### 2026-09-21 (revisi sore): Perbaikan rumus KebijakanSistem, disetujui & diminta user

Temuan di atas dibawa ke user, didiskusikan, dan **disetujui untuk diperbaiki** (bukan sekadar dicatat sebagai keterbatasan). Perubahan:

- **`docs/01-alur-kerja-sistem.md` §7.2 direvisi lebih dulu** (baris formula skenario Sistem + poin baru di §7.5 "Catatan Kejujuran Metodologi" menjelaskan alasan revisi) sebelum kode diubah, supaya dokumen tetap jadi sumber kebenaran yang konsisten dengan implementasi.
- **Rumus baru**: `rencana_produksi(t) = SUM(forecast(t)..forecast(t+delay)) + SS - stok_awal(t) - barang_dalam_proses`, dengan `delay = CEIL(L_total/30)`. Bukan rumus baru dari nol: ini rumus baku "order-up-to level" pada teori periodic-review inventory (protection period = lead time + review interval), dan otomatis kembali sama persis dengan rumus awal saat `delay=0`.
- `KebijakanSistem::rencanaProduksi()` parameter pertamanya berubah dari `float $forecastT` jadi `array $forecastSepanjangWaktuTunggu` (forecast dari bulan t sampai t+delay). `SimulationRunner` diubah urutan eksekusinya: waktu tunggu (`$delay`) sekarang dihitung SEBELUM memanggil `Forecaster`, supaya forecast bisa diperpanjang jadi `12 + delay` bulan (bukan cuma 12) untuk menyuplai jendela forecast yang dibutuhkan bulan-bulan terakhir simulasi.
- **Bug kedua ketemu waktu memverifikasi ulang lewat tinker**: perhitungan `barang_dalam_proses` (WIP) di `SimulationRunner` off-by-one sejak implementasi awal Fase 6: jendelanya `[t-delay+1, t-1]` padahal seharusnya `[t-delay, t-1]` (kurang satu bulan pesanan yang harusnya masih dianggap "dalam perjalanan"). Ini bug independen dari masalah rumus di atas, ketemu sambil menelusuri hasil yang masih ganjil (overstock meledak) setelah rumus utamanya diperbaiki. Sudah diperbaiki sekalian.
- **Hasil akhir setelah kedua perbaikan** (fixture uji, `stok_awal=200`, `naif_bulan_lalu`): pola bulanan sekarang stabil (tidak ada lagi order-besar/order-kosong bergantian). Stockout Sistem turun dari kondisi terburuk sebelumnya, tapi **masih sedikit di atas** kebijakan naif (2.680 vs 2.071 unit) dan overstock Sistem lebih tinggi (1.020 vs 544 unit, sebagian besar cuma efek batas akhir jendela simulasi bulan ke-12 yang tidak punya data bulan ke-13 untuk dibandingkan). `is_sistem_lebih_baik` masih `false` untuk fixture ini.
- **Kesimpulan jujur**: rumusnya sekarang sudah benar secara teori (ditelusuri manual, konsisten dengan rumus baku inventory management). Sisa gap performa yang teramati kemungkinan besar berasal dari **akurasi forecast ARIMA itu sendiri** terhadap tren naik yang cukup tajam di 12 bulan terakhir data BJ-01, bukan dari cacat rumus rencana produksi lagi. Ini titik diskusi yang sehat untuk bab pembahasan skripsi (keterbatasan model, bukan keterbatasan implementasi), dan hasil bisa berbeda signifikan begitu data dummy diganti data penjualan asli perusahaan.
- Test terdampak diperbarui: `KebijakanSistemTest` (jadi 3 test, tambah kasus `delay=0` sama dengan rumus awal), `SimulationRunnerTest` (angka regression-lock diperbarui sesuai hasil baru).
- Verifikasi akhir: `php artisan test` penuh → **325 passed, 0 failed**.

**Logic simulasi backtest (tolak ukur utama revisi sidang) sudah selesai, rumusnya sudah diperbaiki dan diverifikasi presisi.** Hasil akhirnya masih menunjukkan sistem belum unggul pada data dummy ini, tapi itu bukan masalah kode, melainkan bahan diskusi jujur untuk bab pembahasan. Sisa **Fase 7**: `LaporanSimulasiController` + export PDF, dan wiring akhir (`routes/analisis.php` sudah aktif semua kecuali `cetak`+`laporan.simulasi`; `sidebar-analisis.blade.php` tinggal satu item "Laporan Hasil Simulasi" yang masih `#`).

### 2026-09-21 (audit tambahan): 2 bug lagi ketemu lewat code review menyeluruh, sudah diperbaiki

Atas permintaan user, seluruh `app/Services/{Arima,Produksi,Stok,Simulasi}` dan `app/Support/Math` diaudit ulang pakai skill `/code-review` level tinggi (bukan sekadar dites manual). Ketemu 2 temuan valid:

1. **`AccuracyMetric::mape()` pembagian oleh nol.** `TimeSeriesBuilder::bangun()` sengaja mengisi `nilai_zt=0` untuk bulan tanpa penjualan (Fase 1, supaya `urutan_t` tidak bolong). Barang dengan permintaan musiman/jarang yang punya bulan bernilai nol di rentang in-sample akan bikin `mape()` membagi dengan nol (`INF`/`NAN`), yang lalu gagal disimpan ke kolom `peramalan.mape` (decimal) dan menggagalkan seluruh `BoxJenkinsPipeline::jalankan()` di tengah transaction. Padahal `hasil_peramalan.persen_error` di tempat lain sudah benar menjaga kasus ini (`$aktual != 0 ? ... : null`), jadi ini murni inkonsistensi, bukan pilihan sengaja. Diperbaiki: bulan dengan aktual nol dilewati dari rata-rata MAPE (n disesuaikan), bukan dihitung sebagai pembagian oleh nol.
2. **`BomExploder` tidak punya deteksi BOM berputar.** Validasi yang ada (`BomRequest`) cuma menolak barang yang jadi komponen buat dirinya sendiri secara langsung (satu level). Tapi BOM antar dua barang berbeda yang saling membutuhkan lintas level (A butuh B, B, di resep terpisah, butuh A lagi) tidak tertangkap validasi itu, dan akan bikin `BomExploder::jelajahi()` rekursi tanpa henti sampai stack overflow begitu `LeadTimeCalculator`/`TargetProduksiPlanner` memakainya. Diperbaiki: rekursi sekarang membawa jalur (`$jalur`, barang_id yang sedang dieksplorasi) dan langsung melempar `RuntimeException` yang jelas begitu mendeteksi barang muncul dua kali di jalur yang sama.

Kedua bug ini **laten** (tidak pernah ketriggered oleh data seeder yang ada, itu sebabnya lolos terus di semua test sebelumnya), baru kelihatan lewat audit khusus, bukan lewat kegagalan test yang sudah ada. Test baru ditambahkan utk kedua kasus supaya laten seperti ini tidak terulang. Verifikasi akhir: `php artisan test` penuh → **328 passed, 0 failed**.

### 2026-09-22: Fase 7 selesai (Laporan PDF + wiring akhir), dikerjakan 2 subagent, direview manual

Dikerjakan pakai 2 agent terpisah (bukan paralel, berurutan, biar tidak ada 2 proses menulis `routes/analisis.php` di saat bersamaan), lalu HASILNYA DIREVIEW MANUAL satu-satu sebelum dianggap selesai (bukan langsung dipercaya dari laporan agent).

- **Agent 1 (7.1 bagian 1): `SimulasiController::cetak()`**: PDF cetak SATU simulasi lengkap (kesimpulan, ringkasan berdampingan, rincian bulanan 2 skenario), template baru `resources/views/simulasi/pdf/cetak.blade.php` mengikuti gaya visual `laporan/pdf/umum.blade.php` (dompdf, CSS inline, tanpa Tailwind). Tombol "Cetak PDF" ditambahkan di halaman `show` dan `perbandingan`.
- **Agent 2 (7.1 bagian 2 + 7.2): `LaporanSimulasiController`**: ternyata Modul A sudah punya `LaporanController` abstrak (dipakai 4 laporan operasional: judul()+susun() saja, sisanya termasuk unduh PDF/Excel sudah generik). `LaporanSimulasiController` tinggal mewarisi kelas itu persis seperti `LaporanPenjualanController`, jadi TIDAK perlu view baru sama sekali, otomatis dapat unduh PDF dan Excel gratis dari kerangka yang sudah ada. Filter tanggal memakai `created_at` (kapan simulasi dijalankan), bukan `periode_awal`/`periode_akhir` (periode 12 bulan yang diuji) karena dua hal itu beda konsep. Sekaligus mengaktifkan sisa wiring: route `laporan/simulasi` dan menu sidebar "Laporan Hasil Simulasi".
- **Hasil review manual** (bukan cuma percaya laporan agent):
  1. Ketemu 1 pelanggaran aturan gaya di kerjaan Agent 1: pakai `&mdash;` (entitas HTML untuk em dash, U+2014) di satu judul tabel PDF. Secara visual sama persis dengan karakter em dash yang sudah dilarang di aturan gaya project ini, tapi grep biasa untuk karakter Unicode tidak menangkapnya karena beda encoding. Diperbaiki jadi titik dua biasa.
  2. PDF hasil Agent 1 benar-benar dibuka dan dibaca (bukan cuma dicek `%PDF` doang): 2 halaman, layout rapi, angka di kesimpulan cocok dengan angka di tabel ringkasan dan rincian bulanan (dicek manual: jumlah kolom stockout 12 bulan = angka total di ringkasan).
  3. Kerjaan Agent 2 sendiri yang mem-flag: nilai "Total penghematan biaya" bisa negatif (skenario Sistem lebih mahal dari kebijakan lama), tapi ditulis "Rp -1.667.370" yang agak janggal dibaca. Diperbaiki jadi "-Rp 1.667.370" (tanda minus di depan "Rp", bukan di antara "Rp" dan angka).
  4. `routes/analisis.php` hasil gabungan kedua agent dicek manual: tidak ada baris yang saling tertimpa, semua route sudah aktif, tidak ada lagi baris berkomentar.
  5. Sidebar `sidebar-analisis.blade.php` dicek: seluruh 8 menu Modul B (Peramalan (ARIMA) 4 menu + Simulasi & Pengujian 4 menu) sudah aktif, tidak ada lagi `href="#"` yang tersisa.
- Test baru: 5 test PDF cetak (`SimulasiTest`, ditambahkan Agent 1) + 5 test `LaporanSimulasiTest` (Agent 2) = 10 test baru.
- Verifikasi akhir (dijalankan ulang manual, bukan cuma laporan agent): `php artisan test` penuh → **334 passed, 0 failed**.

**Modul B (7 fase) selesai semua.** Seluruh menu "Peramalan (ARIMA)" dan "Simulasi & Pengujian" sudah aktif dan berfungsi penuh lewat browser, dari agregasi data historis sampai laporan PDF/Excel hasil simulasi backtesting. Catatan yang masih perlu ditindaklanjuti user (bukan kerjaan kode lagi): (1) diskusikan temuan performa skenario Sistem pada Fase 6 ke dosen pembimbing, (2) data penjualan masih dummy seeder, ganti dengan data asli CV. Pande Sejahtera sebelum dipakai di laporan skripsi final.

### 2026-09-22: Audit RBAC — menu Peramalan & Simulasi belum digate per role

Audit menyeluruh (bukan cuma Modul A) menemukan `RoleMiddleware` cuma dipakai di 1 dari ~12 area menu di seluruh aplikasi (lihat detail lengkap di `docs/06-todolist-modul-a.md` §6). Untuk sisi Modul B, `routes/analisis.php` **seluruhnya cuma dijaga `auth`+`verified`**, tidak ada `role:` middleware sama sekali, dan `sidebar-analisis.blade.php` tidak punya satupun `@if` role (beda dari `sidebar-operasional.blade.php` yang setidaknya sudah menyembunyikan menu Pengguna dari non-admin).

Dampaknya cukup serius untuk area ini karena sesuai `docs/01-alur-kerja-sistem.md` §10, **Peramalan & Target Produksi** dan **Simulasi & Pengujian** (tolak ukur utama skripsi) seharusnya cuma bisa dijalankan penuh oleh Pimpinan — termasuk aksi `target.setujui` (approve target produksi) yang sekarang bisa dipanggil siapapun yang login, bukan cuma Pimpinan.

**Target sesuai `docs/01` §10 — selesai 2026-09-22:**

- [x] Peramalan & Target Produksi (`peramalan.*` di `routes/analisis.php`) — admin & produksi lihat saja, gudang tidak boleh akses, pimpinan penuh (termasuk `target.setujui`)
- [x] Simulasi & Pengujian (`simulasi.*`) — admin lihat saja, produksi & gudang tidak boleh akses, pimpinan penuh

**Pendekatan:** sama seperti Modul A, tidak ada middleware baru. Karena `routes/analisis.php` tidak pakai `Route::resource()` (semua rute ditulis eksplisit), aturannya jadi: rute GET yang murni menampilkan (index/show) masuk grup `role:admin,produksi,pimpinan` (peramalan) atau `role:admin,pimpinan` (simulasi), rute POST/PATCH yang memicu aksi (`historis.agregasi`, `forecasting.proses`, `waktu-tunggu.hitung`, `target.hitung`, `target.setujui`, `simulasi.create`, `simulasi.store`) masuk grup `role:pimpinan` saja. `waktu-tunggu.hitung` sebenarnya cuma kalkulasi tanpa nulis ke DB, tapi tetap diperlakukan sebagai "tulis" supaya konsisten dengan makna "lihat saja" di `docs/01` §10 (gudang sama sekali tidak boleh akses Peramalan, jadi tidak relevan di sini). Urutan pendaftaran rute `simulasi.create`/`simulasi.store` (grup pimpinan) tetap ditaruh sebelum grup baca yang punya rute `{simulasi}` (show), mengikuti pola yang sama dengan Modul A supaya `create` tidak ketiban wildcard `{simulasi}`.

Sidebar `sidebar-analisis.blade.php` diberi `@if` role: grup menu "Peramalan (ARIMA)" disembunyikan total dari gudang, grup "Simulasi & Pengujian" disembunyikan dari produksi & gudang, dan tombol "Jalankan Simulasi" di dalam grup itu cuma tampil untuk pimpinan (admin tetap lihat "Perbandingan Skenario", "Rincian Bulanan", "Laporan Hasil Simulasi"). Tombol write-tier di view (`peramalan/{historis,forecasting,waktu-tunggu,target}/index.blade.php`, `simulasi/index.blade.php`) juga digate sama.

Test baru: `tests/Feature/Peramalan/PeramalanRoleAksesTest.php` (3 test, cek gudang ditolak total, admin/produksi cuma lihat, pimpinan bisa jalankan aksi tulis) dan `tests/Feature/Simulasi/SimulasiRoleAksesTest.php` (3 test). Detail lengkap RBAC seluruh aplikasi (kedua modul) ada di `docs/06-todolist-modul-a.md` §6. `php artisan test` penuh setelah kedua modul selesai digate: **359 passed, 0 failed**.
