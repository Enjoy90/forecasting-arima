<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailBom extends Model
{
    use HasFactory;

    protected $table = 'detail_bom';

    protected $fillable = [
        'bom_id',
        'barang_id',
        'jumlah_kebutuhan',
        'satuan',
        'persen_susut',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_kebutuhan' => 'decimal:4',
            'persen_susut' => 'decimal:2',
        ];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    /** Komponen penyusun */
    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
