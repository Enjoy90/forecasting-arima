<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header satu proses peramalan: model ARIMA(p,d,q) terpilih beserta akurasinya.
 */
class Peramalan extends Model
{
    use HasFactory;

    protected $table = 'peramalan';

    protected $fillable = [
        'kode_peramalan',
        'barang_id',
        'user_id',
        'periode_awal',
        'periode_akhir',
        'jumlah_data',
        'ordo_p',
        'ordo_d',
        'ordo_q',
        'konstanta',
        'aic',
        'bic',
        'sigma_kuadrat',
        'mape',
        'rmse',
        'mae',
        'kategori_akurasi',
        'horizon',
        'status',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_data' => 'integer',
            'ordo_p' => 'integer',
            'ordo_d' => 'integer',
            'ordo_q' => 'integer',
            'konstanta' => 'decimal:6',
            'aic' => 'decimal:6',
            'bic' => 'decimal:6',
            'sigma_kuadrat' => 'decimal:6',
            'mape' => 'decimal:4',
            'rmse' => 'decimal:6',
            'mae' => 'decimal:6',
            'horizon' => 'integer',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ujiStasioneritas(): HasMany
    {
        return $this->hasMany(UjiStasioneritas::class, 'peramalan_id');
    }

    public function korelasiLag(): HasMany
    {
        return $this->hasMany(KorelasiLag::class, 'peramalan_id');
    }

    public function kandidatModel(): HasMany
    {
        return $this->hasMany(KandidatModel::class, 'peramalan_id');
    }

    public function parameterModel(): HasMany
    {
        return $this->hasMany(ParameterModel::class, 'peramalan_id');
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(HasilPeramalan::class, 'peramalan_id');
    }

    public function targetProduksi(): HasMany
    {
        return $this->hasMany(TargetProduksi::class, 'peramalan_id');
    }

    public function simulasi(): HasMany
    {
        return $this->hasMany(Simulasi::class, 'peramalan_id');
    }

    /** Penulisan model, contoh: ARIMA(1,1,2) */
    public function getNamaModelAttribute(): string
    {
        return "ARIMA({$this->ordo_p},{$this->ordo_d},{$this->ordo_q})";
    }

    /** Label kategori akurasi berdasarkan nilai MAPE */
    public static function kategoriMape(float $mape): string
    {
        return match (true) {
            $mape < 10 => 'Sangat Baik',
            $mape < 20 => 'Baik',
            $mape < 50 => 'Cukup',
            default => 'Buruk',
        };
    }
}
