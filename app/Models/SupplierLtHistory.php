<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierLtHistory extends Model
{
    protected $table = 'supplier_lt_history';

    protected $fillable = [
        'supplier_id',
        'produk_id',
        'po_id',
        'jenis_event',
        'jumlah_sebelum',
        'jumlah_sesudah',
        'total_menit_sebelum',
        'total_menit_sesudah',
        'average_sebelum',
        'average_sesudah',
        'max_sebelum',
        'max_sesudah',
        'dicatat_oleh',
        'dicatat_pada',
    ];

    protected $casts = [
        'jumlah_sebelum' => 'integer',
        'jumlah_sesudah' => 'integer',
        'total_menit_sebelum' => 'integer',
        'total_menit_sesudah' => 'integer',
        'average_sebelum' => 'decimal:1',
        'average_sesudah' => 'decimal:1',
        'max_sebelum' => 'decimal:1',
        'max_sesudah' => 'decimal:1',
        'dicatat_pada' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}

