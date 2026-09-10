<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestTransferItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'request_transfer_id',
        'produk_id',
        'qty_diminta',
        'qty_dikirim',
        'qty_diterima',
    ];

    protected $casts = [
        'qty_diminta' => 'decimal:2',
        'qty_dikirim' => 'decimal:2',
        'qty_diterima' => 'decimal:2',
    ];

    public function requestTransfer(): BelongsTo
    {
        return $this->belongsTo(RequestTransfer::class, 'request_transfer_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function selisih(): float
    {
        return (float) ($this->qty_diterima ?? 0) - (float) ($this->qty_dikirim ?? 0);
    }
}
