<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'supplier';

    protected $fillable = [
        'kode_supplier',
        'nama_supplier',
        'telepon',
        'email',
        'alamat',
        'lead_time_default',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'lead_time_default' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'supplier_id');
    }

    public function pembelian(): HasMany
    {
        return $this->hasMany(Pembelian::class, 'supplier_id');
    }
}
