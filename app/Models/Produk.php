<?php

namespace App\Models;

use App\Traits\AuditableMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Produk extends Model
{
    use AuditableMasterData;

    protected $table = 'produk';

    protected $fillable = [
        'kategori_id',
        'varian_id',
        'supplier_id',
        'sku',
        'nama',
        'nama_produk',
        'tipe',
        'satuan',
        'faktor_konversi',
        'satuan_order_moq',
        'harga_hpp',
        'profil_analisa',
        'is_active',
    ];

    protected $casts = [
        'satuan_order_moq' => 'decimal:2',
        'harga_hpp' => 'decimal:4',
        'faktor_konversi' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public const TIPE = ['bahan', 'kemas', 'produk_jadi'];

    protected static function booted(): void
    {
        static::saving(function ($produk) {
            // Keep nama column in sync if not explicitly provided or when master fields change without explicit nama
            if (empty($produk->nama) || (!$produk->isDirty('nama') && ($produk->isDirty('nama_produk') || $produk->isDirty('varian_id')))) {
                if (!empty($produk->nama_produk)) {
                    $varianNama = '';
                    if (!empty($produk->varian_id)) {
                        $varian = $produk->relationLoaded('varian') ? $produk->varian : Varian::find($produk->varian_id);
                        if ($varian) {
                            $varianNama = ' ' . $varian->nama;
                        }
                    }
                    $produk->nama = trim($produk->nama_produk . $varianNama);
                }
            }
        });
    }

    public function getNamaAttribute(?string $value): string
    {
        if (!empty($value)) {
            return $value;
        }

        if (!empty($this->attributes['nama_produk'])) {
            if ($this->relationLoaded('varian') && $this->varian) {
                return "{$this->attributes['nama_produk']} {$this->varian->nama}";
            }
            if (!empty($this->attributes['varian_id'])) {
                $varian = Varian::find($this->attributes['varian_id']);
                return $varian ? "{$this->attributes['nama_produk']} {$varian->nama}" : $this->attributes['nama_produk'];
            }
            return $this->attributes['nama_produk'];
        }

        return '';
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function varian(): BelongsTo
    {
        return $this->belongsTo(Varian::class, 'varian_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

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

    public function leadTimeLokalStages(): HasMany
    {
        return $this->hasMany(LeadTimeLokalStage::class, 'produk_id');
    }

    public function leadTimeLokal(): HasOne
    {
        return $this->hasOne(LeadTimeLokal::class, 'produk_id');
    }

    public function analisaLokal(): HasOne
    {
        return $this->hasOne(AnalisaLokal::class, 'produk_id');
    }

    public function rekomendasiOrderLokal(): HasOne
    {
        return $this->hasOne(RekomendasiOrderLokal::class, 'produk_id');
    }

    public function leadTimeImpor(): HasOne
    {
        return $this->hasOne(LeadTimeImpor::class, 'produk_id');
    }

    public function analisaImpor(): HasOne
    {
        return $this->hasOne(AnalisaImpor::class, 'produk_id');
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
