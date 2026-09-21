<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahapanProduksi extends Model
{
    use HasFactory;

    protected $table = 'tahapan_produksi';

    protected $fillable = [
        'kode_tahapan',
        'nama_tahapan',
        'urutan',
        'waktu_proses_hari',
        'kapasitas_per_hari',
        'deskripsi',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'waktu_proses_hari' => 'decimal:2',
            'kapasitas_per_hari' => 'decimal:2',
            'is_aktif' => 'boolean',
        ];
    }

    public function bom(): HasMany
    {
        return $this->hasMany(Bom::class, 'tahapan_id');
    }

    public function produksi(): HasMany
    {
        return $this->hasMany(Produksi::class, 'tahapan_id');
    }

    public function kebutuhanBahan(): HasMany
    {
        return $this->hasMany(KebutuhanBahan::class, 'tahapan_id');
    }

    /**
     * Lama tahapan ini untuk sejumlah unit tertentu, dalam hari.
     * Bila target melebihi kapasitas harian, tahapan memakan hari tambahan.
     */
    public function hariUntuk(float $jumlah): float
    {
        $hari = (float) $this->waktu_proses_hari;

        if ($this->kapasitas_per_hari > 0 && $jumlah > 0) {
            $hari += ceil($jumlah / (float) $this->kapasitas_per_hari) - 1;
        }

        return $hari;
    }
}
