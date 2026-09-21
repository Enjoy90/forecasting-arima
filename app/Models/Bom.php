<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bill of Material: komposisi bahan untuk menghasilkan satu item output
 * pada satu tahapan produksi.
 */
class Bom extends Model
{
    use HasFactory;

    protected $table = 'bom';

    protected $fillable = [
        'kode_bom',
        'nama_bom',
        'barang_id',
        'tahapan_id',
        'jumlah_output',
        'is_aktif',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_output' => 'decimal:4',
            'is_aktif' => 'boolean',
        ];
    }

    /** Item yang DIHASILKAN resep ini */
    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function tahapan(): BelongsTo
    {
        return $this->belongsTo(TahapanProduksi::class, 'tahapan_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailBom::class, 'bom_id');
    }

    public function produksi(): HasMany
    {
        return $this->hasMany(Produksi::class, 'bom_id');
    }

    /**
     * Kebutuhan tiap komponen untuk memproduksi sejumlah unit output,
     * sudah memperhitungkan persen susut.
     *
     * @return array<int, array{barang_id:int, jumlah:float, satuan:?string}>
     */
    public function kebutuhanUntuk(float $jumlahOutput): array
    {
        $rasio = $jumlahOutput / max((float) $this->jumlah_output, 0.0001);

        return $this->detail->map(fn (DetailBom $d) => [
            'barang_id' => $d->barang_id,
            'jumlah' => (float) $d->jumlah_kebutuhan * $rasio * (1 + (float) $d->persen_susut / 100),
            'satuan' => $d->satuan,
        ])->all();
    }
}
