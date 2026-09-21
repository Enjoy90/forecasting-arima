<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailProduksiBahan extends Model
{
    use HasFactory;

    protected $table = 'detail_produksi_bahan';

    protected $fillable = [
        'produksi_id',
        'barang_id',
        'jumlah_rencana',
        'jumlah_pakai',
        'satuan',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_rencana' => 'decimal:4',
            'jumlah_pakai' => 'decimal:4',
        ];
    }

    public function produksi(): BelongsTo
    {
        return $this->belongsTo(Produksi::class, 'produksi_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    /** Selisih realisasi terhadap rencana; positif berarti boros */
    public function getSelisihAttribute(): float
    {
        return (float) $this->jumlah_pakai - (float) $this->jumlah_rencana;
    }
}
