# Roadmap Pengerjaan Modul A — Operasional Pabrik

Urutan di bawah ini **bukan urutan menu**, tapi urutan ketergantungan: yang tidak bergantung pada apa pun dikerjakan lebih dulu, yang dibutuhkan banyak modul lain didahulukan.

---

## Prinsip yang Menentukan Urutan Ini

1. **Master data mandiri lebih dulu** — Supplier, Pelanggan, dan Tahapan Produksi tidak bergantung pada apa pun, jadi bisa langsung dikerjakan dengan pola yang sama persis seperti Kategori.
2. **Barang adalah simpul pusat** — seluruh transaksi mengacu ke sana, tapi Barang sendiri butuh Kategori dan Supplier. Jadi posisinya tepat setelah keduanya.
3. **Pencatatan stok dibuat sebelum transaksi apa pun** — Pembelian, Produksi, dan Penjualan ketiganya menulis mutasi stok. Kalau logika stok baru dibuat setelah ketiganya jadi, ketiganya harus dibongkar ulang. Ini kesalahan paling sering terjadi di proyek seperti ini.
4. **Produksi dikerjakan sebelum laporan** — modul paling berat jangan ditaruh di akhir.

---

## Tahap 1 — Master Data Mandiri

Pola sudah ada dari CRUD Kategori, tinggal disalin dan disesuaikan. Paling cepat dikerjakan.

- [x] **Supplier** — field tambahan: telepon, email, alamat, `lead_time_default`, `is_aktif`
      (penghapusan dikunci bila masih dipakai barang/pembelian — pakai nonaktifkan)
- [x] **Pelanggan** — field tambahan: jenis (toko/distributor/perorangan/instansi), kota, `is_aktif`
      (penghapusan dikunci bila sudah punya transaksi penjualan — pakai nonaktifkan)
- [x] **Tahapan Produksi** — `urutan` (unik, divalidasi), `waktu_proses_hari`, `kapasitas_per_hari`
      (daftar menampilkan total L_produksi tahapan aktif; perubahan waktu proses dicatat di log aktivitas)

> Tahapan Produksi terlihat sepele, tapi `waktu_proses_hari` dan `kapasitas_per_hari` adalah **bahan baku perhitungan waktu tunggu Modul B**. Pastikan bisa diedit dan divalidasi (tidak boleh negatif, `urutan` unik).

---

## Tahap 2 — Simpul Pusat

- [x] **Barang** — CRUD paling berat di kelompok master

Yang membedakan dari CRUD lain:

| Kebutuhan | Catatan | Cara penyelesaian |
|---|---|---|
| Dropdown `jenis_barang` | bahan_baku / setengah_jadi / barang_jadi | `BarangController::JENIS`, dipakai bersama form + filter + validasi |
| Relasi kategori & supplier | supplier boleh kosong untuk setengah jadi & barang jadi | supplier `required` khusus bahan baku, selain itu boleh kosong |
| Filter per jenis | daftar barang akan panjang, filter wajib ada | filter jenis, kategori, status, + penyaring stok menipis |
| `is_diramalkan` | hanya masuk akal untuk barang jadi — kunci lewat validasi | aturan tambahan di `withValidator()` pada `BarangRequest` |
| `lead_time_hari` | dipakai Modul B sebagai `L_beli` | wajib, 0–365 hari |
| `stok_tersedia` | **read-only di form** — hanya boleh berubah lewat mutasi stok | tidak ada di form request; `store()` memaksa 0, `update()` membuang kiriman stok |

> Penghapusan barang dijaga ketat. Sebagian foreign key ke tabel `barang` memakai
> `cascadeOnDelete` (`mutasi_stok`, `data_time_series`, `peramalan`, `target_produksi`,
> `simulasi`), sehingga menghapus satu barang jadi bisa ikut menghapus seluruh hasil
> peramalan dan simulasi miliknya tanpa peringatan dari database. Karena itu
> penghapusan hanya boleh untuk barang yang belum tersentuh transaksi maupun analisis.

---

## Tahap 3 — Fondasi Stok (jangan dilewati)

- [x] **`app/Services/Stok/StockMutator.php`** — satu-satunya pintu masuk perubahan stok
- [x] **`app/Observers/MutasiStokObserver.php`** — perbarui `barang.stok_tersedia` otomatis setiap ada mutasi
      (didaftarkan di `AppServiceProvider::boot()`, bukan lewat atribut pada model, karena model dibekukan)
- [x] **Persediaan → Stok Saat Ini** — daftar stok + penanda menipis/kosong + nilai persediaan
- [x] **Persediaan → Mutasi Stok** — riwayat masuk/keluar/penyesuaian, disaring per barang/jenis/sumber/tanggal
- [x] **Stok Opname** — penyesuaian manual (index/create/store saja, tidak boleh diubah atau dihapus)

Keputusan penting saat pengerjaan:

| Hal | Keputusan | Alasan |
|---|---|---|
| Arti `jumlah` | MASUK & KELUAR selalu positif; PENYESUAIAN bertanda | Arah mutasi sudah ditentukan `jenis_mutasi`, jadi tanda hanya diperlukan untuk koreksi |
| Sumber `stok_awal` | Diambil dari `stok_akhir` mutasi terakhir, bukan dari `barang.stok_tersedia` | `mutasi_stok` bertipe decimal(15,4) sedangkan `barang.stok_tersedia` integer; rantai mutasi jadi tetap utuh walau nilai pada barang dibulatkan |
| Stok minus | Ditolak `StokTidakCukupException` | Barang tidak bisa diambil dari gudang bila tidak ada |
| Mutasi bersamaan | Baris barang dikunci `lockForUpdate` di dalam transaksi | Dua proses tidak boleh menghitung stok awal yang sama |
| Opname tanpa selisih | Tidak mencatat apa pun | Mutasi bernilai nol hanya mengotori riwayat |

Kontrak `StockMutator` yang dipakai seluruh transaksi berikutnya:

```php
$stockMutator->catat(
    barang: $barang,
    jenis: MutasiStok::MASUK,      // MASUK | KELUAR | PENYESUAIAN
    sumber: 'pembelian',            // pembelian | produksi | penjualan | opname
    jumlah: 100,
    tanggal: now(),
    referensi: $pembelian,          // model sumbernya
    keterangan: 'Penerimaan dari UD Baja Perkasa'
);
```

Method ini yang mengisi `stok_awal` dan `stok_akhir`, lalu memperbarui `barang.stok_tersedia`. Setelah ini jadi, tiga modul transaksi berikutnya tinggal memanggilnya.

---

## Tahap 4 — Transaksi

- [x] **Pembelian** — header + detail, status dipesan → diterima → batal
- [x] **Penerimaan barang** — saat status jadi `diterima`, catat mutasi **MASUK**
- [x] **Penjualan** — header + detail, catat mutasi **KELUAR** seketika
- [x] **Import Excel Penjualan** — berkas contoh dibuat di tempat lewat
      `penjualan/import/template`, bukan berkas statis, agar kolomnya dijamin
      selalu sama dengan yang dibaca `PenjualanImport`

Keputusan penting saat pengerjaan:

| Hal | Keputusan | Alasan |
|---|---|---|
| Kapan stok pembelian bergerak | Saat **penerimaan**, bukan saat order dibuat | Memesan barang tidak sama dengan barangnya sudah ada di gudang |
| Order yang sudah diterima | Dikunci: tidak bisa diubah, dihapus, atau diterima ulang | Stoknya sudah bergerak; mengubahnya akan membuat catatan gudang berbohong |
| Kapan stok penjualan bergerak | **Seketika** saat faktur disimpan | Penjualan tidak punya tahap "dipesan"; barang langsung keluar |
| Faktur kekurangan stok | Seluruh faktur ditolak, bukan sebagian | Satu transaksi utuh; tidak boleh ada faktur setengah jadi |
| Mengubah faktur penjualan | Hanya bagian kepala; baris barang terkunci | Stok sudah bergerak. Isi yang keliru diperbaiki dengan menghapus lalu membuat ulang |
| Menghapus faktur penjualan | Stok dikembalikan lewat mutasi **MASUK pengimbang** | Mutasi bersifat catat-tambah; jejak barang pernah keluar tidak dihapus |
| **Import Excel tidak mengubah stok** | Disengaja | Import dipakai memasukkan penjualan **masa lalu** (36 bulan) sebagai bahan ARIMA. Barangnya sudah keluar gudang bertahun lalu; mengurangi stok hari ini dengan angka lama justru merusak stok berjalan |
| Nomor faktur yang sudah ada saat import | Dilewati, bukan ditimpa | Import ulang tidak boleh menggandakan data penjualan |
| Satu baris salah saat import | Seluruh berkas dibatalkan | Data penjualan setengah masuk lebih berbahaya daripada tidak masuk sama sekali |

> Import Excel Penjualan penting didahulukan dari laporan: inilah jalan masuk **data penjualan asli 3 tahun CV. Pande Sejahtera** untuk menggantikan data dummy. Selama masih dummy, hasil simulasi Modul B belum bisa dipakai di laporan skripsi.

- [x] **Import Excel Pembelian** — beda dari Import Excel Penjualan: hasilnya order **berstatus dipesan**, persis order manual, jadi stok tidak tersentuh sampai diterima satu per satu

---

## Tahap 5 — Produksi (paling berat)

- [x] **BOM / Komposisi** — header + detail komponen, validasi: komponen tidak boleh sama dengan output
- [x] **Perintah Produksi** — satu controller untuk lima tahapan, dibedakan `tahapan_id`

Keputusan penting saat pengerjaan:

| Hal | Keputusan | Alasan |
|---|---|---|
| Parameter `{tahapan}` | Diikat ke `kode_tahapan` (mis. TP-01), bukan id | Lebih stabil daripada nama yang boleh diubah pengguna, lebih terbaca daripada id |
| Menu 5 tahapan di sidebar | Dibangkitkan dari master Tahapan Produksi | Menambah tahapan cukup lewat halaman master, tanpa menyentuh kode maupun sidebar |
| Barang hasil perintah | Diambil dari BOM, bukan isian pengguna | Perintah tidak boleh menghasilkan barang yang tidak sesuai resepnya |
| Rencana bahan | **Disalin** saat perintah dibuat, bukan dibaca ulang dari BOM saat selesai | Revisi resep di kemudian hari tidak boleh mengubah perintah yang sudah berjalan |
| Kapan stok bergerak | Hanya saat perintah **diselesaikan** | Membuat dan memulai perintah belum memakai bahan apa pun |
| Kekurangan bahan | Diperiksa sekaligus, seluruhnya dilaporkan | Staf perlu tahu semua bahan yang kurang dalam sekali lihat, bukan gagal satu per satu |
| Produk gagal | Tidak menambah stok | Tidak dapat dijual maupun dipakai tahapan berikutnya |
| Resep beda tahapan | Ditolak validasi | BOM menentukan tahapan sekaligus barang hasilnya |

> **Catatan pembulatan stok.** Uji coba nyata menunjukkan akibat ketidakcocokan
> tipe kolom yang dicatat pada Tahap 3: perintah 10 unit Kepala Sekop memakai
> 0,875 lembar Plat Besi, tetapi karena `barang.stok_tersedia` bertipe integer,
> stok tercatat turun 1 lembar (120 → 119,125 → dibulatkan 119). Rantai
> `mutasi_stok` tetap menyimpan 119,125 secara tepat. Untuk produksi harian
> berjumlah besar selisih ini tidak berarti, tetapi bila perusahaan menjalankan
> banyak perintah kecil, `barang.stok_tersedia` perlu diubah menjadi decimal
> lewat migration baru.

Alur satu perintah produksi:

```
1. Pilih tahapan + BOM + jumlah target
2. Sistem salin komponen BOM -> detail_produksi_bahan (jumlah_rencana)
3. Status: draft -> proses
4. Staf isi realisasi: jumlah_pakai, jumlah_hasil, jumlah_gagal
5. Status -> selesai, saat itu juga:
     - tiap bahan   -> StockMutator KELUAR
     - barang output-> StockMutator MASUK sebanyak jumlah_hasil
```

Validasi yang wajib ada: **stok bahan harus cukup** sebelum status boleh jadi `selesai`.

---

## Tahap 6 — Penutup

- [x] **Laporan Pembelian / Produksi / Persediaan / Penjualan** + export PDF & Excel
- [x] **Dashboard** — ringkasan stok, grafik tren penjualan, aktivitas terbaru
- [x] **Manajemen Pengguna** (khusus admin)
- [x] **Tombol "Buat Order dari Rekomendasi"** — membaca `kebutuhan_bahan` milik Modul B

Keputusan penting saat pengerjaan:

| Hal | Keputusan | Alasan |
|---|---|---|
| Empat laporan | Mewarisi satu `LaporanController` abstrak | Keempatnya berbentuk sama (rentang tanggal, tabel, ringkasan, 3 cara tampil). Tanpa induk bersama, kode cetak PDF dan export Excel tersalin empat kali |
| Cara unduh | Halaman yang sama dengan `?unduh=pdf` / `?unduh=excel` | Penyaring yang sedang dipakai ikut terbawa ke berkas unduhan, tanpa perlu route terpisah |
| Isi laporan | Hanya transaksi yang terwujud (pembelian diterima, produksi selesai) | Order yang masih dipesan belum jadi biaya maupun stok |
| Stok awal pada laporan persediaan | Dihitung mundur dari stok berjalan dikurangi mutasi | Sistem tidak menyimpan potret stok harian; rantai mutasi adalah satu-satunya sumber yang jujur |
| Grafik dashboard | HTML + Tailwind, bukan Chart.js | Grafik batang 12 bulan tidak butuh pustaka; menghindari satu ketergantungan JavaScript baru |
| Bulan kosong pada grafik | Tetap ditampilkan bernilai nol | Deret yang bolong menyesatkan saat dibaca |
| Penjagaan pengguna | Tidak boleh menonaktifkan/menghapus diri sendiri; harus tersisa 1 admin aktif | Tanpa ini sangat mungkin seluruh akses pengelolaan terkunci tanpa jalan masuk |
| Rekomendasi per supplier | Dikelompokkan, satu order satu supplier | Satu nota pembelian ditujukan ke satu pemasok |
| Qty rekomendasi pecahan | Dibulatkan **ke atas** | Memesan setengah lembar tidak mungkin; kurang sedikit lebih merugikan daripada lebih sedikit |

> **Titik temu dengan Modul B.** Tombol rekomendasi dikerjakan lebih dulu meski
> tabel `kebutuhan_bahan` masih kosong, karena skema dan kontraknya sudah beku
> sejak fase 0 dan model `KebutuhanBahan` sudah menyediakan `scopePerluBeli()`.
> Halaman menampilkan keterangan "belum ada rekomendasi" selama Modul B belum
> mengisi tabelnya. Uji coba memakai baris `kebutuhan_bahan` buatan sendiri yang
> menirukan keluaran Modul B — **belum pernah diuji dengan data Modul B yang
> sebenarnya.**

---

## Ringkasan Bobot

| Tahap | Isi | Bobot |
|---|---|---|
| 1 | 3 CRUD master mandiri | Ringan — pola sudah ada |
| 2 | CRUD Barang | Sedang |
| 3 | Fondasi stok | **Sedang, tapi paling menentukan** |
| 4 | Pembelian, Penjualan, Import | Sedang |
| 5 | BOM + Produksi 5 tahapan | **Berat** |
| 6 | Laporan, dashboard, pengguna | Sedang |

---

## Yang Ditunggu Modul B dari Anda

Modul B sudah bisa jalan penuh dari data seeder, jadi **tidak ada yang memblokirnya**. Tapi dua hal ini menentukan kualitas hasil akhirnya:

| Kebutuhan B | Dari tahap | Kenapa penting |
|---|---|---|
| `tahapan_produksi.waktu_proses_hari` bisa diedit | Tahap 1 | Dipakai menghitung waktu tunggu produksi |
| Import penjualan data asli perusahaan | Tahap 4 | Selama masih dummy, hasil simulasi belum layak masuk laporan skripsi |
