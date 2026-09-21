<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pembelian extends Model
{
    use HasFactory;

    protected $table = 'pembelian';

    protected $fillable = [
        'no_pembelian',
        'tanggal_pembelian',
        'tanggal_terima',
        'supplier_id',
        'user_id',
        'total_harga',
        'status',
        'sumber_data',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pembelian' => 'date',
            'tanggal_terima' => 'date',
            'total_harga' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPembelian::class, 'pembelian_id');
    }

    public function hitungTotal(): float
    {
        return (float) $this->detail()->sum('subtotal');
    }
}
