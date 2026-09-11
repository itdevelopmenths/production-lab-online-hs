<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    protected $table = 'produk';

    protected $fillable = [
        'sku',
        'nama',
        'tipe',
        'satuan',
        'satuan_order_moq',
        'profil_analisa',
        'is_active',
    ];

    protected $casts = [
        'satuan_order_moq' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public const TIPE = ['bahan', 'kemas', 'produk_jadi'];

    /** Relasi master UOM berdasarkan kolom kode satuan. */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'satuan', 'kode');
    }

    /** BOM baris di mana produk ini adalah produk jadi. */
    public function bom(): HasMany
    {
        return $this->hasMany(Bom::class, 'produk_jadi_id');
    }

    /** BOM baris di mana produk ini dipakai sebagai bahan. */
    public function bomAsBahan(): HasMany
    {
        return $this->hasMany(Bom::class, 'bahan_id');
    }

    public function stok(): HasMany
    {
        return $this->hasMany(Stok::class, 'produk_id');
    }

    public function kartuStok(): HasMany
    {
        return $this->hasMany(KartuStok::class, 'produk_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBahan($query)
    {
        return $query->whereIn('tipe', ['bahan', 'kemas']);
    }

    public function scopeKemas($query)
    {
        return $query->where('tipe', 'kemas');
    }

    public function scopeProdukJadi($query)
    {
        return $query->where('tipe', 'produk_jadi');
    }
}
