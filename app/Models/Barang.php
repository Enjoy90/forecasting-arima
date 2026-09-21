<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    use HasFactory;

    protected $table = 'barang';

    public const JENIS_BAHAN_BAKU = 'bahan_baku';
    public const JENIS_SETENGAH_JADI = 'setengah_jadi';
    public const JENIS_BARANG_JADI = 'barang_jadi';

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'jenis_barang',
        'kategori_id',
        'supplier_id',
        'satuan',
        'harga_beli',
        'harga_jual',
        'stok_tersedia',
        'stok_minimum',
        'lead_time_hari',
        'service_level',
        'is_diramalkan',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'harga_beli' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'stok_tersedia' => 'integer',
            'stok_minimum' => 'integer',
            'lead_time_hari' => 'integer',
            'service_level' => 'decimal:2',
            'is_diramalkan' => 'boolean',
            'is_aktif' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------
    // Relasi
    // ---------------------------------------------------------------

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** BOM yang MENGHASILKAN barang ini */
    public function bom(): HasMany
    {
        return $this->hasMany(Bom::class, 'barang_id');
    }

    /** Baris BOM tempat barang ini dipakai sebagai KOMPONEN */
    public function dipakaiDiBom(): HasMany
    {
        return $this->hasMany(DetailBom::class, 'barang_id');
    }

    public function detailPembelian(): HasMany
    {
        return $this->hasMany(DetailPembelian::class, 'barang_id');
    }

    public function detailPenjualan(): HasMany
    {
        return $this->hasMany(DetailPenjualan::class, 'barang_id');
    }

    public function mutasiStok(): HasMany
    {
        return $this->hasMany(MutasiStok::class, 'barang_id');
    }

    public function dataTimeSeries(): HasMany
    {
        return $this->hasMany(DataTimeSeries::class, 'barang_id');
    }

    public function peramalan(): HasMany
    {
        return $this->hasMany(Peramalan::class, 'barang_id');
    }

    public function simulasi(): HasMany
    {
        return $this->hasMany(Simulasi::class, 'barang_id');
    }

    // ---------------------------------------------------------------
    // Scope
    // ---------------------------------------------------------------

    public function scopeBahanBaku(Builder $q): Builder
    {
        return $q->where('jenis_barang', self::JENIS_BAHAN_BAKU);
    }

    public function scopeSetengahJadi(Builder $q): Builder
    {
        return $q->where('jenis_barang', self::JENIS_SETENGAH_JADI);
    }

    public function scopeBarangJadi(Builder $q): Builder
    {
        return $q->where('jenis_barang', self::JENIS_BARANG_JADI);
    }

    public function scopeDiramalkan(Builder $q): Builder
    {
        return $q->where('is_diramalkan', true);
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('is_aktif', true);
    }

    // ---------------------------------------------------------------
    // Bantuan
    // ---------------------------------------------------------------

    public function getIsStokMenipisAttribute(): bool
    {
        return $this->stok_tersedia <= $this->stok_minimum;
    }

    /** BOM aktif yang dipakai untuk memproduksi barang ini */
    public function bomAktif(): ?Bom
    {
        return $this->bom()->where('is_aktif', true)->first();
    }
}
