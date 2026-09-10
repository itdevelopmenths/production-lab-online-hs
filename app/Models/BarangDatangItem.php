<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangDatangItem extends Model
{
    use HasUuids;

    protected $table = 'barang_datang_items';

    protected $fillable = [
        'bardat_id',
        'po_item_id',
        'qty_diterima',
    ];

    protected $casts = [
        'qty_diterima' => 'decimal:2',
    ];

    public function barangDatang(): BelongsTo
    {
        return $this->belongsTo(BarangDatang::class, 'bardat_id');
    }

    public function poItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'po_item_id');
    }
}
