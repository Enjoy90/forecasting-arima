<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengujian rencana stok memakai data masa lalu (backtesting).
 * Tolak ukur utama keberhasilan sistem menurut revisi sidang proposal.
 *
 * Awalan kolom:
 *   pb_*  = skenario kebijakan perusahaan
 *   sis_* = skenario rekomendasi sistem
 */
class Simulasi extends Model
{
    use HasFactory;

    protected $table = 'simulasi';

    public const SKENARIO_PERUSAHAAN = 'perusahaan';
    public const SKENARIO_SISTEM = 'sistem';

    protected $fillable = [
        'kode_simulasi',
        'barang_id',
        'peramalan_id',
        'user_id',
        'periode_awal',
        'periode_akhir',
        'jumlah_periode',
        'stok_awal_simulasi',
        'metode_pembanding',
        'nilai_z',
        'service_level',
        'lead_time_total_hari',
        'biaya_simpan_per_unit',
        'biaya_stockout_per_unit',
        'pb_total_stockout_unit',
        'pb_bulan_stockout',
        'pb_rata_stok_akhir',
        'pb_total_overstock_unit',
        'pb_service_level_tercapai',
        'pb_perputaran_persediaan',
        'pb_total_biaya',
        'sis_total_stockout_unit',
        'sis_bulan_stockout',
        'sis_rata_stok_akhir',
        'sis_total_overstock_unit',
        'sis_service_level_tercapai',
        'sis_perputaran_persediaan',
        'sis_total_biaya',
        'penurunan_overstock_persen',
        'penurunan_stockout_persen',
        'penghematan_biaya',
        'is_sistem_lebih_baik',
        'kesimpulan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_periode' => 'integer',
            'stok_awal_simulasi' => 'decimal:2',
            'nilai_z' => 'decimal:4',
            'service_level' => 'decimal:2',
            'lead_time_total_hari' => 'decimal:2',
            'biaya_simpan_per_unit' => 'decimal:2',
            'biaya_stockout_per_unit' => 'decimal:2',
            'pb_total_stockout_unit' => 'decimal:2',
            'pb_bulan_stockout' => 'integer',
            'pb_rata_stok_akhir' => 'decimal:2',
            'pb_total_overstock_unit' => 'decimal:2',
            'pb_service_level_tercapai' => 'decimal:4',
            'pb_perputaran_persediaan' => 'decimal:4',
            'pb_total_biaya' => 'decimal:2',
            'sis_total_stockout_unit' => 'decimal:2',
            'sis_bulan_stockout' => 'integer',
            'sis_rata_stok_akhir' => 'decimal:2',
            'sis_total_overstock_unit' => 'decimal:2',
            'sis_service_level_tercapai' => 'decimal:4',
            'sis_perputaran_persediaan' => 'decimal:4',
            'sis_total_biaya' => 'decimal:2',
            'penurunan_overstock_persen' => 'decimal:4',
            'penurunan_stockout_persen' => 'decimal:4',
            'penghematan_biaya' => 'decimal:2',
            'is_sistem_lebih_baik' => 'boolean',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function peramalan(): BelongsTo
    {
        return $this->belongsTo(Peramalan::class, 'peramalan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(SimulasiDetail::class, 'simulasi_id');
    }

    public function detailPerusahaan(): Collection
    {
        return $this->detail()
            ->where('skenario', self::SKENARIO_PERUSAHAAN)
            ->orderBy('urutan_t')
            ->get();
    }

    public function detailSistem(): Collection
    {
        return $this->detail()
            ->where('skenario', self::SKENARIO_SISTEM)
            ->orderBy('urutan_t')
            ->get();
    }
}
