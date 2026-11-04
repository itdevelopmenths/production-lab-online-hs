<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoPengiriman extends Model
{
    protected $table = 'po_pengiriman';

    protected $fillable = [
        'po_id',
        'jumlah_koli',
        'berat_kg',
        'catatan_pengemasan',
        'ekspedisi',
        'moda',
        'no_resi',
        'no_kontainer',
        'eta_tiba',
    ];

    protected $casts = [
        'jumlah_koli' => 'integer',
        'berat_kg' => 'decimal:2',
        'eta_tiba' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
}
