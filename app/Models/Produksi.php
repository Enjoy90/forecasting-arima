<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu perintah kerja pada satu tahapan produksi.
 * Lima menu tahapan memakai model ini, dibedakan oleh tahapan_id.
 */
class Produksi extends Model
{
    use HasFactory;

    protected $table = 'produksi';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROSES = 'proses';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_BATAL = 'batal';

    protected $fillable = [
        'no_produksi',
        'tanggal_produksi',
        'tahapan_id',
        'bom_id',
        'barang_output_id',
        'jumlah_target',
        'jumlah_hasil',
        'jumlah_gagal',
        'status',
        'user_id',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_produksi' => 'date',
            'jumlah_target' => 'decimal:2',
            'jumlah_hasil' => 'decimal:2',
            'jumlah_gagal' => 'decimal:2',
        ];
    }

    public function tahapan(): BelongsTo
    {
        return $this->belongsTo(TahapanProduksi::class, 'tahapan_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    public function barangOutput(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_output_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bahan(): HasMany
    {
        return $this->hasMany(DetailProduksiBahan::class, 'produksi_id');
    }

    public function scopeTahapan(Builder $q, int $tahapanId): Builder
    {
        return $q->where('tahapan_id', $tahapanId);
    }

    public function scopeSelesai(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_SELESAI);
    }

    /** Persentase produk gagal terhadap total yang dikerjakan */
    public function getPersenGagalAttribute(): float
    {
        $total = (float) $this->jumlah_hasil + (float) $this->jumlah_gagal;

        return $total > 0 ? round((float) $this->jumlah_gagal / $total * 100, 2) : 0.0;
    }
}
