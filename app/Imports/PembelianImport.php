<?php

namespace App\Imports;

use App\Models\Barang;
use App\Models\Pembelian;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Import order pembelian dari Excel.
 *
 * Bentuk berkas: SATU BARIS = SATU BARANG pada sebuah order. Beberapa baris
 * dengan `no_pembelian` yang sama otomatis digabung menjadi satu order.
 *
 *   tanggal | no_pembelian | kode_supplier | kode_barang | jumlah | harga_satuan
 *
 * Keputusan penting: order hasil import berstatus **dipesan**, PERSIS seperti
 * order yang dibuat manual lewat form — bukan jalan pintas historis seperti
 * PenjualanImport. Alasannya, tidak ada kebutuhan mengisi data pembelian masa
 * lalu untuk ARIMA (yang jadi bahan ARIMA adalah data penjualan). Import ini
 * murni mempercepat entri banyak order sekaligus dari catatan/backlog.
 *
 * Karena statusnya tetap "dipesan", stok SAMA SEKALI tidak tersentuh oleh
 * import ini — sama seperti order manual, stok baru bergerak nanti saat staf
 * memproses penerimaan lewat PenerimaanController.
 *
 * Satu order hanya boleh untuk satu supplier (aturan yang sama seperti form
 * manual), jadi baris-baris dengan `no_pembelian` sama tapi `kode_supplier`
 * berbeda ditolak.
 *
 * Seluruh proses berjalan dalam satu transaksi: bila ada satu baris yang tidak
 * sah, tidak ada satu pun order yang tersimpan setengah jalan.
 */
class PembelianImport implements ToCollection, WithHeadingRow
{
    /** @var list<string> */
    public array $galat = [];

    public int $jumlahOrder = 0;

    public int $jumlahBaris = 0;

    /** @var list<string> */
    public array $dilewati = [];

    public function collection(Collection $baris): void
    {
        if ($baris->isEmpty()) {
            $this->galat[] = 'Berkas tidak berisi satu baris data pun.';

            return;
        }

        $kolomWajib = ['tanggal', 'no_pembelian', 'kode_supplier', 'kode_barang', 'jumlah', 'harga_satuan'];
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

            $noPembelian = trim((string) ($data['no_pembelian'] ?? ''));
            $kodeSupplier = strtoupper(trim((string) ($data['kode_supplier'] ?? '')));
            $kodeBarang = strtoupper(trim((string) ($data['kode_barang'] ?? '')));
            $jumlah = (int) ($data['jumlah'] ?? 0);
            $harga = (float) ($data['harga_satuan'] ?? 0);

            if ($noPembelian === '') {
                $this->galat[] = "Baris {$nomorBaris}: no_pembelian kosong.";

                continue;
            }

            $tanggal = $this->bacaTanggal($data['tanggal'] ?? null);

            if ($tanggal === null) {
                $this->galat[] = "Baris {$nomorBaris}: tanggal '{$data['tanggal']}' tidak dapat dibaca.";

                continue;
            }

            if ($tanggal->isAfter(Carbon::today())) {
                $this->galat[] = "Baris {$nomorBaris}: tanggal pembelian tidak boleh di masa depan.";

                continue;
            }

            $supplier = Supplier::where('kode_supplier', $kodeSupplier)->first();

            if (! $supplier) {
                $this->galat[] = "Baris {$nomorBaris}: kode supplier '{$kodeSupplier}' tidak terdaftar di master supplier.";

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

            if (isset($terkumpul[$noPembelian]) && $terkumpul[$noPembelian]['supplier_id'] !== $supplier->id) {
                $this->galat[] = "Baris {$nomorBaris}: order {$noPembelian} sudah memakai supplier lain pada baris sebelumnya. ".
                    'Satu order hanya boleh untuk satu supplier.';

                continue;
            }

            $barangDipakai = collect($terkumpul[$noPembelian]['baris'] ?? [])->pluck('barang_id');

            if ($barangDipakai->contains($barang->id)) {
                $this->galat[] = "Baris {$nomorBaris}: barang '{$kodeBarang}' sudah ada pada order {$noPembelian}. ".
                    'Gabungkan jadi satu baris, jangan ditulis dua kali.';

                continue;
            }

            $terkumpul[$noPembelian]['tanggal'] = $tanggal;
            $terkumpul[$noPembelian]['supplier_id'] = $supplier->id;
            $terkumpul[$noPembelian]['baris'][] = [
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
                foreach ($terkumpul as $noPembelian => $order) {
                    // Nomor pembelian yang sudah ada dilewati, bukan ditimpa —
                    // supaya import ulang tidak menggandakan order.
                    if (Pembelian::where('no_pembelian', $noPembelian)->exists()) {
                        $this->dilewati[] = $noPembelian;

                        continue;
                    }

                    $pembelian = Pembelian::create([
                        'no_pembelian' => $noPembelian,
                        'tanggal_pembelian' => $order['tanggal'],
                        'supplier_id' => $order['supplier_id'],
                        'user_id' => auth()->id(),
                        'status' => 'dipesan',
                        'sumber_data' => 'import',
                        'total_harga' => collect($order['baris'])->sum('subtotal'),
                    ]);

                    $pembelian->detail()->createMany($order['baris']);

                    $this->jumlahOrder++;
                }
            });
        } catch (Throwable $e) {
            $this->galat[] = 'Gagal menyimpan: '.$e->getMessage();
            $this->jumlahOrder = 0;
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
