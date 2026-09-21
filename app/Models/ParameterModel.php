<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tahap 2 - koefisien model terpilih beserta uji signifikansi. */
class ParameterModel extends Model
{
    use HasFactory;

    protected $table = 'parameter_model';

    protected $fillable = [
        'peramalan_id',
        'jenis',
        'lag',
        'koefisien',
        'standard_error',
        't_hitung',
        't_tabel',
        'p_value',
        'is_signifikan',
    ];

    protected function casts(): array
    {
        return [
            'lag' => 'integer',
            'koefisien' => 'decimal:6',
            'standard_error' => 'decimal:6',
            't_hitung' => 'decimal:6',
            't_tabel' => 'decimal:6',
            'p_value' => 'decimal:6',
            'is_signifikan' => 'boolean',
        ];
    }

    public function peramalan(): BelongsTo
    {
        return $this->belongsTo(Peramalan::class, 'peramalan_id');
    }

    /** Penulisan parameter, contoh: AR(1), MA(2), Konstanta */
    public function getNamaParameterAttribute(): string
    {
        return $this->jenis === 'KONSTANTA'
            ? 'Konstanta'
            : "{$this->jenis}({$this->lag})";
    }
}
