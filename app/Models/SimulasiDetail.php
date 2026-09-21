<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak simulasi bulan per bulan untuk satu skenario.
 * Dicetak sebagai lampiran pengujian di laporan skripsi.
 */
class SimulasiDetail extends Model
{
    use HasFactory;

    protected $table = 'simulasi_detail';

    protected $fillable = [
        'simulasi_id',
        'skenario',
        'periode',
        'urutan_t',
        'stok_awal',
        'permintaan_aktual',
        'prediksi_permintaan',
        'safety_stock',
        'rencana_produksi',
        'barang_masuk',
        'terpenuhi',
        'stockout_unit',
        'stok_akhir',
        'overstock_unit',
        'biaya_simpan',
        'biaya_stockout',
        'is_stockout',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'urutan_t' => 'integer',
            'stok_awal' => 'decimal:2',
            'permintaan_aktual' => 'decimal:2',
            'prediksi_permintaan' => 'decimal:2',
            'safety_stock' => 'decimal:2',
            'rencana_produksi' => 'decimal:2',
            'barang_masuk' => 'decimal:2',
            'terpenuhi' => 'decimal:2',
            'stockout_unit' => 'decimal:2',
            'stok_akhir' => 'decimal:2',
            'overstock_unit' => 'decimal:2',
            'biaya_simpan' => 'decimal:2',
            'biaya_stockout' => 'decimal:2',
            'is_stockout' => 'boolean',
        ];
    }

    public function simulasi(): BelongsTo
    {
        return $this->belongsTo(Simulasi::class, 'simulasi_id');
    }

    public function scopeSkenario(Builder $q, string $skenario): Builder
    {
        return $q->where('skenario', $skenario);
    }

    public function getTotalBiayaAttribute(): float
    {
        return (float) $this->biaya_simpan + (float) $this->biaya_stockout;
    }
}
