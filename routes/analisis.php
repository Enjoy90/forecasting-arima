<?php

/*
|--------------------------------------------------------------------------
| Route Modul B - Analisis, Peramalan & Simulasi
|--------------------------------------------------------------------------
|
| Pemilik file ini: pengerjaan Modul B. Modul A tidak menambah apa pun di sini.
|
| Cakupan : data historis, proses Box-Jenkins, waktu tunggu operasional,
|           target produksi, simulasi pengujian rencana stok
| Folder  : app/Http/Controllers/{Peramalan,Simulasi}
|
*/

use App\Http\Controllers\Laporan\LaporanSimulasiController;
use App\Http\Controllers\Peramalan\DataHistorisController;
use App\Http\Controllers\Peramalan\ForecastingController;
use App\Http\Controllers\Peramalan\TargetProduksiController;
use App\Http\Controllers\Peramalan\WaktuTungguController;
use App\Http\Controllers\Simulasi\PerbandinganController;
use App\Http\Controllers\Simulasi\SimulasiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    // -----------------------------------------------------------------
    // PERAMALAN (ARIMA)
    // -----------------------------------------------------------------
    Route::prefix('peramalan')->name('peramalan.')->group(function () {

        // Data historis: agregasi penjualan menjadi deret Zt
        Route::get('historis', [DataHistorisController::class, 'index'])->name('historis.index');
        Route::post('historis/agregasi', [DataHistorisController::class, 'agregasi'])->name('historis.agregasi');

        // Proses forecasting: 4 tahap Box-Jenkins
        Route::get('forecasting', [ForecastingController::class, 'index'])->name('forecasting.index');
        Route::post('forecasting/proses', [ForecastingController::class, 'proses'])->name('forecasting.proses');
        Route::get('forecasting/{peramalan}', [ForecastingController::class, 'show'])->name('forecasting.show');

        // Rincian waktu tunggu operasional pabrik
        Route::get('waktu-tunggu', [WaktuTungguController::class, 'index'])->name('waktu-tunggu.index');
        Route::post('waktu-tunggu/hitung', [WaktuTungguController::class, 'hitung'])->name('waktu-tunggu.hitung');

        // Target produksi + kebutuhan bahan
        Route::get('target', [TargetProduksiController::class, 'index'])->name('target.index');
        Route::post('target/hitung', [TargetProduksiController::class, 'hitung'])->name('target.hitung');
        Route::patch('target/{target}/setujui', [TargetProduksiController::class, 'setujui'])->name('target.setujui');
    });

    // -----------------------------------------------------------------
    // SIMULASI - PENGUJIAN RENCANA STOK (tolak ukur utama skripsi)
    // -----------------------------------------------------------------
    Route::prefix('simulasi')->name('simulasi.')->group(function () {
        Route::get('/', [SimulasiController::class, 'index'])->name('index');
        Route::get('create', [SimulasiController::class, 'create'])->name('create');
        Route::post('/', [SimulasiController::class, 'store'])->name('store');
        Route::get('{simulasi}', [SimulasiController::class, 'show'])->name('show');
        Route::get('{simulasi}/perbandingan', [PerbandinganController::class, 'show'])->name('perbandingan');
        Route::get('{simulasi}/cetak', [SimulasiController::class, 'cetak'])->name('cetak');
    });

    // -----------------------------------------------------------------
    // LAPORAN HASIL SIMULASI
    // -----------------------------------------------------------------
    Route::get('laporan/simulasi', [LaporanSimulasiController::class, 'index'])
        ->name('laporan.simulasi');

});
