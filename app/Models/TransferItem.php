<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferItem extends Model
{
    use HasUuids;

    protected $table = 'transfer_items';

    protected $fillable = [
        'transfer_id',
        'produk_id',
        'request_item_id',
        'qty_dikirim',
        'qty_diterima',
        'qty_baik',
        'qty_rusak',
        'keterangan_rusak',
    ];

    protected $casts = [
        'qty_dikirim' => 'decimal:2',
        'qty_diterima' => 'decimal:2',
        'qty_baik' => 'decimal:2',
        'qty_rusak' => 'decimal:2',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'transfer_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(StockRequestItem::class, 'request_item_id');
    }
}

