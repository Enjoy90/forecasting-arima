<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Utama
|--------------------------------------------------------------------------
|
| JANGAN menambah route di file ini.
|
| Proyek ini dikerjakan dua orang. Agar tidak pernah terjadi konflik saat
| menggabungkan pekerjaan, route dipecah berdasarkan kepemilikan modul:
|
|   routes/operasional.php  -> Modul A (master data, pembelian, produksi,
|                              persediaan, penjualan, laporan operasional)
|   routes/analisis.php     -> Modul B (peramalan, waktu tunggu,
|                              target produksi, simulasi)
|
| Tambahkan route baru ke file modul masing-masing.
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Satu-satunya route non-profil di berkas ini, bawaan Breeze. Isinya dialihkan
// ke DashboardController (milik Modul A) tanpa menambah route baru.
Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Profil pengguna (bawaan Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/operasional.php';
require __DIR__.'/analisis.php';
require __DIR__.'/auth.php';
