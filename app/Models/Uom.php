<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uom extends Model
{
    protected $table = 'uom';

    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'satuan', 'kode');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
