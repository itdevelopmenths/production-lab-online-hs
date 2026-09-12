<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'po_id',
        'produk_id',
        'qty',
        'harga_total',
        'diskon',
        'ppn',
        'ongkir',
        'adjustment',
        'hpp_per_satuan',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'harga_total' => 'decimal:2',
        'diskon' => 'decimal:2',
        'ppn' => 'decimal:2',
        'ongkir' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'hpp_per_satuan' => 'decimal:4',
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

    /** Total bersih setelah diskon, ppn, ongkir, dan adjustment */
    public function netTotal(): float
    {
        return (float) $this->harga_total - (float) $this->diskon + (float) $this->ppn + (float) $this->ongkir + (float) $this->adjustment;
    }

    /** Harga per satuan (HPP) = netTotal / qty. */
    public function hargaPerSatuan(): float
    {
        if ((float) $this->hpp_per_satuan > 0) {
            return (float) $this->hpp_per_satuan;
        }

        return $this->qty > 0 ? $this->netTotal() / (float) $this->qty : 0;
    }

    public function qtyDiterima(): float
    {
        if ($this->relationLoaded('bardatItems')) {
            return (float) $this->bardatItems->sum('qty_diterima');
        }

        return (float) $this->bardatItems()->sum('qty_diterima');
    }
}
