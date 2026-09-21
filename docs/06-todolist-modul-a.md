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
