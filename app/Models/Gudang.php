<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gudang extends Model
{
    use \App\Traits\AuditableMasterData;

    protected $table = 'gudang';

    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'is_pusat',
        'parent_gudang_id',
        'status',
        'allow_negative_stock',
    ];

    protected $casts = [
        'is_pusat' => 'boolean',
        'allow_negative_stock' => 'boolean',
    ];

    /**
     * Tipe logistik inti (Skenario A - Unified Model).
     */
    public const TIPE = ['bahan_baku', 'operasional', 'fulfillment'];

    /**
     * Seluruh tipe yang diterima termasuk legacy (untuk backward compatibility).
     */
    public const TIPE_ALL = ['bahan_baku', 'operasional', 'fulfillment', 'fulfillment_pusat', 'fulfillment_cabang'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'parent_gudang_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Gudang::class, 'parent_gudang_id');
    }

    public function stok(): HasMany
    {
        return $this->hasMany(Stok::class, 'gudang_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'aktif');
    }

    /**
     * Scope untuk seluruh gudang fulfillment (mendukung tipe baru 'fulfillment' & legacy).
     */
    public function scopeFulfillment($query)
    {
        return $query->whereIn('tipe', ['fulfillment', 'fulfillment_pusat', 'fulfillment_cabang']);
    }

    /**
     * Scope untuk gudang yang bertindak sebagai Pusat / Hub Utama.
     */
    public function scopePusat($query)
    {
        return $query->where('is_pusat', true);
    }

    /**
     * Scope khusus Gudang Bahan Baku Pusat.
     */
    public function scopeBahanBakuPusat($query)
    {
        return $query->where('tipe', 'bahan_baku')->where('is_pusat', true);
    }

    /**
     * Scope khusus Fulfillment Pusat.
     */
    public function scopeFulfillmentPusat($query)
    {
        return $query->where(function ($q) {
            $q->where('tipe', 'fulfillment')->where('is_pusat', true)
              ->orWhere('tipe', 'fulfillment_pusat');
        });
    }

    /**
     * Helper apakah gudang ini merupakan pusat.
     */
    public function isPusat(): bool
    {
        return (bool) $this->is_pusat || $this->tipe === 'fulfillment_pusat';
    }

    /**
     * Helper apakah gudang ini bertipe fulfillment / produk jadi.
     */
    public function isFulfillment(): bool
    {
        return in_array($this->tipe, ['fulfillment', 'fulfillment_pusat', 'fulfillment_cabang'], true);
    }

    /**
     * Helper apakah gudang ini bertipe bahan baku.
     */
    public function isBahanBaku(): bool
    {
        return $this->tipe === 'bahan_baku';
    }

    /**
     * Helper apakah gudang ini bertipe operasional.
     */
    public function isOperasional(): bool
    {
        return $this->tipe === 'operasional';
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'gudang_user', 'gudang_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
