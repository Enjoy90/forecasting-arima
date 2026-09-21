<?php

namespace App\Http\Controllers\Simulasi;

use App\Http\Controllers\Controller;
use App\Models\Simulasi;
use Illuminate\View\View;

/**
 * Perbandingan Skenario: ringkasan berdampingan kebijakan perusahaan vs
 * rekomendasi sistem (docs/01 §7.3-§7.4) beserta kesimpulan otomatis --
 * bukti utama yang ditunjukkan ke penguji.
 */
class PerbandinganController extends Controller
{
    public function show(Simulasi $simulasi): View
    {
        $simulasi->load('barang');

        return view('simulasi.perbandingan', ['simulasi' => $simulasi]);
    }
}
