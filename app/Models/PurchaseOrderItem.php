<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'po_id',
        'produk_id',
        'qty',
        'harga_total',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'harga_total' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function bardatItems(): HasMany
    {
        return $this->hasMany(BarangDatangItem::class, 'po_item_id');
    }

    /** Harga per satuan (HPP) = harga_total / qty. */
    public function hargaPerSatuan(): float
    {
        return $this->qty > 0 ? (float) $this->harga_total / (float) $this->qty : 0;
    }

    public function qtyDiterima(): float
    {
        return (float) $this->bardatItems->sum('qty_diterima');
    }
}
