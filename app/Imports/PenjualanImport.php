<?php

namespace App\Imports;

use App\Models\Barang;
use App\Models\Pelanggan;
use App\Models\Penjualan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Import data penjualan dari Excel.
 *
 * Bentuk berkas: SATU BARIS = SATU BARANG pada sebuah faktur. Beberapa baris
 * dengan `no_faktur` yang sama otomatis digabung menjadi satu faktur.
 *
 *   tanggal | no_faktur | nama_pelanggan | kode_barang | jumlah | harga_satuan
 *
 * Keputusan penting: import TIDAK mencatat mutasi stok.
 *
 * Alasannya, jalan masuk ini dipakai untuk memasukkan penjualan MASA LALU
 * perusahaan (36 bulan ke belakang) sebagai bahan deret waktu ARIMA. Barangnya
 * sudah lama keluar gudang bertahun-tahun lalu; mengurangi stok hari ini dengan
 * angka penjualan tiga tahun lalu justru akan merusak stok berjalan. Penjualan
 * berjalan tetap dicatat lewat form faktur, yang memang mengurangi stok.
 *
 * Seluruh proses berjalan dalam satu transaksi: bila ada satu baris yang tidak
 * sah, tidak ada satu pun faktur yang tersimpan setengah jalan.
 */
class PenjualanImport implements ToCollection, WithHeadingRow
{
    /** @var list<string> */
    public array $galat = [];

    public int $jumlahFaktur = 0;

    public int $jumlahBaris = 0;

    /** @var list<string> */
    public array $dilewati = [];

    public function collection(Collection $baris): void
    {
        if ($baris->isEmpty()) {
            $this->galat[] = 'Berkas tidak berisi satu baris data pun.';

            return;
        }

        $kolomWajib = ['tanggal', 'no_faktur', 'kode_barang', 'jumlah', 'harga_satuan'];
        $kolomAda = array_keys($baris->first()->toArray());

        foreach ($kolomWajib as $kolom) {
            if (! in_array($kolom, $kolomAda, true)) {
                $this->galat[] = "Kolom '{$kolom}' tidak ditemukan pada berkas. ".
                    'Pakai berkas contoh agar susunan kolomnya tepat.';
            }
        }

        if ($this->galat !== []) {
            return;
        }

        // Dikumpulkan lebih dulu supaya seluruh berkas bisa diperiksa sebelum
        // satu baris pun ditulis ke database.
        $terkumpul = [];

        foreach ($baris as $i => $row) {
            $nomorBaris = $i + 2; // baris 1 adalah judul kolom
            $data = $row->toArray();

            if ($this->barisKosong($data)) {
                continue;
            }

            $noFaktur = trim((string) ($data['no_faktur'] ?? ''));
            $kodeBarang = strtoupper(trim((string) ($data['kode_barang'] ?? '')));
            $jumlah = (int) ($data['jumlah'] ?? 0);
            $harga = (float) ($data['harga_satuan'] ?? 0);

            if ($noFaktur === '') {
                $this->galat[] = "Baris {$nomorBaris}: no_faktur kosong.";

                continue;
            }

            $tanggal = $this->bacaTanggal($data['tanggal'] ?? null);

            if ($tanggal === null) {
                $this->galat[] = "Baris {$nomorBaris}: tanggal '{$data['tanggal']}' tidak dapat dibaca.";

                continue;
            }

            $barang = Barang::where('kode_barang', $kodeBarang)->first();

            if (! $barang) {
                $this->galat[] = "Baris {$nomorBaris}: kode barang '{$kodeBarang}' tidak terdaftar di master barang.";

                continue;
            }

            if ($jumlah < 1) {
                $this->galat[] = "Baris {$nomorBaris}: jumlah harus minimal 1.";

                continue;
            }

            if ($harga < 0) {
                $this->galat[] = "Baris {$nomorBaris}: harga satuan tidak boleh negatif.";

                continue;
            }

            $terkumpul[$noFaktur]['tanggal'] = $tanggal;
            $terkumpul[$noFaktur]['pelanggan'] = trim((string) ($data['nama_pelanggan'] ?? ''));
            $terkumpul[$noFaktur]['baris'][] = [
                'barang_id' => $barang->id,
                'jumlah' => $jumlah,
                'harga_satuan' => $harga,
                'subtotal' => $jumlah * $harga,
            ];

            $this->jumlahBaris++;
        }

        if ($this->galat !== []) {
            return;
        }

        if ($terkumpul === []) {
            $this->galat[] = 'Tidak ada baris yang dapat diproses dari berkas ini.';

            return;
        }

        try {
            DB::transaction(function () use ($terkumpul) {
                foreach ($terkumpul as $noFaktur => $faktur) {
                    // Nomor faktur yang sudah ada dilewati, bukan ditimpa —
                    // supaya import ulang tidak menggandakan data penjualan.
                    if (Penjualan::where('no_faktur', $noFaktur)->exists()) {
                        $this->dilewati[] = $noFaktur;

                        continue;
                    }

                    $pelanggan = $faktur['pelanggan'] !== ''
                        ? Pelanggan::where('nama_pelanggan', $faktur['pelanggan'])->first()
                        : null;

                    $penjualan = Penjualan::create([
                        'no_faktur' => $noFaktur,
                        'tanggal_penjualan' => $faktur['tanggal'],
                        'pelanggan_id' => $pelanggan?->id,
                        // Nama yang tidak cocok dengan master tetap disimpan apa
                        // adanya, agar tidak ada keterangan pembeli yang hilang.
                        'nama_pelanggan_manual' => $pelanggan ? null : ($faktur['pelanggan'] ?: null),
                        'user_id' => auth()->id(),
                        'sumber_data' => 'import',
                        'total_harga' => collect($faktur['baris'])->sum('subtotal'),
                    ]);

                    $penjualan->detail()->createMany($faktur['baris']);

                    $this->jumlahFaktur++;
                }
            });
        } catch (Throwable $e) {
            $this->galat[] = 'Gagal menyimpan: '.$e->getMessage();
            $this->jumlahFaktur = 0;
        }
    }

    public function berhasil(): bool
    {
        return $this->galat === [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function barisKosong(array $data): bool
    {
        foreach ($data as $nilai) {
            if ($nilai !== null && trim((string) $nilai) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Excel menyimpan tanggal sebagai angka seri; teks biasa juga diterima.
     */
    private function bacaTanggal(mixed $nilai): ?Carbon
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return null;
        }

        try {
            if (is_numeric($nilai)) {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $nilai)
                );
            }

            return Carbon::parse((string) $nilai);
        } catch (Throwable) {
            return null;
        }
    }
}
