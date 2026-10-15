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
Tumpang tindih = 1 hari untuk setiap sambungan di jalur kritis yang boleh mulai di hari yang sama:
                 kapasitas tahap sebelumnya >= kapasitas tahap ini, dan jumlahnya >= kapasitas tahap ini

L_produksi = jalur kritis + (hari tahap terlama - 1) - tumpang tindih     [hari kerja]
L_beli     = lead time terlama di antara bahan yang kebutuhannya melebihi stok (0 bila semua cukup)
L_total    = L_beli + L_produksi

Tanggal mulai produksi = awal periode penjualan mundur L_produksi hari kerja
Tanggal pesan bahan    = tanggal mulai produksi mundur L_beli hari kalender
```

Kapasitas dipakai bersama. Saat menghitung target produksi satu produk, beban di setiap tahap ikut menjumlahkan prakiraan produk lain untuk periode yang sama.

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

## 10. Risiko

| Risiko | Penanganan |
|---|---|
| Rumus di bab metode skripsi berubah | Ditulis sebagai revisi berdasarkan wawancara proses produksi, seperti revisi rumus simulasi di docs/01 bab 7.5 |
| Semua angka turunan berubah (safety stock, target, simulasi) | Hitung ulang dan perbarui user guide (T10, T12) |
| Excel final tim (Python) masih dalam pcs | MAPE tetap sama. RMSE dan MAE di sistem menjadi 1/12 angka Excel karena satuannya dz |
| Rentang kapasitas (720 sampai 840) | Dipakai nilai tengah 780 dan bisa diubah di menu Tahapan Produksi |

## 11. Catatan untuk Skripsi

Revisi ini mengubah rumus waktu tunggu produksi di docs/01 bab 4.2. Alasannya: rumus lama mengasumsikan setiap tahap harus menyelesaikan seluruh target sebelum tahap berikutnya mulai, padahal di pabrik tahapan berjalan sambung-menyambung dan cetak kepala berjalan bersamaan dengan pembuatan handle. Data kapasitas dan urutan kerja diperoleh dari wawancara dengan pihak produksi pada 14 sampai 15 Oktober 2026.
