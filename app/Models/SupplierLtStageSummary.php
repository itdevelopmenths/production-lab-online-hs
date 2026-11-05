<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierLtStageSummary extends Model
{
    protected $table = 'supplier_lt_stage_summary';

    protected $fillable = [
        'supplier_id',
        'stage',
        'jumlah_transaksi',
        'total_menit',
        'average_hari',
        'max_hari',
    ];

    protected $casts = [
        'jumlah_transaksi' => 'integer',
        'total_menit' => 'integer',
        'average_hari' => 'decimal:1',
        'max_hari' => 'decimal:1',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}

