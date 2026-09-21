<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tahap 4 - nilai per periode.
 * in_sample : ada nilai aktual, dipakai menghitung MAPE/RMSE/MAE
 * forecast   : periode ke depan, nilai aktual masih kosong
 */
class HasilPeramalan extends Model
{
    use HasFactory;

    protected $table = 'hasil_peramalan';

    protected $fillable = [
        'peramalan_id',
        'periode',
        'urutan_t',
        'tipe',
        'nilai_aktual',
        'nilai_prediksi',
        'residual',
        'persen_error',
        'batas_bawah',
        'batas_atas',
    ];

    protected function casts(): array
    {
        return [
            'urutan_t' => 'integer',
            'nilai_aktual' => 'decimal:2',
            'nilai_prediksi' => 'decimal:2',
            'residual' => 'decimal:6',
            'persen_error' => 'decimal:6',
            'batas_bawah' => 'decimal:2',
            'batas_atas' => 'decimal:2',
        ];
    }

    public function peramalan(): BelongsTo
    {
        return $this->belongsTo(Peramalan::class, 'peramalan_id');
    }

    public function scopeInSample(Builder $q): Builder
    {
        return $q->where('tipe', 'in_sample');
    }

    public function scopeForecast(Builder $q): Builder
    {
        return $q->where('tipe', 'forecast');
    }
}
