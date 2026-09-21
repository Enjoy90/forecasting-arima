<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil BOM explosion dari target produksi.
 * Jembatan antara hasil peramalan dan perencanaan stok bahan baku.
 */
class KebutuhanBahan extends Model
{
    use HasFactory;

    protected $table = 'kebutuhan_bahan';

    protected $fillable = [
        'target_produksi_id',
        'barang_id',
        'tahapan_id',
        'jumlah_kebutuhan',
        'stok_tersedia',
        'kekurangan',
        'safety_stock_bahan',
        'qty_rekomendasi_beli',
        'satuan',
        'status',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_kebutuhan' => 'decimal:4',
            'stok_tersedia' => 'decimal:4',
            'kekurangan' => 'decimal:4',
            'safety_stock_bahan' => 'decimal:4',
            'qty_rekomendasi_beli' => 'decimal:4',
        ];
    }

    public function targetProduksi(): BelongsTo
    {
        return $this->belongsTo(TargetProduksi::class, 'target_produksi_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function tahapan(): BelongsTo
    {
        return $this->belongsTo(TahapanProduksi::class, 'tahapan_id');
    }

    /** Dipakai Modul A untuk menu "Buat Order dari Rekomendasi" */
    public function scopePerluBeli(Builder $q): Builder
    {
        return $q->whereIn('status', ['perlu_beli', 'mendesak']);
    }
}
