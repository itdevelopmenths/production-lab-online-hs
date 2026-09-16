<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekomendasiOrderLokal extends Model
{
    protected $table = 'rekomendasi_order_lokal';

    protected $fillable = [
        'produk_id',
        'batas_minimum',
        'target_stock',
        'stok_saat_ini',
        'akan_datang',
        'selisih',
        'rumus_moq',
        'status',
        'rekomendasi_order',
        'harga_ml_pcs',
        'total_nominal_order',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'batas_minimum' => 'decimal:2',
        'target_stock' => 'decimal:2',
        'stok_saat_ini' => 'decimal:2',
        'akan_datang' => 'decimal:2',
        'selisih' => 'decimal:2',
        'rumus_moq' => 'decimal:2',
        'rekomendasi_order' => 'decimal:2',
        'harga_ml_pcs' => 'decimal:2',
        'total_nominal_order' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
