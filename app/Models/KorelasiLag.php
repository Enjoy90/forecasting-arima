<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tahap 1 - nilai ACF & PACF per lag, bahan plot correlogram. */
class KorelasiLag extends Model
{
    use HasFactory;

    protected $table = 'korelasi_lag';

    protected $fillable = [
        'peramalan_id',
        'jenis',
        'lag',
        'nilai',
        'batas_atas',
        'batas_bawah',
        'is_signifikan',
    ];

    protected function casts(): array
    {
        return [
            'lag' => 'integer',
            'nilai' => 'decimal:6',
            'batas_atas' => 'decimal:6',
            'batas_bawah' => 'decimal:6',
            'is_signifikan' => 'boolean',
        ];
    }

    public function peramalan(): BelongsTo
    {
        return $this->belongsTo(Peramalan::class, 'peramalan_id');
    }

    public function scopeAcf(Builder $q): Builder
    {
        return $q->where('jenis', 'ACF');
    }

    public function scopePacf(Builder $q): Builder
    {
        return $q->where('jenis', 'PACF');
    }
}
