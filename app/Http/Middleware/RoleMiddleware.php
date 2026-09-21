<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pembatasan akses berdasarkan role pengguna.
 *
 * Pemakaian pada route:
 *   ->middleware('role:admin')
 *   ->middleware('role:admin,pimpinan')
 *
 * Role yang tersedia: admin, produksi, gudang, pimpinan
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_aktif) {
            abort(403, 'Akun tidak aktif.');
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }
}
