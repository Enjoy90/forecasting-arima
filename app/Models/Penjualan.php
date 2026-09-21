<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penjualan extends Model
{
    use HasFactory;

    protected $table = 'penjualan';

    protected $fillable = [
        'no_faktur',
        'tanggal_penjualan',
        'pelanggan_id',
        'user_id',
        'nama_pelanggan_manual',
        'total_harga',
        'sumber_data',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penjualan' => 'date',
            'total_harga' => 'decimal:2',
        ];
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPenjualan::class, 'penjualan_id');
    }

    /** Nama pelanggan terdaftar, atau nama manual hasil import */
    public function getNamaPembeliAttribute(): string
    {
        return $this->pelanggan?->nama_pelanggan
            ?? $this->nama_pelanggan_manual
            ?? 'Umum';
    }
}
