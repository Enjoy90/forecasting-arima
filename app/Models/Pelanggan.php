<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelanggan extends Model
{
    use HasFactory;

    protected $table = 'pelanggan';

    protected $fillable = [
        'kode_pelanggan',
        'nama_pelanggan',
        'jenis',
        'telepon',
        'email',
        'alamat',
        'kota',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }

    public function penjualan(): HasMany
    {
        return $this->hasMany(Penjualan::class, 'pelanggan_id');
    }
}
