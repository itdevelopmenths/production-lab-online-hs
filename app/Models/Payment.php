<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'po_id',
        'skema',
        'tanggal_bayar',
        'nominal',
        'bukti_file',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date',
        'nominal' => 'decimal:2',
    ];

    public const SKEMA = ['tempo', 'termin', 'pelunasan'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
}
