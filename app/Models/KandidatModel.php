<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tahap 2 - seluruh kombinasi ARIMA(p,d,q) hasil grid search. */
class KandidatModel extends Model
{
    use HasFactory;

    protected $table = 'kandidat_model';

    protected $fillable = [
        'peramalan_id',
        'ordo_p',
        'ordo_d',
        'ordo_q',
        'aic',
        'bic',
        'sse',
        'sigma_kuadrat',
        'mape',
        'semua_signifikan',
        'lolos_ljung_box',
        'is_terpilih',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'ordo_p' => 'integer',
            'ordo_d' => 'integer',
            'ordo_q' => 'integer',
            'aic' => 'decimal:6',
            'bic' => 'decimal:6',
            'sse' => 'decimal:6',
            'sigma_kuadrat' => 'decimal:6',
            'mape' => 'decimal:4',
            'semua_signifikan' => 'boolean',
            'lolos_ljung_box' => 'boolean',
            'is_terpilih' => 'boolean',
        ];
    }

    public function peramalan(): BelongsTo
    {
        return $this->belongsTo(Peramalan::class, 'peramalan_id');
    }

    public function getNamaModelAttribute(): string
    {
        return "ARIMA({$this->ordo_p},{$this->ordo_d},{$this->ordo_q})";
    }
}
