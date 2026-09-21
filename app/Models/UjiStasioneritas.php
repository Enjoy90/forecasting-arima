<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tahap 1 - hasil uji ADF pada tiap tingkat differencing. */
class UjiStasioneritas extends Model
{
    use HasFactory;

    protected $table = 'uji_stasioneritas';

    protected $fillable = [
        'peramalan_id',
        'differencing_ke',
        'rata_rata',
        'standar_deviasi',
        'adf_statistic',
        'nilai_kritis_1',
        'nilai_kritis_5',
        'nilai_kritis_10',
        'p_value',
        'is_stasioner',
        'kesimpulan',
    ];

    protected function casts(): array
    {
        return [
            'differencing_ke' => 'integer',
            'rata_rata' => 'decimal:6',
            'standar_deviasi' => 'decimal:6',
            'adf_statistic' => 'decimal:6',
            'nilai_kritis_1' => 'decimal:6',
            'nilai_kritis_5' => 'decimal:6',
            'nilai_kritis_10' => 'decimal:6',
            'p_value' => 'decimal:6',
            'is_stasioner' => 'boolean',
        ];
    }

    public function peramalan(): BelongsTo
    {
        return $this->belongsTo(Peramalan::class, 'peramalan_id');
    }
}
