# To-Do List Modul A — Operasional Pabrik

Dokumen ini melanjutkan [`05-roadmap-modul-a.md`](05-roadmap-modul-a.md), yang isinya sudah **Tahap 1–6 selesai** dan terverifikasi lewat test suite (185 test lolos). File ini khusus mencatat **sisa pekerjaan, item yang perlu diuji ulang, dan follow-up** — bukan pengulangan roadmap.

---

## 1. Sudah Dikerjakan Sejak Versi Sebelumnya

- [x] **Import Excel Pembelian** — `ImportPembelianController` + `PembelianImport` + `TemplatePembelianExport` sudah ada. Beda penting dari Import Excel Penjualan: hasil import ini adalah order **berstatus dipesan** (bukan histori "diterima"), jadi stok baru bergerak nanti lewat proses penerimaan manual seperti biasa. Satu order hanya boleh satu supplier, dan barang dobel dalam satu order ditolak — 11 test di `ImportPembelianTest` mencakup semua aturan ini.

---

## 2. Perlu Diuji dengan Data Nyata (bukan data buatan sendiri)

- [ ] **Rekomendasi Pembelian dari `kebutuhan_bahan`** — `RekomendasiController` dan test-nya (`RekomendasiTest`) baru diuji pakai baris `kebutuhan_bahan` buatan sendiri yang meniru keluaran Modul B. **Belum pernah dicoba dengan data yang benar-benar dihasilkan `TargetProduksiPlanner` milik Modul B.**
      Cara verifikasi setelah Modul B selesai mengisi tabelnya:
      1. Jalankan alur peramalan & simulasi Modul B sampai `kebutuhan_bahan` terisi.
      2. Buka `/pembelian/rekomendasi`, pastikan pengelompokan per supplier dan pembulatan ke atas tetap benar dengan angka asli (bukan angka bulat seperti data uji).
      3. Pastikan bahan tanpa supplier tetap terpisah dan tidak bisa diorder.

- [ ] **Import Excel Penjualan dengan data asli CV. Pande Sejahtera** — `ImportPenjualanTest` sudah lolos dengan berkas contoh, tapi belum pernah dijalankan dengan **data penjualan 3 tahun yang sebenarnya**. Ini penting karena selama datanya masih dummy/seeder, hasil ARIMA Modul B belum layak dipakai di laporan skripsi (lihat roadmap Tahap 4).

---

## 3. Follow-up dari Redesain Tampilan (baru dikerjakan)

Layout (`layouts/app.blade.php`, `layouts/navigation.blade.php`, `partials/sidebar.blade.php`) baru diganti ke tema sidebar gelap + topbar ramping. Semua CRUD/route tidak disentuh dan test tetap lolos (196/196). Verifikasi berikut sudah dilakukan lewat server (login sungguhan tiap role + curl), kecuali interaksi visual murni yang butuh browser asli:

- [x] Cek akses keempat role (`admin`, `produksi`, `gudang`, `pimpinan`) — menu "Pengguna" hanya tampil untuk admin, dan akses langsung ke `/master/pengguna` ditolak `RoleMiddleware` (403) untuk 3 role lainnya. Kartu profil sidebar menampilkan nama & role yang benar untuk keempatnya.
- [x] Cek halaman dengan tabel lebar (Data Barang, Stok Saat Ini, Mutasi Stok) — ketiganya sudah dibungkus `overflow-x-auto`, jadi tabel lebar scroll sendiri tanpa mendorong sidebar 256px.
- [x] Cetak PDF/Excel keempat laporan — semua `HTTP 200`, berkas PDF valid (`%PDF` magic bytes) dan Excel valid (`PK` / zip magic bytes untuk `.xlsx`).
- [x] Markup Alpine.js sidebar mobile diperiksa (root `x-data="{ sidebarOpen: false }"`, tombol hamburger `@click="sidebarOpen = true"`, overlay `@click="sidebarOpen = false"`, binding `:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"`) — semua terpasang benar dan saling terhubung.
      > **Batasan:** ini pemeriksaan markup/server-side, bukan uji interaktif di browser sungguhan (klik tombol, lihat animasi geser). Alpine.js adalah pola standar yang sudah terbukti untuk struktur ini, tapi kalau mau yakin 100%, buka `/dashboard` di Chrome DevTools dengan mode responsif (< 1024px) dan coba klik hamburger-nya langsung.
- [x] `partials/sidebar-analisis.blade.php` (milik Modul B) sudah konsisten pakai warna dark theme yang sama.

---

## 4. Catatan Teknis yang Ditunda (bukan bug, keputusan sadar)

- [ ] **`barang.stok_tersedia` bertipe integer** sedangkan `mutasi_stok.jumlah` bertipe `decimal(15,4)`. Untuk produksi harian dengan banyak perintah kecil, pembulatan bisa terakumulasi (lihat catatan di roadmap Tahap 5, kasus Kepala Sekop 10 unit). Kalau nanti dibutuhkan presisi lebih, perlu migration baru mengubah kolom ini jadi decimal — **jangan mengubah migration lama** yang sudah dibekukan.

---

## 5. Sebelum Deploy / Sidang

- [ ] `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, isi `DB_*` sesuai hosting.
- [ ] `php artisan migrate:fresh --seed` di server tujuan untuk memastikan seeder (36 bulan data + 4 akun) jalan bersih dari nol.
- [ ] `php artisan config:cache`, `route:cache`, `view:cache` setelah kode final, dan `npm run build` untuk asset produksi.
- [ ] Ganti password 4 akun seeder (`admin@pande.test`, dst — semua masih `password`) sebelum dipakai di luar lingkungan pengujian.
- [ ] Jalankan `php artisan test` sekali lagi setelah Modul B selesai digabung, untuk memastikan tidak ada regresi lintas modul.

## 6. Kontrol Akses per Role (RBAC) Belum Diterapkan

**Audit 2026-09-22**: matriks hak akses di `docs/01-alur-kerja-sistem.md` §10 sudah lengkap dan siap jadi acuan, tapi baru **1 dari ~12 area menu** yang benar-benar diterapkan di kode. Sisanya bisa diakses semua role yang login, sama seperti admin.

**Yang sudah benar:** Master ▸ Pengguna — `routes/operasional.php` pakai `->middleware('role:admin')`, dan `sidebar-operasional.blade.php` menyembunyikan menunya dari non-admin. Ini satu-satunya tempat `RoleMiddleware` (`app/Http/Middleware/RoleMiddleware.php`, alias `role` di `bootstrap/app.php`) benar-benar dipakai. Tidak ada Policy/`Gate::`/`@can` di manapun, dan seluruh `FormRequest::authorize()` hard-code `return true`.

**Yang masih terbuka ke semua role (perlu digate sesuai `docs/01` §10), khusus route Modul A (`routes/operasional.php`):**

- [x] Master Data (kategori, barang, supplier, pelanggan) — admin penuh, produksi tidak boleh akses, gudang & pimpinan lihat saja — **selesai 2026-09-22**
- [x] Tahapan Produksi — admin & produksi penuh, gudang tidak boleh akses, pimpinan lihat saja — **selesai 2026-09-22**
- [x] Pembelian (order, penerimaan, rekomendasi, import) — admin & gudang penuh, produksi tidak boleh akses, pimpinan lihat saja — **selesai 2026-09-22**
- [x] BOM / Komposisi — admin & produksi penuh, gudang tidak boleh akses, pimpinan lihat saja — **selesai 2026-09-22**
- [x] Produksi (5 tahap + aksi perintah) — admin & produksi penuh, gudang & pimpinan lihat saja — **selesai 2026-09-22**
- [x] Persediaan & Mutasi Stok, Opname — admin & gudang penuh, produksi & pimpinan lihat saja — **selesai 2026-09-22**
- [x] Penjualan (faktur, import) — admin penuh, produksi & gudang tidak boleh akses, pimpinan lihat saja — **selesai 2026-09-22**

Sisa area (Peramalan, Target Produksi, Simulasi) ada di `routes/analisis.php`, dicatat di `docs/06-todolist-modul-b.md` — **selesai juga 2026-09-22**, jadi RBAC sudah diterapkan penuh di kedua modul.

**Pendekatan yang dipakai (diputuskan 2026-09-22):** tidak bikin middleware baru. Tiap `Route::resource()` dipecah jadi 2 grup: grup "tulis" (`->except(['index','show'])`, isinya create/store/edit/update/destroy) didaftar LEBIH DULU dengan daftar role lebih ketat, baru grup "baca" (`->only(['index','show'])`) didaftar SETELAHNYA dengan daftar role lebih longgar — dua-duanya pakai `role:` middleware yang sudah ada. **Urutan pendaftaran ini penting**: kalau grup baca (yang punya rute `show` berpola `/{id}`) didaftar lebih dulu, rute itu akan menangkap kata "create" sebagai `{id}` dan bikin rute `/create` asli tidak pernah kesentuh (hasilnya 404, bukan halaman create) — ini kejadian nyata, ketangkep dari test yang baru ditulis, bukan cuma teori. Untuk rute yang bukan `Route::resource()` (semua route di `routes/analisis.php`, ditulis eksplisit per method), tidak ada isu ini, tapi urutan pendaftaran GET literal (mis. `create`) sebelum GET wildcard (mis. `{simulasi}`) tetap dijaga dengan pola yang sama untuk konsistensi.

Untuk area yang aksinya bukan CRUD biasa (POST tanpa resource, seperti `historis.agregasi`, `forecasting.proses`, `waktu-tunggu.hitung`, `target.hitung`, `target.setujui` di Modul B, atau aksi `mulai/realisasi/selesaikan/batal` pada perintah produksi di Modul A), aturannya: rute GET (index/show, tidak mengubah data) masuk grup "baca", rute POST/PATCH (mengubah data) masuk grup "tulis" — walau secara teknis tidak selalu benar-benar menulis ke database (misal `waktu-tunggu.hitung` cuma kalkulasi tanpa persist), diperlakukan sama sebagai "tulis" supaya konsisten dengan definisi "lihat saja" di `docs/01` §10 (murni lihat, tidak bisa memicu aksi apapun).

Tombol Tambah/Ubah/Hapus/aksi status di seluruh view index & show yang relevan (`master/*`, `pembelian/*`, `produksi/bom/index`, `produksi/perintah/{index,show}`, `persediaan/opname/index`, `penjualan/{index,show}`, `peramalan/{historis,forecasting,waktu-tunggu,target}/index`, `simulasi/index`) disembunyikan sesuai role, supaya role "lihat saja" tidak melihat tombol yang berujung 403. Kedua sidebar (`sidebar-operasional.blade.php`, `sidebar-analisis.blade.php`) disesuaikan sama.

**2 test lama jadi usang dan diperbaiki** (bukan regresi): `DashboardTest::test_menu_kedua_modul_tetap_tampil` dan `DashboardRenderTest::test_dashboard_menampilkan_menu_kedua_modul` sama-sama assert akun **admin** bisa lihat menu "Jalankan Simulasi" di sidebar — itu betul sebelum RBAC ada (semua role lihat semua menu), tapi sekarang salah karena Simulasi & Pengujian memang didesain admin cuma "lihat saja" (bukan pimpinan). Assertion diganti ke "Perbandingan Skenario" (menu yang tetap kelihatan buat admin).

Test baru (RBAC, total 25): `tests/Feature/Master/MasterDataRoleAksesTest.php` (6), `tests/Feature/Pembelian/PembelianRoleAksesTest.php` (3), `tests/Feature/Produksi/ProduksiRoleAksesTest.php` (5), `tests/Feature/Persediaan/PersediaanRoleAksesTest.php` (2), `tests/Feature/Penjualan/PenjualanRoleAksesTest.php` (3), `tests/Feature/Peramalan/PeramalanRoleAksesTest.php` (3), `tests/Feature/Simulasi/SimulasiRoleAksesTest.php` (3) — semuanya cek 403/200/302 tiap role di tiap rute, bukan cuma "tidak error". `php artisan test` penuh: **359 passed, 0 failed**.

**RBAC (RoleMiddleware) sekarang diterapkan di seluruh ~12 area menu**, bukan cuma Master ▸ Pengguna seperti temuan audit awal. Yang masih terbuka buat semua role (memang sesuai desain, bukan celah): Dashboard dan Laporan — keduanya "v" untuk keempat role di `docs/01` §10.

## 7. Audit Keamanan IDOR (2026-09-22) — 1 bug ditemukan & diperbaiki

Atas permintaan user, diaudit apakah RBAC yang baru selesai juga aman dari IDOR (Insecure Direct Object Reference). Karena aplikasi ini tidak punya konsep kepemilikan data per-user (semua staf dengan role yang sesuai memang boleh lihat semua data perusahaan), IDOR klasik "user A lihat data privat user B" tidak relevan di sini — yang relevan adalah IDOR **relasi**: apakah controller memverifikasi bahwa child record benar-benar anak dari parent yang disebut di URL.

**Ketemu 1 bug nyata**: `ProduksiController` (`app/Http/Controllers/Produksi/ProduksiController.php`). Rute `produksi/perintah/{tahapan}/{perintah}` mengikat `{tahapan}` dan `{perintah}` sebagai dua route-model-binding yang **berdiri sendiri-sendiri**, tidak pernah dicek apakah `$perintah->tahapan_id === $tahapan->id`. Akibatnya siapapun yang login sebagai admin/produksi bisa akses perintah produksi milik TP-01 lewat URL `/produksi/perintah/TP-02/...`. Yang paling parah di `update()`: kalau BOM yang dikirim memang milik TP-02 (jadi lolos validasi `ProduksiRequest`), `bom_id`/`barang_output_id` perintah ke-update ke BOM tahapan lain sementara `tahapan_id`-nya tetap tahapan asli — data jadi tidak konsisten, dan laporan produksi yang dikelompokkan per tahapan (`LaporanProduksiController`) menampilkan angka yang salah. Test lama `test_kode_tahapan_asing_menghasilkan_404` cuma nguji kode tahapan yang tidak ada sama sekali (mis. `TP-99`), bukan kombinasi "tahapan valid + perintah valid tapi salah pasangan" — jadi bug ini lolos tidak ketahuan sebelumnya.

**Perbaikan**: ditambah method `pastikanMilikTahapan()` yang dipanggil di awal ke-8 method yang menerima kedua parameter (`show`, `edit`, `update`, `destroy`, `mulai`, `realisasi`, `selesaikan`, `batal`) — `abort_unless($perintah->tahapan_id === $tahapan->id, 404)`. Test baru `test_perintah_tidak_bisa_diakses_lewat_kode_tahapan_yang_salah` di `tests/Feature/Produksi/ProduksiTest.php` menguji ke-8 aksi itu sekaligus. Severity dinilai Medium (bukan privilege escalation — admin & produksi memang sama-sama boleh akses semua tahapan — tapi bisa merusak integritas data). `php artisan test` penuh: **360 passed, 0 failed**.

Area lain yang dicek dalam audit yang sama dan **aman** (tidak ada temuan): `TargetProduksiController` (rute `target.setujui` cuma punya 1 parameter, tidak ada relasi untuk dicek), `ForecastingController::show` (idem), serta seluruh model dicek tidak ada `SoftDeletes`/status privat yang bisa "bocor" lewat akses ID langsung.
