<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisaFulfillmentInput extends Model
{
    protected $table = 'analisa_fulfillment_input';

    protected $fillable = [
        'produk_id',
        'gudang_id',
        'terjual_rata_rata_4bulan',
        'lead_time_distribusi',
        'buffer_distribusi',
        'review_period',
        'stok_saat_ini',
        'akan_datang',
    ];

    protected $casts = [
        'terjual_rata_rata_4bulan' => 'decimal:2',
        'stok_saat_ini' => 'decimal:2',
        'akan_datang' => 'decimal:2',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }
}
