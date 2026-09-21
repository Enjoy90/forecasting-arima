<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\PenggunaRequest;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manajemen pengguna sistem — khusus admin.
 *
 * Empat role menentukan apa yang boleh diakses seseorang: admin, produksi,
 * gudang, dan pimpinan (lihat docs/01-alur-kerja-sistem.md bagian 10).
 *
 * Dua penjagaan yang tidak boleh dilanggar:
 *   1. Admin tidak dapat menonaktifkan atau menghapus akunnya sendiri.
 *   2. Sistem harus selalu punya minimal satu admin aktif.
 *
 * Tanpa keduanya, sangat mungkin seluruh akses pengelolaan terkunci dan tidak
 * ada seorang pun yang bisa masuk untuk memperbaikinya.
 */
class PenggunaController extends Controller
{
    private const MODUL = 'Master Pengguna';

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $role = $request->query('role', 'semua');
        $status = $request->query('status', 'semua');

        $pengguna = User::query()
            ->when($cari !== '', function ($q) use ($cari) {
                $q->where(function ($sub) use ($cari) {
                    $sub->where('name', 'like', "%{$cari}%")
                        ->orWhere('email', 'like', "%{$cari}%");
                });
            })
            ->when(array_key_exists($role, PenggunaRequest::ROLE), fn ($q) => $q->where('role', $role))
            ->when($status === 'aktif', fn ($q) => $q->where('is_aktif', true))
            ->when($status === 'nonaktif', fn ($q) => $q->where('is_aktif', false))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.pengguna.index', [
            'pengguna' => $pengguna,
            'cari' => $cari,
            'role' => $role,
            'status' => $status,
            'daftarRole' => PenggunaRequest::ROLE,
            'jumlahAdminAktif' => User::where('role', User::ROLE_ADMIN)->where('is_aktif', true)->count(),
        ]);
    }

    public function create(): View
    {
        return view('master.pengguna.create', [
            'pengguna' => new User(['role' => User::ROLE_GUDANG, 'is_aktif' => true]),
            'daftarRole' => PenggunaRequest::ROLE,
        ]);
    }

    public function store(PenggunaRequest $request): RedirectResponse
    {
        $pengguna = User::create($request->validated());

        // Akun dibuat admin, jadi tidak melewati alur verifikasi email.
        // Ditandai terverifikasi agar pengguna dapat langsung masuk — tanpa ini
        // ia akan tertahan middleware 'verified' pada seluruh halaman.
        //
        // Disetel terpisah, bukan lewat User::create(), karena
        // `email_verified_at` tidak termasuk $fillable pada model User yang
        // sudah dibekukan sejak fase 0.
        $pengguna->forceFill(['email_verified_at' => now()])->save();

        LogAktivitas::catat(self::MODUL, "Menambah pengguna {$pengguna->name} ({$pengguna->role})");

        return redirect()
            ->route('master.pengguna.index')
            ->with('sukses', "Pengguna {$pengguna->name} berhasil ditambahkan.");
    }

    public function show(User $pengguna): RedirectResponse
    {
        return redirect()->route('master.pengguna.edit', $pengguna);
    }

    public function edit(User $pengguna): View
    {
        return view('master.pengguna.edit', [
            'pengguna' => $pengguna,
            'daftarRole' => PenggunaRequest::ROLE,
        ]);
    }

    public function update(PenggunaRequest $request, User $pengguna): RedirectResponse
    {
        $data = $request->validated();

        // Kata sandi kosong berarti tidak diubah.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $roleLama = $pengguna->role;
        $pengguna->update($data);

        $catatan = "Mengubah pengguna {$pengguna->name}";

        if ($roleLama !== $pengguna->role) {
            $catatan .= " (role {$roleLama} -> {$pengguna->role})";
        }

        LogAktivitas::catat(self::MODUL, $catatan);

        return redirect()
            ->route('master.pengguna.index')
            ->with('sukses', "Pengguna {$pengguna->name} berhasil diperbarui.");
    }

    public function destroy(User $pengguna): RedirectResponse
    {
        if ($pengguna->id === auth()->id()) {
            return redirect()
                ->route('master.pengguna.index')
                ->with('gagal', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        if ($pengguna->role === User::ROLE_ADMIN && $pengguna->is_aktif) {
            $adminLain = User::query()
                ->where('role', User::ROLE_ADMIN)
                ->where('is_aktif', true)
                ->whereKeyNot($pengguna->id)
                ->count();

            if ($adminLain === 0) {
                return redirect()
                    ->route('master.pengguna.index')
                    ->with('gagal', 'Ini satu-satunya admin aktif dan tidak dapat dihapus. Angkat admin lain lebih dulu.');
            }
        }

        // Jejak aktivitas sengaja tidak ikut dihapus: log_aktivitas.user_id
        // memakai nullOnDelete, jadi catatan pekerjaannya tetap ada meski
        // penggunanya sudah tidak terdaftar.
        $nama = $pengguna->name;
        $pengguna->delete();

        LogAktivitas::catat(self::MODUL, "Menghapus pengguna {$nama}");

        return redirect()
            ->route('master.pengguna.index')
            ->with('sukses', "Pengguna {$nama} berhasil dihapus.");
    }
}
