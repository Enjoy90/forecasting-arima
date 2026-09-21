<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deret Zt: hasil agregasi penjualan bulanan per barang jadi.
 */
class DataTimeSeries extends Model
{
    use HasFactory;

    protected $table = 'data_time_series';

    protected $fillable = [
        'barang_id',
        'periode',
        'tahun',
        'bulan',
        'urutan_t',
        'nilai_zt',
        'dihitung_pada',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'urutan_t' => 'integer',
            'nilai_zt' => 'decimal:2',
            'dihitung_pada' => 'datetime',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function getNamaPeriodeAttribute(): string
    {
        $nama = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return ($nama[$this->bulan] ?? '?').' '.$this->tahun;
    }
}
