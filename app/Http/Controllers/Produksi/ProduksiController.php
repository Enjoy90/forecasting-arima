<?php

namespace App\Http\Controllers\Produksi;

use App\Exceptions\BahanTidakCukupException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produksi\ProduksiRequest;
use App\Http\Requests\Produksi\RealisasiProduksiRequest;
use App\Models\Bom;
use App\Models\LogAktivitas;
use App\Models\Produksi;
use App\Models\TahapanProduksi;
use App\Services\Produksi\ProduksiProcessor;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Perintah produksi — SATU controller untuk lima menu tahapan.
 *
 * Kepala, Handle, Coating, Perakitan, dan Pengemasan punya alur yang sama
 * persis (pilih BOM, catat bahan, catat hasil) dan hanya berbeda `tahapan_id`.
 * Membuat lima controller terpisah berarti menyalin kode yang sama lima kali,
 * dan setiap perbaikan harus dikerjakan lima kali pula.
 *
 * Tahapan ditentukan lewat parameter route `{tahapan}`, yang diikat ke
 * `kode_tahapan` (mis. TP-01) di routes/operasional.php. Menambah atau mengubah
 * tahapan cukup lewat master Tahapan Produksi — tidak perlu menyentuh kode.
 *
 * Perpindahan status:
 *   draft  -> proses   (mulai)      : rencana bahan sudah disalin dari BOM
 *   proses -> selesai  (selesaikan) : mutasi stok dicatat di sini
 *   draft|proses -> batal           : tidak menyentuh stok sama sekali
 */
class ProduksiController extends Controller
{
    private const MODUL = 'Produksi';

    /** @var array<string, string> */
    public const STATUS = [
        Produksi::STATUS_DRAFT => 'Draft',
        Produksi::STATUS_PROSES => 'Proses',
        Produksi::STATUS_SELESAI => 'Selesai',
        Produksi::STATUS_BATAL => 'Batal',
    ];

    public function __construct(private readonly ProduksiProcessor $processor)
    {
    }

    public function index(Request $request, TahapanProduksi $tahapan): View
    {
        $status = $request->query('status', 'semua');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        // Dipanggil lewat query(), bukan Produksi::tahapan(...), karena nama
        // scopeTahapan bentrok dengan relasi tahapan() pada model. Memanggilnya
        // statis akan diarahkan PHP ke relasinya dan menghasilkan galat.
        $produksi = Produksi::query()
            ->tahapan($tahapan->id)
            ->with(['barangOutput:id,kode_barang,nama_barang,satuan', 'bom:id,kode_bom'])
            ->withCount('bahan')
            ->when(array_key_exists($status, self::STATUS), fn ($q) => $q->where('status', $status))
            ->when($dari, fn ($q) => $q->whereDate('tanggal_produksi', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('tanggal_produksi', '<=', $sampai))
            ->orderByDesc('tanggal_produksi')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('produksi.perintah.index', [
            'tahapan' => $tahapan,
            'produksi' => $produksi,
            'status' => $status,
            'dari' => $dari,
            'sampai' => $sampai,
            'daftarStatus' => self::STATUS,
            'jumlahBerjalan' => Produksi::query()->tahapan($tahapan->id)
                ->where('status', Produksi::STATUS_PROSES)->count(),
        ]);
    }

    public function create(TahapanProduksi $tahapan): View
    {
        return view('produksi.perintah.create', [
            'tahapan' => $tahapan,
            'produksi' => new Produksi(['tanggal_produksi' => now(), 'jumlah_target' => 1]),
            'daftarBom' => $this->bomTahapan($tahapan),
        ]);
    }

    public function store(ProduksiRequest $request, TahapanProduksi $tahapan): RedirectResponse
    {
        $bom = Bom::findOrFail($request->integer('bom_id'));
        $target = (float) $request->input('jumlah_target');

        $produksi = DB::transaction(function () use ($request, $tahapan, $bom, $target) {
            $tanggal = Carbon::parse($request->input('tanggal_produksi'));

            $produksi = Produksi::create([
                'no_produksi' => $this->nomorBerikutnya($tahapan, $tanggal),
                'tanggal_produksi' => $tanggal,
                'tahapan_id' => $tahapan->id,
                'bom_id' => $bom->id,
                // Barang hasil diambil dari BOM, bukan dari isian pengguna,
                // supaya perintah tidak bisa menghasilkan barang yang tidak
                // sesuai resepnya.
                'barang_output_id' => $bom->barang_id,
                'jumlah_target' => $target,
                'status' => Produksi::STATUS_DRAFT,
                'user_id' => auth()->id(),
                'keterangan' => $request->input('keterangan'),
            ]);

            $this->processor->salinRencanaBahan($produksi, $bom, $target);

            return $produksi;
        });

        LogAktivitas::catat(self::MODUL, "Membuat perintah {$produksi->no_produksi} ({$tahapan->nama_tahapan}), target {$target}");

        return redirect()
            ->route('produksi.perintah.show', [$tahapan->kode_tahapan, $produksi])
            ->with('sukses', "Perintah {$produksi->no_produksi} dibuat. Rencana bahan sudah disalin dari resep {$bom->kode_bom}.");
    }

    public function show(TahapanProduksi $tahapan, Produksi $perintah): View
    {
        $this->pastikanMilikTahapan($tahapan, $perintah);

        $perintah->load(['bom', 'barangOutput', 'user', 'bahan.barang', 'tahapan']);

        return view('produksi.perintah.show', [
            'tahapan' => $tahapan,
            'produksi' => $perintah,
            'daftarStatus' => self::STATUS,
            // Kekurangan ditampilkan sejak status proses supaya staf sempat
            // memesan bahan sebelum mencoba menyelesaikan perintah.
            'kekurangan' => $perintah->status === Produksi::STATUS_PROSES
                ? $this->processor->kekuranganBahan($perintah)
                : [],
        ]);
    }

    public function edit(TahapanProduksi $tahapan, Produksi $perintah): View|RedirectResponse
    {
        $this->pastikanMilikTahapan($tahapan, $perintah);

        if ($perintah->status !== Produksi::STATUS_DRAFT) {
            return $this->tolakKarenaStatus($tahapan, $perintah, 'diubah');
        }

        return view('produksi.perintah.edit', [
            'tahapan' => $tahapan,
            'produksi' => $perintah,
            'daftarBom' => $this->bomTahapan($tahapan),
        ]);
    }

    public function update(ProduksiRequest $request, TahapanProduksi $tahapan, Produksi $perintah): RedirectResponse
    {
        $this->pastikanMilikTahapan($tahapan, $perintah);

        if ($perintah->status !== Produksi::STATUS_DRAFT) {
            return $this->tolakKarenaStatus($tahapan, $perintah, 'diubah');
        }

        $bom = Bom::findOrFail($request->integer('bom_id'));
        $target = (float) $request->input('jumlah_target');

        DB::transaction(function () use ($request, $perintah, $bom, $target) {
            $perintah->update([
                'tanggal_produksi' => Carbon::parse($request->input('tanggal_produksi')),
                'bom_id' => $bom->id,
                'barang_output_id' => $bom->barang_id,
                'jumlah_target' => $target,
                'keterangan' => $request->input('keterangan'),
            ]);

            // Rencana bahan disalin ulang karena target atau resepnya berubah.
            $this->processor->salinRencanaBahan($perintah, $bom, $target);
        });

        LogAktivitas::catat(self::MODUL, "Mengubah perintah {$perintah->no_produksi}");

        return redirect()
            ->route('produksi.perintah.show', [$tahapan->kode_tahapan, $perintah])
            ->with('sukses', "Perintah {$perintah->no_produksi} diperbarui dan rencana bahannya dihitung ulang.");
    }

    public function destroy(TahapanProduksi $tahapan, Produksi $perintah): RedirectResponse
    {
        $this->pastikanMilikTahapan($tahapan, $perintah);

        if ($perintah->status !== Produksi::STATUS_DRAFT) {
            return $this->tolakKarenaStatus($tahapan, $perintah, 'dihapus');
        }

        $nomor = $perintah->no_produksi;

        // detail_produksi_bahan memakai cascadeOnDelete.
        $perintah->delete();

        LogAktivitas::catat(self::MODUL, "Menghapus perintah {$nomor}");

        return redirect()
            ->route('produksi.perintah.index', $tahapan->kode_tahapan)
            ->with('sukses', "Perintah {$nomor} dihapus.");
    }

    /**
     * draft -> proses. Pekerjaan dimulai di lantai produksi.
     */
    public function mulai(TahapanProduksi $tahapan, Produksi $perintah): RedirectResponse
    {
        $this->pastikanMilikTahapan($tahapan, $perintah);

        if ($perintah->status !== Produksi::STATUS_DRAFT) {
            return $this->tolakKarenaStatus($tahapan, $perintah, 'dimulai');
        }

        $perintah->update(['status' => Produksi::STATUS_PROSES]);

        LogAktivitas::catat(self::MODUL, "Memulai perintah {$perintah->no_produksi}");

        return redirect()
            ->route('produksi.perintah.show', [$tahapan->kode_tahapan, $perintah])
            ->with('sukses', "Perintah {$perintah->no_produksi} dimulai. Catat realisasinya setelah pekerjaan berjalan.");
    }

    /**
     * Menyimpan realisasi tanpa menyelesaikan perintah, supaya staf dapat
     * mencicil pencatatan selama pekerjaan masih berjalan.
     */
    public function realisasi(RealisasiProduksiRequest $request, TahapanProduksi $tahapan, Produksi $perintah): RedirectResponse
    {
        $this->pastikanMilikTahapan($tahapan, $perintah);

        if ($perintah->status !== Produksi::STATUS_PROSES) {
            return $this->tolakKarenaStatus($tahapan, $perintah, 'dicatat realisasinya');
        }

        DB::transaction(function () use ($request, $perintah) {
            $perintah->update([
                'jumlah_hasil' => (float) $request->input('jumlah_hasil'),
                'jumlah_gagal' => (float) $request->input('jumlah_gagal'),
                'keterangan' => $request->input('keterangan'),
            ]);

            foreach ($request->input('bahan') as $id => $isian) {
                $perintah->bahan()
                    ->whereKey((int) $id)
                    ->update([
                        'jumlah_pakai' => (float) $isian['jumlah_pakai'],
                        'keterangan' => $isian['keterangan'] ?? null,
                    ]);
            }
        });

        LogAktivitas::catat(self::MODUL, "Mencatat realisasi perintah {$perintah->no_produksi}");

        return redirect()
            ->route('produksi.perintah.show', [$tahapan->kode_tahapan, $perintah])
            ->with('sukses', 'Realisasi tersimpan. Perintah belum diselesaikan, jadi stok belum bergerak.');
    }

    /**
     * proses -> selesai. Di sinilah stok benar-benar bergerak.
     */
    public function selesaikan(TahapanProduksi $tahapan, Produksi $perintah): RedirectResponse
    {
        $this->pastikanMilikTahapan($tahapan, $perintah);

        try {
            $this->processor->selesaikan($perintah);
        } catch (BahanTidakCukupException|RuntimeException $e) {
            return redirect()
                ->route('produksi.perintah.show', [$tahapan->kode_tahapan, $perintah])
                ->with('gagal', $e->getMessage());
        }

        $perintah->refresh();

        LogAktivitas::catat(
            self::MODUL,
            "Menyelesaikan perintah {$perintah->no_produksi}: hasil {$perintah->jumlah_hasil}, gagal {$perintah->jumlah_gagal}"
        );

        return redirect()
            ->route('produksi.perintah.show', [$tahapan->kode_tahapan, $perintah])
            ->with('sukses', "Perintah {$perintah->no_produksi} selesai. Bahan sudah dikurangi dan {$perintah->jumlah_hasil} unit hasil masuk gudang.");
    }

    public function batal(TahapanProduksi $tahapan, Produksi $perintah): RedirectResponse
    {
        $this->pastikanMilikTahapan($tahapan, $perintah);

        if (! in_array($perintah->status, [Produksi::STATUS_DRAFT, Produksi::STATUS_PROSES], true)) {
            return $this->tolakKarenaStatus($tahapan, $perintah, 'dibatalkan');
        }

        $perintah->update(['status' => Produksi::STATUS_BATAL]);

        LogAktivitas::catat(self::MODUL, "Membatalkan perintah {$perintah->no_produksi}");

        return redirect()
            ->route('produksi.perintah.show', [$tahapan->kode_tahapan, $perintah])
            ->with('sukses', "Perintah {$perintah->no_produksi} dibatalkan. Stok tidak tersentuh.");
    }

    /**
     * {tahapan} dan {perintah} adalah dua route-model-binding yang berdiri
     * sendiri-sendiri, jadi URL bisa saja mencampur kode tahapan yang valid
     * dengan id perintah milik tahapan lain (IDOR relasi, bukan cuma "id
     * tidak ada"). 404 di sini, bukan diam-diam memproses kombinasi yang
     * tidak nyambung.
     */
    private function pastikanMilikTahapan(TahapanProduksi $tahapan, Produksi $perintah): void
    {
        abort_unless($perintah->tahapan_id === $tahapan->id, 404);
    }

    private function tolakKarenaStatus(TahapanProduksi $tahapan, Produksi $perintah, string $tindakan): RedirectResponse
    {
        $status = self::STATUS[$perintah->status] ?? $perintah->status;

        return redirect()
            ->route('produksi.perintah.show', [$tahapan->kode_tahapan, $perintah])
            ->with('gagal', "Perintah {$perintah->no_produksi} berstatus {$status} sehingga tidak dapat {$tindakan}.");
    }

    /**
     * Resep aktif milik tahapan ini saja.
     */
    private function bomTahapan(TahapanProduksi $tahapan)
    {
        return Bom::query()
            ->where('tahapan_id', $tahapan->id)
            ->where('is_aktif', true)
            ->with('barang:id,kode_barang,nama_barang,satuan')
            ->withCount('detail')
            ->orderBy('kode_bom')
            ->get();
    }

    /**
     * Nomor dokumen berpola PRD-TP01-YYYYMM-001, memuat kode tahapan agar
     * penomoran tiap tahapan berjalan sendiri-sendiri.
     */
    private function nomorBerikutnya(TahapanProduksi $tahapan, CarbonInterface $tanggal): string
    {
        $kode = str_replace('-', '', $tahapan->kode_tahapan);
        $awalan = "PRD-{$kode}-".$tanggal->format('Ym').'-';

        $terakhir = Produksi::query()
            ->where('no_produksi', 'like', $awalan.'%')
            ->orderByDesc('no_produksi')
            ->value('no_produksi');

        $urutan = $terakhir ? ((int) substr($terakhir, strlen($awalan))) + 1 : 1;

        return $awalan.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
    }
}
