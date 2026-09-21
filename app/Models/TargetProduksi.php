<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rencana stok bulanan: hasil ramalan diterjemahkan menjadi jumlah
 * yang harus diproduksi, lengkap dengan rincian waktu tunggu operasional.
 */
class TargetProduksi extends Model
{
    use HasFactory;

    protected $table = 'target_produksi';

    protected $fillable = [
        'peramalan_id',
        'barang_id',
        'user_id',
        'periode',
        'prediksi_penjualan',
        'stok_barang_jadi',
        'stok_setengah_jadi',
        'standar_deviasi_error',
        'lead_time_pembelian_hari',
        'lead_time_produksi_hari',
        'lead_time_total_hari',
        'tanggal_mulai_produksi',
        'tanggal_pesan_bahan',
        'nilai_z',
        'safety_stock',
        'reorder_point',
        'jumlah_target_produksi',
        'status_stok',
        'status_approval',
        'disetujui_pada',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'prediksi_penjualan' => 'decimal:2',
            'stok_barang_jadi' => 'decimal:2',
            'stok_setengah_jadi' => 'decimal:2',
            'standar_deviasi_error' => 'decimal:6',
            'lead_time_pembelian_hari' => 'decimal:2',
            'lead_time_produksi_hari' => 'decimal:2',
            'lead_time_total_hari' => 'decimal:2',
            'tanggal_mulai_produksi' => 'date',
            'tanggal_pesan_bahan' => 'date',
            'nilai_z' => 'decimal:4',
            'safety_stock' => 'decimal:2',
            'reorder_point' => 'decimal:2',
            'jumlah_target_produksi' => 'decimal:2',
            'disetujui_pada' => 'datetime',
        ];
    }

    public function peramalan(): BelongsTo
    {
        return $this->belongsTo(Peramalan::class, 'peramalan_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kebutuhanBahan(): HasMany
    {
        return $this->hasMany(KebutuhanBahan::class, 'target_produksi_id');
    }

    /**
     * Apakah perintah produksi untuk periode ini sudah harus dibuat
     * pada bulan berjalan, karena waktu tunggu melebihi satu bulan.
     */
    public function getPerluDipesanBulanIniAttribute(): bool
    {
        return (float) $this->lead_time_total_hari > 30;
    }
}
