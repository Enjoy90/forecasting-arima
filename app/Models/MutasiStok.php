<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiStok extends Model
{
    use HasFactory;

    protected $table = 'mutasi_stok';

    public const MASUK = 'masuk';
    public const KELUAR = 'keluar';
    public const PENYESUAIAN = 'penyesuaian';

    protected $fillable = [
        'barang_id',
        'tanggal',
        'jenis_mutasi',
        'sumber',
        'referensi_tipe',
        'referensi_id',
        'jumlah',
        'stok_awal',
        'stok_akhir',
        'user_id',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah' => 'decimal:4',
            'stok_awal' => 'decimal:4',
            'stok_akhir' => 'decimal:4',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeMasuk(Builder $q): Builder
    {
        return $q->where('jenis_mutasi', self::MASUK);
    }

    public function scopeKeluar(Builder $q): Builder
    {
        return $q->where('jenis_mutasi', self::KELUAR);
    }
}
