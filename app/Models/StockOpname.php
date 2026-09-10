<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpname extends Model
{
    protected $table = 'stock_opname';

    protected $fillable = [
        'batch_id',
        'bahan_id',
        'pemakaian_teoritis',
        'pemakaian_aktual',
        'keterangan',
    ];

    protected $casts = [
        'pemakaian_teoritis' => 'decimal:2',
        'pemakaian_aktual' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchProduksi::class, 'batch_id');
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'bahan_id');
    }

    /** Variance = aktual - teoritis (positif = boros). */
    public function variance(): float
    {
        return (float) $this->pemakaian_aktual - (float) $this->pemakaian_teoritis;
    }
}
