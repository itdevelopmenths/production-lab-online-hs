<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoLeadTime extends Model
{
    protected $table = 'po_lead_time';

    protected $fillable = [
        'po_id',
        'supplier_id',
        'waktu_mulai',
        'waktu_selesai',
        'total_lt_menit',
        'total_lt_hari',
        'lt_internal_menit',
        'lt_supplier_menit',
        'lt_logistik_menit',
        'is_valid',
        'alasan_tidak_valid',
        'divalidasi_oleh',
    ];

    protected $casts = [
        'total_lt_menit' => 'integer',
        'total_lt_hari' => 'decimal:1',
        'lt_internal_menit' => 'integer',
        'lt_supplier_menit' => 'integer',
        'lt_logistik_menit' => 'integer',
        'is_valid' => 'boolean',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }
}

