<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gudang extends Model
{
    protected $table = 'gudang';

    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'parent_gudang_id',
        'status',
        'allow_negative_stock',
    ];

    protected $casts = [
        'allow_negative_stock' => 'boolean',
    ];

    public const TIPE = ['bahan_baku', 'operasional', 'fulfillment_pusat', 'fulfillment_cabang'];

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

    public function scopeFulfillment($query)
    {
        return $query->whereIn('tipe', ['fulfillment_pusat', 'fulfillment_cabang']);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'gudang_user', 'gudang_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
