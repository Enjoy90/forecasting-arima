<?php

namespace App\Http\Controllers\Produksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Produksi\BomRequest;
use App\Models\Barang;
use App\Models\Bom;
use App\Models\LogAktivitas;
use App\Models\TahapanProduksi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRUD BOM (Bill of Material) — resep komposisi bahan per tahapan produksi.
 *
 * Satu BOM menjawab: "untuk menghasilkan sekian unit barang X pada tahapan Y,
 * bahan apa saja yang dibutuhkan dan berapa banyak". Resep inilah yang disalin
 * menjadi rencana pemakaian bahan saat perintah produksi dibuat, dan yang
 * diledakkan Modul B untuk menghitung kebutuhan bahan dari target produksi.
 *
 * Karena itu ketelitian angkanya berpengaruh jauh: salah menulis
 * `jumlah_kebutuhan` di sini akan salah pula rekomendasi pembelian bahannya.
 */
class BomController extends Controller
{
    private const MODUL = 'BOM';

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $tahapanId = $request->query('tahapan', 'semua');
        $status = $request->query('status', 'semua');

        $bom = Bom::query()
            ->with(['barang:id,kode_barang,nama_barang,satuan', 'tahapan:id,kode_tahapan,nama_tahapan,urutan'])
            ->withCount(['detail', 'produksi'])
            ->when($cari !== '', function ($q) use ($cari) {
                $q->where(function ($sub) use ($cari) {
                    $sub->where('kode_bom', 'like', "%{$cari}%")
                        ->orWhere('nama_bom', 'like', "%{$cari}%");
                });
            })
            ->when(is_numeric($tahapanId), fn ($q) => $q->where('tahapan_id', (int) $tahapanId))
            ->when($status === 'aktif', fn ($q) => $q->where('is_aktif', true))
            ->when($status === 'nonaktif', fn ($q) => $q->where('is_aktif', false))
            ->orderBy('kode_bom')
            ->paginate(15)
            ->withQueryString();

        return view('produksi.bom.index', [
            'bom' => $bom,
            'cari' => $cari,
            'tahapanId' => $tahapanId,
            'status' => $status,
            'daftarTahapan' => TahapanProduksi::orderBy('urutan')->get(['id', 'kode_tahapan', 'nama_tahapan']),
        ]);
    }

    public function create(): View
    {
        return view('produksi.bom.create', [
            'bom' => new Bom(['jumlah_output' => 1, 'is_aktif' => true]),
            'baris' => old('detail', [['barang_id' => '', 'jumlah_kebutuhan' => 1, 'persen_susut' => 0, 'keterangan' => '']]),
            'kodeUsulan' => $this->kodeBerikutnya(),
        ] + $this->pilihanForm());
    }

    public function store(BomRequest $request): RedirectResponse
    {
        $bom = DB::transaction(function () use ($request) {
            $bom = Bom::create($request->safe()->except('detail'));
            $this->simpanDetail($bom, $request->input('detail'));

            return $bom;
        });

        LogAktivitas::catat(self::MODUL, "Menambah BOM {$bom->kode_bom} - {$bom->nama_bom}");

        return redirect()
            ->route('produksi.bom.show', $bom)
            ->with('sukses', "BOM {$bom->nama_bom} berhasil dibuat.");
    }

    public function show(Bom $bom): View
    {
        $bom->load(['barang', 'tahapan', 'detail.barang']);
        $bom->loadCount('produksi');

        return view('produksi.bom.show', ['bom' => $bom]);
    }

    public function edit(Bom $bom): View
    {
        $bom->load('detail');

        return view('produksi.bom.edit', [
            'bom' => $bom,
            'baris' => old('detail', $bom->detail->map(fn ($d) => [
                'barang_id' => $d->barang_id,
                'jumlah_kebutuhan' => (float) $d->jumlah_kebutuhan,
                'persen_susut' => (float) $d->persen_susut,
                'keterangan' => $d->keterangan,
            ])->all()),
        ] + $this->pilihanForm());
    }

    public function update(BomRequest $request, Bom $bom): RedirectResponse
    {
        DB::transaction(function () use ($request, $bom) {
            $bom->update($request->safe()->except('detail'));

            // Komponen ditulis ulang. Aman karena perintah produksi menyalin
            // kebutuhan bahannya sendiri saat dibuat, sehingga perintah lama
            // tidak ikut berubah bila resepnya direvisi belakangan.
            $bom->detail()->delete();
            $this->simpanDetail($bom, $request->input('detail'));
        });

        LogAktivitas::catat(self::MODUL, "Mengubah BOM {$bom->kode_bom} - {$bom->nama_bom}");

        return redirect()
            ->route('produksi.bom.show', $bom)
            ->with('sukses', "BOM {$bom->nama_bom} berhasil diperbarui.");
    }

    public function destroy(Bom $bom): RedirectResponse
    {
        $jumlahProduksi = $bom->produksi()->count();

        if ($jumlahProduksi > 0) {
            return redirect()
                ->route('produksi.bom.index')
                ->with('gagal', "BOM {$bom->nama_bom} tidak dapat dihapus karena sudah dipakai pada {$jumlahProduksi} perintah produksi. ".
                    'Nonaktifkan resep ini lewat tombol Ubah bila sudah tidak dipakai lagi.');
        }

        $nama = $bom->nama_bom;
        $kode = $bom->kode_bom;

        // detail_bom memakai cascadeOnDelete.
        $bom->delete();

        LogAktivitas::catat(self::MODUL, "Menghapus BOM {$kode} - {$nama}");

        return redirect()
            ->route('produksi.bom.index')
            ->with('sukses', "BOM {$nama} berhasil dihapus.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $detail
     */
    private function simpanDetail(Bom $bom, array $detail): void
    {
        foreach ($detail as $baris) {
            $barang = Barang::find((int) $baris['barang_id']);

            $bom->detail()->create([
                'barang_id' => (int) $baris['barang_id'],
                'jumlah_kebutuhan' => (float) $baris['jumlah_kebutuhan'],
                // Satuan disalin dari master barang supaya tampilan resep tidak
                // bergantung pada ketelitian pengetikan pengguna.
                'satuan' => $barang?->satuan,
                'persen_susut' => (float) $baris['persen_susut'],
                'keterangan' => $baris['keterangan'] ?? null,
            ]);
        }
    }

    private function kodeBerikutnya(): string
    {
        $terakhir = Bom::query()
            ->where('kode_bom', 'like', 'BOM-%')
            ->orderByDesc('kode_bom')
            ->value('kode_bom');

        $urutan = $terakhir ? ((int) substr($terakhir, 4)) + 1 : 1;

        return 'BOM-'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function pilihanForm(): array
    {
        return [
            'daftarTahapan' => TahapanProduksi::where('is_aktif', true)
                ->orderBy('urutan')
                ->get(['id', 'kode_tahapan', 'nama_tahapan']),
            'daftarBarang' => Barang::aktif()
                ->orderBy('jenis_barang')
                ->orderBy('kode_barang')
                ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'jenis_barang']),
        ];
    }
}
