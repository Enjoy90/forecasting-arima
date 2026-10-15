<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\TahapanProduksiRequest;
use App\Models\Barang;
use App\Models\Bom;
use App\Models\LogAktivitas;
use App\Models\TahapanProduksi;
use App\Services\Produksi\BomExploder;
use App\Services\Produksi\LeadTimeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * CRUD master tahapan produksi (5 tahapan pembuatan sekop).
 *
 * Halaman ini terlihat sederhana, tetapi isinya adalah bahan mentah
 * perhitungan waktu tunggu operasional pabrik (docs/01 §4.2, docs/07).
 * Ringkasannya dihitung dengan LeadTimeCalculator yang sama dengan menu
 * Peramalan, memakai resep produk jadi pertama sebagai acuan: lama produksi
 * dari nol dan tahap penghambat (kapasitas terkecil setelah disetarakan ke
 * barang jadi), supaya salah ketik angka langsung kelihatan.
 *
 * Catatan penghapusan:
 * bom.tahapan_id dan produksi.tahapan_id memakai restrictOnDelete, jadi
 * database sendiri akan menolak. Penolakan itu ditangkap lebih dulu di sini
 * agar pengguna menerima pesan yang bisa dimengerti, bukan error SQL.
 */
class TahapanProduksiController extends Controller
{
    private const MODUL = 'Master Tahapan Produksi';

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $status = $request->query('status', 'semua');

        $tahapan = TahapanProduksi::query()
            ->withCount(['bom', 'produksi'])
            ->when($cari !== '', function ($query) use ($cari) {
                $query->where(function ($sub) use ($cari) {
                    $sub->where('kode_tahapan', 'like', "%{$cari}%")
                        ->orWhere('nama_tahapan', 'like', "%{$cari}%");
                });
            })
            ->when($status === 'aktif', fn ($query) => $query->where('is_aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_aktif', false))
            ->orderBy('urutan')
            ->paginate(15)
            ->withQueryString();

        // Satuan kapasitas tiap tahapan = satuan barang hasil resepnya (pcs atau dz).
        $satuanTahapan = Bom::with('barang:id,satuan')->get()
            ->groupBy('tahapan_id')
            ->map(fn ($daftar) => $daftar->first()->barang?->satuan);

        return view('master.tahapan-produksi.index', [
            'tahapan' => $tahapan,
            'cari' => $cari,
            'status' => $status,
            'satuanTahapan' => $satuanTahapan,
        ] + $this->ringkasan());
    }

    /**
     * Lama produksi dari nol dan tahap penghambat, dihitung dari resep produk
     * jadi pertama yang punya BOM aktif.
     *
     * @return array<string, mixed>
     */
    private function ringkasan(): array
    {
        $acuan = Barang::diramalkan()->aktif()->orderBy('kode_barang')->get()
            ->first(fn (Barang $b) => $b->bomAktif() !== null);

        if ($acuan === null) {
            return ['acuan' => null, 'lamaDariNol' => null, 'penghambat' => null];
        }

        try {
            $lamaDariNol = (new LeadTimeCalculator())->hitung($acuan, 1, null, [], false)['lead_time_produksi_hari'];
            $perUnit = collect((new BomExploder())->ledakkan($acuan, 1)['tahapan'])->pluck('jumlah', 'tahapan_id');
        } catch (RuntimeException) {
            return ['acuan' => $acuan, 'lamaDariNol' => null, 'penghambat' => null];
        }

        // Kapasitas disetarakan ke barang jadi: 780 kepala per hari dibagi kebutuhan
        // kepala per 1 dz, supaya tahap bersatuan pcs dan dz bisa dibandingkan.
        $penghambat = TahapanProduksi::where('is_aktif', true)
            ->whereIn('id', $perUnit->keys())
            ->where('kapasitas_per_hari', '>', 0)
            ->get()
            ->map(fn (TahapanProduksi $t) => [
                'tahapan' => $t,
                'setara' => (float) $t->kapasitas_per_hari / (float) $perUnit[$t->id],
            ])
            ->sortBy('setara')
            ->first();

        return ['acuan' => $acuan, 'lamaDariNol' => $lamaDariNol, 'penghambat' => $penghambat];
    }

    public function create(): View
    {
        return view('master.tahapan-produksi.create', [
            'tahapan' => new TahapanProduksi([
                'waktu_proses_hari' => 1,
                'kapasitas_per_hari' => 0,
                'is_aktif' => true,
            ]),
            'kodeUsulan' => $this->kodeBerikutnya(),
            'urutanUsulan' => $this->urutanBerikutnya(),
        ]);
    }

    public function store(TahapanProduksiRequest $request): RedirectResponse
    {
        $tahapan = TahapanProduksi::create($request->validated());

        LogAktivitas::catat(self::MODUL, "Menambah tahapan {$tahapan->kode_tahapan} - {$tahapan->nama_tahapan}");

        return redirect()
            ->route('master.tahapan-produksi.index')
            ->with('sukses', "Tahapan {$tahapan->nama_tahapan} berhasil ditambahkan.");
    }

    public function show(TahapanProduksi $tahapanProduksi): RedirectResponse
    {
        // Tidak ada halaman detail terpisah; data sudah lengkap di tabel daftar.
        return redirect()->route('master.tahapan-produksi.edit', $tahapanProduksi);
    }

    public function edit(TahapanProduksi $tahapanProduksi): View
    {
        return view('master.tahapan-produksi.edit', ['tahapan' => $tahapanProduksi]);
    }

    public function update(TahapanProduksiRequest $request, TahapanProduksi $tahapanProduksi): RedirectResponse
    {
        $waktuLama = (float) $tahapanProduksi->waktu_proses_hari;

        $tahapanProduksi->update($request->validated());

        $waktuBaru = (float) $tahapanProduksi->waktu_proses_hari;
        $catatan = "Mengubah tahapan {$tahapanProduksi->kode_tahapan} - {$tahapanProduksi->nama_tahapan}";

        // Perubahan waktu proses menggeser hasil perhitungan waktu tunggu,
        // jadi dicatat khusus agar bisa ditelusuri bila hasil ramalan berubah.
        if ($waktuLama !== $waktuBaru) {
            $catatan .= " (waktu proses {$waktuLama} -> {$waktuBaru} hari)";
        }

        LogAktivitas::catat(self::MODUL, $catatan);

        return redirect()
            ->route('master.tahapan-produksi.index')
            ->with('sukses', "Tahapan {$tahapanProduksi->nama_tahapan} berhasil diperbarui.");
    }

    public function destroy(TahapanProduksi $tahapanProduksi): RedirectResponse
    {
        $jumlahBom = $tahapanProduksi->bom()->count();
        $jumlahProduksi = $tahapanProduksi->produksi()->count();

        if ($jumlahBom > 0 || $jumlahProduksi > 0) {
            $dipakai = [];

            if ($jumlahBom > 0) {
                $dipakai[] = "{$jumlahBom} BOM";
            }

            if ($jumlahProduksi > 0) {
                $dipakai[] = "{$jumlahProduksi} perintah produksi";
            }

            return redirect()
                ->route('master.tahapan-produksi.index')
                ->with('gagal', "Tahapan {$tahapanProduksi->nama_tahapan} tidak dapat dihapus karena masih terpakai pada ".
                    implode(' dan ', $dipakai).'. Nonaktifkan tahapan ini lewat tombol Ubah bila sudah tidak dipakai lagi.');
        }

        $nama = $tahapanProduksi->nama_tahapan;
        $kode = $tahapanProduksi->kode_tahapan;

        $tahapanProduksi->delete();

        LogAktivitas::catat(self::MODUL, "Menghapus tahapan {$kode} - {$nama}");

        return redirect()
            ->route('master.tahapan-produksi.index')
            ->with('sukses', "Tahapan {$nama} berhasil dihapus.");
    }

    /**
     * Usulan kode berikutnya mengikuti pola seeder: TP-01, TP-02, ...
     */
    private function kodeBerikutnya(): string
    {
        $terakhir = TahapanProduksi::query()
            ->where('kode_tahapan', 'like', 'TP-%')
            ->orderByDesc('kode_tahapan')
            ->value('kode_tahapan');

        $urutan = $terakhir ? ((int) substr($terakhir, 3)) + 1 : 1;

        return 'TP-'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Urutan berikutnya = urutan terbesar + 1, supaya tidak bentrok
     * dengan aturan unik pada TahapanProduksiRequest.
     */
    private function urutanBerikutnya(): int
    {
        return ((int) TahapanProduksi::max('urutan')) + 1;
    }
}
