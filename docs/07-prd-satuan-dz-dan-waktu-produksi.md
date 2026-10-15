# PRD: Satuan Lusin (dz) dan Rumus Waktu Produksi Baru

| | |
|---|---|
| Status | Selesai kecuali T12 (user guide) |
| Dibuat | 15 Oktober 2026 |
| Sumber kebutuhan | Masukan Emi (proses produksi CV. Pande Sejahtera), 14 sampai 15 Oktober 2026 |
| Modul terdampak | Master Barang dan Tahapan, Penjualan, Peramalan (Waktu Tunggu, Target Produksi), Simulasi, seeder data |

## 1. Latar Belakang

Saat mencoba halaman Waktu Tunggu dan Target Produksi, Emi menemukan tiga masalah:

1. **Satuan salah.** Barang jadi di perusahaan dihitung per lusin (dz), tetapi sistem memakai pcs. Mengisi "2" di Jumlah Target dibaca 2 pcs, padahal yang dimaksud 2 dz.
2. **Lama produksi tidak masuk akal.** Sistem menghitung 6 hari untuk 2 dz, padahal di pabrik dari nol cukup 3 hari. Untuk target Oktober, tanggal mulai produksi bahkan jatuh di Januari.
3. **Kebutuhan kepala dan handle tidak terlihat.** Dari hasil ARIMA, misalnya 1.000 dz, perusahaan ingin langsung melihat butuh berapa kepala dan handle (pcs).

Penyebab nomor 2: rumus lama menjumlahkan semua tahapan berurutan dan menganggap seluruh target harus selesai di satu tahap sebelum tahap berikutnya mulai, ditambah kapasitas per tahap yang terlalu kecil untuk volume data.

## 2. Tujuan

1. Barang jadi memakai satuan dz di seluruh sistem. Barang setengah jadi tetap pcs, bahan baku tetap satuannya masing-masing.
2. Kapasitas tahapan sesuai kondisi pabrik.
3. Lama produksi dihitung mengikuti cara kerja pabrik: jalur paralel dan tahap yang berjalan sambung-menyambung.
4. Waktu beli bahan hanya dihitung bila ada bahan yang stoknya kurang.
5. Halaman Target Produksi menampilkan kebutuhan barang setengah jadi (kepala, handle, rakitan) dalam pcs.

## 3. Di Luar Lingkup

- **Jadwal produksi harian per jenis sekop** (hari ini coating warna apa, besok rakit produk apa). Ini fitur penjadwalan tersendiri dan dicatat sebagai saran pengembangan.
- **Batas kapasitas lintas bulan.** Sistem tidak membatasi total produksi per bulan. Bulan puncak di data (sampai 1.905 dz) melebihi kapasitas coating sekitar 1.430 dz per bulan, dan ini terlihat dari tanggal mulai produksi yang lebih awal, bukan ditolak.

## 4. Aturan dari Pabrik

| Tahap | Kapasitas per hari | Keterangan |
|---|---|---|
| Beli bahan baku | paling lama 14 hari | Hanya bila ada bahan yang kurang |
| Cetak kepala papak | 1.200 pcs | Jalur sendiri |
| Cetak kepala lancip | 1.200 pcs | Jalur sendiri |
| Handle | 1.200 pcs | Jalur sendiri, berjalan bersamaan dengan cetak kepala |
| Coating | 780 pcs (720 sampai 840) | Gabungan semua jenis, Hammerton dan biru bergantian |
| Rakit + kemas | 80 dz (960 pcs) | Dikemas di hari yang sama dengan rakit |

Urutan dari nol: hari 1 cetak kepala dan handle, hari 2 coating, hari 3 rakit dan kemas.
Coating boleh mulai di hari yang sama dengan cetak bila kepala yang tercetak sudah mencapai satu muatan coating (780 pcs).
Rakit mulai sehari setelah coating, karena coating tidak bisa memenuhi satu hari penuh kapasitas rakit dalam sehari.

## 5. Rumus Baru

```
Hari tahap-i   = PEMBULATAN_ATAS(jumlah di tahap-i / kapasitas tahap-i)
Jalur kritis   = jalur BOM dengan jumlah waktu_proses terbesar
                 (kepala 1 + coating 1 + rakit 1 + kemas 0 = 3 hari)
Tumpang tindih = 1 hari untuk setiap sambungan di jalur yang boleh mulai di hari yang sama:
                 kapasitas tahap sebelumnya >= kapasitas tahap ini, dan jumlah di tahap ini
                 (per tingkat pohon resep, semua produk) >= kapasitas tahap ini
Jalur kritis dipilih dari jalur dengan (jumlah waktu_proses - tumpang tindih) terbesar.

L_produksi = jalur kritis + (hari tahap terlama - 1) - tumpang tindih     [hari kerja]
L_beli     = lead time terlama di antara bahan produk yang direncanakan yang total
             kebutuhannya (semua produk di periode itu) melebihi stok (0 bila semua cukup)
L_total    = L_beli + L_produksi

Tanggal mulai produksi = awal periode penjualan mundur L_produksi hari kerja
Tanggal pesan bahan    = tanggal mulai produksi mundur L_beli hari kalender
```

Kapasitas dipakai bersama. Saat menghitung target produksi satu produk, beban di setiap tahap ikut menjumlahkan prakiraan produk lain untuk periode yang sama. Produk lain hanya memakai kapasitas: jalur kritis dan bahan yang harus dibeli tetap diambil dari produk yang direncanakan. Produk lain yang tidak punya resep aktif dilewati.

Penyederhanaan yang disadari: kapasitas setiap tahap dihitung gabungan untuk semua produk, termasuk cetak kepala (1.200 pcs untuk semua jenis kepala), padahal di pabrik papak dan lancip punya lini sendiri. Ini lebih hati-hati dari kenyataan, tetapi tidak mengubah hasil karena cetak tidak pernah menjadi tahap terlama (1.200 lebih besar dari 780).

## 6. Contoh yang Harus Cocok (Kriteria Selesai)

| Kasus | Lama produksi | Lama total bila bahan harus dibeli |
|---|---|---|
| 2 dz satu produk | 3 hari | 17 hari |
| 56 dz (Papak Croc 15, Lancip Croc 6, Papak Rees 20, Lancip Rees 15) | 3 hari | 17 hari |
| 1.000 dz (250, 125, 375, 250) | 17 hari | 31 hari |
| Bahan sudah tersedia semua | sama | L_beli = 0 |

Contoh ini sama dengan simulasi di folder `simulasi/` yang sudah dicek Emi.

## 7. Rincian Teknis

| Bagian | Perubahan |
|---|---|
| `BarangSeeder` | BJ-01 sampai BJ-04 satuan Dz, harga jual dan HPP per dz (x12), stok dan stok minimum dalam dz |
| `BomSeeder` | Resep pengemasan menghasilkan 1 dz dari 12 rakitan (sebelumnya 12 pcs) |
| `TahapanProduksiSeeder` | Waktu proses dan kapasitas sesuai tabel bagian 4. Pengemasan waktu 0, kapasitas 80 (dz) |
| `database/data/penjualan-...xlsx` | Jumlah dibagi 12, harga dikali 12 (semua baris kelipatan 12, tidak ada sisa) |
| `BomExploder` | Tambah keluaran kebutuhan barang setengah jadi per barang |
| `LeadTimeCalculator` | Rumus bagian 5, beban gabungan beberapa produk, cek stok bahan, rincian per tahap, tanggal memakai hari kerja |
| `TargetProduksiPlanner` | Beban gabungan dari prakiraan produk lain di periode yang sama. Stok setengah jadi dikonversi ke satuan barang jadi |
| `SimulationRunner` | Memakai rumus baru dengan L_beli penuh, karena stok bahan di masa lalu tidak diketahui |
| Halaman Waktu Tunggu | Isian jumlah per produk (dz), tabel rincian per tahap, daftar bahan yang kurang |
| Halaman Target Produksi | Satuan dz, tabel kebutuhan setengah jadi (pcs) |
| Hasil Forecasting, Dashboard, Simulasi, contoh impor | Label satuan dz |
| `docs/01-alur-kerja-sistem.md` | Bab 4.2 diganti rumus baru, dengan catatan revisi |
| Test | `LeadTimeCalculatorTest`, `WaktuTungguTest`, `TargetProduksiPlannerTest`, ditambah test contoh bagian 6 |

## 8. Checklist Pengerjaan

- [x] T1. Satuan dz: seeder barang jadi, resep pengemasan, berkas data penjualan, konversi stok setengah jadi
- [x] T2. Kapasitas dan waktu proses tahapan sesuai pabrik
- [x] T3. `BomExploder` mengeluarkan kebutuhan barang setengah jadi
- [x] T4. `LeadTimeCalculator` rumus baru (jalur kritis, tumpang tindih, beban gabungan, cek stok bahan, hari kerja)
- [x] T5. `TargetProduksiPlanner` dan `SimulationRunner` memakai rumus baru
- [x] T6. Halaman Waktu Tunggu: isian per produk dalam dz dan rincian per tahap
- [x] T7. Halaman Target Produksi: satuan dz dan tabel kebutuhan setengah jadi
- [x] T8. Label dz di Hasil Forecasting, Dashboard, Simulasi, dan contoh impor
- [x] T9. Test diperbarui, test contoh bagian 6 ditambah, seluruh test lolos (374 test)
- [x] T10. Reseed, jalankan ulang ARIMA per produk, cek contoh bagian 6 di aplikasi
- [x] T11. Revisi `docs/01` bab 4.2
- [ ] T12. Perbarui user guide (teks, angka, dan screenshot)

## 9. Hasil Verifikasi (15 Oktober 2026, setelah reseed)

| Pemeriksaan | Hasil |
|---|---|
| Total penjualan | 47.384 dz (sama dengan 568.608 pcs dibagi 12), nilai rupiah tidak berubah |
| Model dan MAPE ARIMA per produk | Sama persis dengan versi pcs: BJ-01 ARIMA(2,0,0) 12,77%, BJ-02 (2,0,1) 9,33%, BJ-03 (2,0,0) 10,60%, BJ-04 (2,0,1) 9,22% |
| Prakiraan Oktober 2026 | BJ-01 771 dz, BJ-02 203 dz, BJ-03 453 dz, BJ-04 181 dz |
| Contoh bagian 6 dengan resep sungguhan (termasuk susut) | 2 dz: 3 hari, 56 dz: 3 hari, 1.000 dz: 17 hari (31 hari bila semua bahan dibeli, 27 hari bila hanya kayu yang kurang) |
| Target BJ-01 November 2026 | 599 dz, waktu tunggu 10 + 27 = 37 hari, pesan bahan 14 Sep, mulai produksi 24 Sep (sebelumnya jatuh di Januari) |
| Seluruh test | 374 lolos |
| Seluruh test setelah perbaikan QA tahap A, B, dan C | 391 lolos |
| Master Tahapan dengan data sungguhan (C1, C2) | Dari nol 3 hari, penghambat Proses Coating (setara 63,7 dz per hari) |

## 10. Risiko

| Risiko | Penanganan |
|---|---|
| Rumus di bab metode skripsi berubah | Ditulis sebagai revisi berdasarkan wawancara proses produksi, seperti revisi rumus simulasi di docs/01 bab 7.5 |
| Semua angka turunan berubah (safety stock, target, simulasi) | Hitung ulang dan perbarui user guide (T10, T12) |
| Excel final tim (Python) masih dalam pcs | MAPE tetap sama. RMSE dan MAE di sistem menjadi 1/12 angka Excel karena satuannya dz |
| Rentang kapasitas (720 sampai 840) | Dipakai nilai tengah 780 dan bisa diubah di menu Tahapan Produksi |

## 11. Catatan untuk Skripsi

Revisi ini mengubah rumus waktu tunggu produksi di docs/01 bab 4.2. Alasannya: rumus lama mengasumsikan setiap tahap harus menyelesaikan seluruh target sebelum tahap berikutnya mulai, padahal di pabrik tahapan berjalan sambung-menyambung dan cetak kepala berjalan bersamaan dengan pembuatan handle. Data kapasitas dan urutan kerja diperoleh dari wawancara dengan pihak produksi pada 14 sampai 15 Oktober 2026.

## 12. Perbaikan Hasil QA (15 Oktober 2026)

Dua agent QA menguji commit `f781eea`: satu untuk logika perhitungan, satu untuk halaman, peran, dan satuan. Hak akses, contoh bagian 6, tanggal hari kerja, dan konsistensi data dz lolos.

Setiap butir di bawah adalah **satu perubahan kecil**, dengan berkas yang disentuh dan syarat selesai yang bisa dicek. Kode kasus seperti [I] atau [B] merujuk ke skenario uji di laporan QA.

### A. Bug tinggi pada rumus

- [x] **A1.** `hitungGabungan` menerima daftar produk utama. Bahan yang kurang dan jalur kritis dihitung dari produk utama saja, kapasitas tetap dari semua produk.
  Berkas: `LeadTimeCalculator.php`. Selesai bila: BJ-01 tidak lagi menunggu plat 1,6 ml milik Rees Q [I].
- [x] **A2.** `hitung` meneruskan `$bebanLain` sebagai beban kapasitas saja.
  Berkas: `LeadTimeCalculator.php`. Selesai bila: BJ-01 2 dz tetap 3 hari walaupun ada produk lain dengan tahap 10 hari [D].
- [x] **A3.** `bebanProdukLain` melewati produk yang tidak punya resep aktif.
  Berkas: `TargetProduksiPlanner.php`. Selesai bila: resep BJ-04 dinonaktifkan, target BJ-01 tetap terhitung [K].
- [x] **A4.** Test untuk [I], [D], dan [K].
  Berkas: `tests/Unit/Produksi/LeadTimeCalculatorTest.php`, `tests/Unit/Stok/TargetProduksiPlannerTest.php`.

### B. Kasus tepi rumus (data pabrik saat ini tidak terkena)

- [x] **B1.** Pohon resep menyimpan semua sambungan induk dan anak, bukan hanya induk pertama.
  Berkas: `BomExploder.php`. Selesai bila: P butuh A dan B, A dan B butuh C, jalur kritis = 7 [B].
- [x] **B2.** Jalur kritis dipilih dari lama produksi terbesar per jalur (tumpang tindih ikut dihitung), bukan urutan id.
  Berkas: `LeadTimeCalculator.php`. Selesai bila: hasil [C] = 6 untuk urutan id mana pun.
- [x] **B3.** Kapasitas tetap dihitung walaupun semua waktu proses 0.
  Berkas: `LeadTimeCalculator.php`. Selesai bila: [G] = 99 hari.
- [x] **B4.** Tumpang tindih memakai jumlah per level, bukan jumlah gabungan satu tahapan.
  Berkas: `LeadTimeCalculator.php`. Selesai bila: [F] = 3 hari.
- [x] **B5.** Test untuk [B], [C], [F], dan [G].
  Berkas: `tests/Unit/Produksi/LeadTimeCalculatorTest.php`.

### C. Halaman dan validasi

- [x] **C1.** Master Tahapan: ringkasan "Total Waktu Produksi" diganti jalur kritis dari nol (3 hari).
  Berkas: `TahapanProduksiController.php`, `master/tahapan-produksi/index.blade.php`. Selesai bila: tampil 3 hari, bukan 4.
- [x] **C2.** Master Tahapan: "Kapasitas Tersempit" dibandingkan dalam satuan setara, sehingga penghambatnya coating 780 pcs, bukan pengemasan 80 dz.
  Berkas: sama dengan C1. Selesai bila: penentu laju = Proses Coating.
- [x] **C3.** Master Tahapan: label kapasitas memakai satuan tiap tahap (pcs atau dz), dan catatan "dijumlahkan berurutan" dihapus.
  Berkas: `master/tahapan-produksi/index.blade.php`.
- [x] **C4.** Waktu Tunggu: isian dz wajib bilangan bulat 1 sampai 100.000.
  Berkas: `WaktuTungguController.php`. Selesai bila: 1.000.000.000, 1e400, dan 0,01 ditolak dengan pesan, bukan menghasilkan tanggal tahun -58129.
- [x] **C5.** Target: parameter `barang` dan `target` yang bukan angka diabaikan.
  Berkas: `TargetProduksiController.php`. Selesai bila: `?target[]=1` dan `?barang[]=1` tidak galat 500.
- [x] **C6.** Laporan: tanggal filter yang tidak valid diabaikan.
  Berkas: `LaporanController.php`. Selesai bila: `/laporan/penjualan?dari=abc` tidak galat 500.
- [x] **C7.** Rincian per tahap di Waktu Tunggu: jumlah pcs dibulatkan ke atas.
  Berkas: `peramalan/waktu-tunggu/index.blade.php`. Selesai bila: 24,24 rakitan tampil 25.
- [x] **C8.** Tabel kebutuhan setengah jadi di Target: jumlah dibulatkan ke atas.
  Berkas: `peramalan/target/index.blade.php`.
- [x] **C9.** Pesan dan log setelah hitung target memakai satuan barang, bukan "unit".
  Berkas: `TargetProduksiController.php`.
- [x] **C10.** Laporan Penjualan: total ditulis dalam dz.
  Berkas: view atau controller laporan penjualan. Selesai bila: "47.384 dz", bukan "unit".
- [x] **C11.** Laporan Produksi: total hasil dipisah per satuan, tidak menjumlahkan pcs dan dz.
  Berkas: view atau controller laporan produksi.
- [x] **C12.** Excel hasil peramalan: kolom prediksi diberi satuan.
  Berkas: `HasilPeramalanExport.php`.
- [x] **C13.** Detail resep: tombol "Ubah resep" hanya tampil untuk Admin dan Produksi.
  Berkas: `produksi/bom/show.blade.php`. Selesai bila: Pimpinan tidak melihat tombol yang berujung 403.

### D. Masukan UX ringan

- [ ] **D1.** Waktu Tunggu: Admin dan Produksi melihat keterangan bahwa perhitungan dijalankan Pimpinan, bukan halaman kosong.
  Berkas: `peramalan/waktu-tunggu/index.blade.php`.
- [ ] **D2.** Waktu Tunggu: tampil "tidak perlu beli bahan" bila waktu beli bahan 0.
  Berkas: `peramalan/waktu-tunggu/index.blade.php`.
- [ ] **D3.** Target Produksi: tampil "tidak perlu beli bahan" bila waktu beli bahan 0.
  Berkas: `peramalan/target/index.blade.php`.
- [ ] **D4.** Waktu Tunggu: peringatan bila tanggal mulai produksi sudah lewat dari hari ini.
  Berkas: `peramalan/waktu-tunggu/index.blade.php`.
- [ ] **D5.** Target Produksi: peringatan bila tanggal mulai produksi sudah lewat dari hari ini.
  Berkas: `peramalan/target/index.blade.php`.

### E. Butuh keputusan

- [ ] **E1.** ARIMA tanpa konstanta bila d ≥ 1, sesuai kebiasaan Box-Jenkins dan bawaan statsmodels. Memperbaiki prakiraan simulasi yang meledak, tetapi hasil peramalan dan simulasi berubah.
- [ ] **E2.** (Opsional) Safety stock memakai satuan hari yang seragam.
- [ ] **E3.** (Opsional) Pesan validasi dalam Bahasa Indonesia.
- [ ] **E4.** (Opsional) MAPE ditulis dengan koma.
- [ ] **E5.** (Opsional) Nilai penjualan di dashboard disembunyikan dari Produksi dan Gudang.

### F. Penutup

- [ ] **F1.** Seluruh test lolos.
- [ ] **F2.** QA ulang singkat untuk butir A sampai D.
- [ ] **F3.** Perbarui PRD, lalu commit dan push.
- [ ] **F4.** Perbarui user guide (T12).
