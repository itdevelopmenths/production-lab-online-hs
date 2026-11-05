<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequestItem extends Model
{
    use HasUuids;

    protected $table = 'request_items';

    protected $fillable = [
        'request_id',
        'produk_id',
        'qty_diminta',
    ];

    protected $casts = [
        'qty_diminta' => 'decimal:2',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'request_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
