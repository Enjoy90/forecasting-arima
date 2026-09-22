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
- [ ] BOM / Komposisi — target: admin & produksi penuh, gudang tidak boleh akses, pimpinan lihat saja
- [ ] Produksi (5 tahap + aksi perintah) — target: admin & produksi penuh, gudang & pimpinan lihat saja
- [ ] Persediaan & Mutasi Stok, Opname — target: admin & gudang penuh, produksi & pimpinan lihat saja
- [ ] Penjualan (faktur, import) — target: admin penuh, produksi & gudang tidak boleh akses, pimpinan lihat saja

Sisa area (Peramalan, Target Produksi, Simulasi) ada di `routes/analisis.php`, dicatat di `docs/06-todolist-modul-b.md`.

**Pendekatan yang dipakai (diputuskan 2026-09-22):** tidak bikin middleware baru. Tiap `Route::resource()` dipecah jadi 2 grup: grup "tulis" (`->except(['index','show'])`, isinya create/store/edit/update/destroy) didaftar LEBIH DULU dengan daftar role lebih ketat, baru grup "baca" (`->only(['index','show'])`) didaftar SETELAHNYA dengan daftar role lebih longgar — dua-duanya pakai `role:` middleware yang sudah ada. **Urutan pendaftaran ini penting**: kalau grup baca (yang punya rute `show` berpola `/{id}`) didaftar lebih dulu, rute itu akan menangkap kata "create" sebagai `{id}` dan bikin rute `/create` asli tidak pernah kesentuh (hasilnya 404, bukan halaman create) — ini kejadian nyata, ketangkep dari test yang baru ditulis, bukan cuma teori. Tombol Tambah/Ubah/Hapus di view index (`master/{kategori,barang,supplier,pelanggan,tahapan-produksi}/index.blade.php`, `pembelian/{index,show,rekomendasi}.blade.php`) disembunyikan sesuai role juga, supaya role "lihat saja" tidak melihat tombol yang berujung 403. Sidebar (`sidebar-operasional.blade.php`) disesuaikan sama. Test baru: `tests/Feature/Master/MasterDataRoleAksesTest.php` (6 test) dan `tests/Feature/Pembelian/PembelianRoleAksesTest.php` (3 test) — cek 403/200 tiap role di tiap rute, bukan cuma "tidak error". `php artisan test` penuh: **343 passed, 0 failed**.
