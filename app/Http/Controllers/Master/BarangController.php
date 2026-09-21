<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\BarangRequest;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LogAktivitas;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD master data barang — simpul pusat seluruh transaksi.
 *
 * Satu tabel menampung tiga jenis item sekaligus (bahan baku, setengah jadi,
 * barang jadi), dibedakan kolom `jenis_barang`.
 *
 * Dua hal yang membedakan controller ini dari master data lain:
 *
 * 1. `stok_tersedia` tidak pernah diisi dari form. Stok hanya boleh berubah
 *    lewat mutasi stok, supaya angka stok selalu punya jejak asal-usul.
 *    Barang baru selalu mulai dari 0; saldo awal dimasukkan lewat stok opname
 *    setelah modul persediaan jadi (Tahap 3 pada roadmap).
 *
 * 2. Penghapusan dijaga ketat. Beberapa foreign key ke tabel barang memakai
 *    cascadeOnDelete — termasuk mutasi_stok, data_time_series, peramalan,
 *    target_produksi, dan simulasi. Artinya menghapus satu barang jadi bisa
 *    ikut menghapus seluruh hasil peramalan dan simulasi miliknya tanpa
 *    peringatan apa pun dari database. Karena itu penghapusan hanya
 *    diperbolehkan untuk barang yang sama sekali belum tersentuh transaksi
 *    maupun analisis.
 */
class BarangController extends Controller
{
    private const MODUL = 'Master Barang';

    /**
     * Label tampilan untuk kolom enum `jenis_barang`.
     * Dipakai bersama oleh dropdown form, filter daftar, dan aturan validasi.
     *
     * @var array<string, string>
     */
    public const JENIS = [
        Barang::JENIS_BAHAN_BAKU => 'Bahan Baku',
        Barang::JENIS_SETENGAH_JADI => 'Setengah Jadi',
        Barang::JENIS_BARANG_JADI => 'Barang Jadi',
    ];

    /**
     * Relasi yang menandakan barang sudah dipakai. Dipakai untuk menghitung
     * kolom "Dipakai" pada daftar sekaligus menjadi dasar penjagaan hapus.
     *
     * @var list<string>
     */
    private const RELASI_PEMAKAIAN = [
        'bom',              // BOM yang menghasilkan barang ini (cascade!)
        'dipakaiDiBom',     // baris BOM tempat barang ini jadi komponen
        'detailPembelian',
        'detailPenjualan',
        'mutasiStok',       // cascade!
        'dataTimeSeries',   // cascade!
        'peramalan',        // cascade!
        'simulasi',         // cascade!
    ];

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $jenis = $request->query('jenis', 'semua');
        $kategoriId = $request->query('kategori', 'semua');
        $status = $request->query('status', 'semua');
        $hanyaMenipis = $request->boolean('menipis');

        $barang = Barang::query()
            ->with(['kategori', 'supplier'])
            ->withCount(self::RELASI_PEMAKAIAN)
            ->when($cari !== '', function ($query) use ($cari) {
                $query->where(function ($sub) use ($cari) {
                    $sub->where('kode_barang', 'like', "%{$cari}%")
                        ->orWhere('nama_barang', 'like', "%{$cari}%");
                });
            })
            ->when(array_key_exists($jenis, self::JENIS), fn ($query) => $query->where('jenis_barang', $jenis))
            ->when(is_numeric($kategoriId), fn ($query) => $query->where('kategori_id', (int) $kategoriId))
            ->when($status === 'aktif', fn ($query) => $query->where('is_aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_aktif', false))
            ->when($hanyaMenipis, fn ($query) => $query->whereColumn('stok_tersedia', '<=', 'stok_minimum'))
            ->orderBy('jenis_barang')
            ->orderBy('kode_barang')
            ->paginate(15)
            ->withQueryString();

        return view('master.barang.index', [
            'barang' => $barang,
            'cari' => $cari,
            'jenis' => $jenis,
            'kategoriId' => $kategoriId,
            'status' => $status,
            'hanyaMenipis' => $hanyaMenipis,
            'daftarJenis' => self::JENIS,
            'daftarKategori' => Kategori::orderBy('kode_kategori')->get(),
            'jumlahMenipis' => Barang::aktif()->whereColumn('stok_tersedia', '<=', 'stok_minimum')->count(),
        ]);
    }

    public function create(): View
    {
        return view('master.barang.create', [
            'barang' => new Barang([
                'jenis_barang' => Barang::JENIS_BAHAN_BAKU,
                'satuan' => 'Pcs',
                'harga_beli' => 0,
                'harga_jual' => 0,
                'stok_minimum' => 0,
                'lead_time_hari' => 7,
                'service_level' => 95,
                'is_diramalkan' => false,
                'is_aktif' => true,
            ]),
            'kodeUsulan' => $this->kodeBerikutnya(Barang::JENIS_BAHAN_BAKU),
        ] + $this->pilihanForm());
    }

    public function store(BarangRequest $request): RedirectResponse
    {
        // stok_tersedia sengaja tidak diambil dari input. Barang baru mulai
        // dari nol; pemasukan saldo awal lewat mutasi stok / stok opname.
        $barang = Barang::create($request->validated() + ['stok_tersedia' => 0]);

        LogAktivitas::catat(self::MODUL, "Menambah barang {$barang->kode_barang} - {$barang->nama_barang}");

        return redirect()
            ->route('master.barang.index')
            ->with('sukses', "Barang {$barang->nama_barang} berhasil ditambahkan. Stok awal 0 — masukkan lewat mutasi stok.");
    }

    public function show(Barang $barang): RedirectResponse
    {
        // Tidak ada halaman detail terpisah; data sudah lengkap di tabel daftar.
        return redirect()->route('master.barang.edit', $barang);
    }

    public function edit(Barang $barang): View
    {
        return view('master.barang.edit', ['barang' => $barang] + $this->pilihanForm());
    }

    public function update(BarangRequest $request, Barang $barang): RedirectResponse
    {
        $data = $request->validated();

        // Jaga-jaga bila ada kiriman stok_tersedia dari luar form resmi.
        unset($data['stok_tersedia']);

        $barang->update($data);

        LogAktivitas::catat(self::MODUL, "Mengubah barang {$barang->kode_barang} - {$barang->nama_barang}");

        return redirect()
            ->route('master.barang.index')
            ->with('sukses', "Barang {$barang->nama_barang} berhasil diperbarui.");
    }

    public function destroy(Barang $barang): RedirectResponse
    {
        $barang->loadCount(self::RELASI_PEMAKAIAN);

        $terpakai = $this->rincianPemakaian($barang);

        if ($terpakai !== []) {
            return redirect()
                ->route('master.barang.index')
                ->with('gagal', "Barang {$barang->nama_barang} tidak dapat dihapus karena masih terpakai pada ".
                    implode(', ', $terpakai).'. Nonaktifkan barang ini lewat tombol Ubah bila sudah tidak dipakai lagi.');
        }

        $nama = $barang->nama_barang;
        $kode = $barang->kode_barang;

        try {
            $barang->delete();
        } catch (QueryException $e) {
            // Jaring pengaman untuk relasi restrictOnDelete yang tidak punya
            // relasi Eloquent pada model Barang (produksi, detail produksi
            // bahan, kebutuhan bahan).
            return redirect()
                ->route('master.barang.index')
                ->with('gagal', "Barang {$nama} tidak dapat dihapus karena masih dipakai pada data produksi atau perencanaan bahan.");
        }

        LogAktivitas::catat(self::MODUL, "Menghapus barang {$kode} - {$nama}");

        return redirect()
            ->route('master.barang.index')
            ->with('sukses', "Barang {$nama} berhasil dihapus.");
    }

    /**
     * Rincian pemakaian barang dalam kalimat yang bisa dibaca pengguna.
     *
     * @return list<string>
     */
    private function rincianPemakaian(Barang $barang): array
    {
        $label = [
            'bom_count' => 'resep BOM',
            'dipakai_di_bom_count' => 'komponen BOM',
            'detail_pembelian_count' => 'transaksi pembelian',
            'detail_penjualan_count' => 'transaksi penjualan',
            'mutasi_stok_count' => 'mutasi stok',
            'data_time_series_count' => 'data time series',
            'peramalan_count' => 'hasil peramalan',
            'simulasi_count' => 'data simulasi',
        ];

        $rincian = [];

        foreach ($label as $kolom => $teks) {
            $jumlah = (int) ($barang->{$kolom} ?? 0);

            if ($jumlah > 0) {
                $rincian[] = "{$jumlah} {$teks}";
            }
        }

        return $rincian;
    }

    /**
     * Awalan kode mengikuti jenis barang, sesuai pola seeder:
     * BB- bahan baku, SJ- setengah jadi, BJ- barang jadi.
     *
     * @var array<string, string>
     */
    private const AWALAN_KODE = [
        Barang::JENIS_BAHAN_BAKU => 'BB',
        Barang::JENIS_SETENGAH_JADI => 'SJ',
        Barang::JENIS_BARANG_JADI => 'BJ',
    ];

    /**
     * Usulan kode berikutnya untuk satu jenis barang, misalnya BB-08.
     * Hanya nilai awal form; pengguna tetap boleh menggantinya.
     */
    private function kodeBerikutnya(string $jenis): string
    {
        $awalan = self::AWALAN_KODE[$jenis] ?? 'BRG';

        $terakhir = Barang::query()
            ->where('kode_barang', 'like', $awalan.'-%')
            ->orderByDesc('kode_barang')
            ->value('kode_barang');

        $urutan = $terakhir ? ((int) substr($terakhir, strlen($awalan) + 1)) + 1 : 1;

        return $awalan.'-'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Pilihan dropdown yang dipakai bersama oleh create dan edit.
     * Supplier nonaktif tetap ditampilkan agar barang lama tidak kehilangan
     * acuannya saat diedit, tetapi diberi penanda.
     *
     * @return array<string, mixed>
     */
    private function pilihanForm(): array
    {
        return [
            'daftarJenis' => self::JENIS,
            'daftarKategori' => Kategori::orderBy('kode_kategori')->get(),
            'daftarSupplier' => Supplier::orderBy('kode_supplier')->get(),
        ];
    }
}
