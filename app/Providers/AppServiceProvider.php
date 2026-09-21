<?php

namespace App\Providers;

use App\Models\MutasiStok;
use App\Observers\MutasiStokObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Observer didaftarkan di sini, bukan lewat atribut #[ObservedBy] pada
        // model, karena model sudah dibekukan setelah fase 0
        // (lihat docs/04-pembagian-modul.md bagian 4).
        MutasiStok::observe(MutasiStokObserver::class);
    }
}
