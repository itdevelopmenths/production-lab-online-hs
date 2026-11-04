<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdukSupplier extends Model
{
    protected $table = 'produk_supplier';

    protected $fillable = [
        'produk_id',
        'supplier_id',
        'status',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }
}

