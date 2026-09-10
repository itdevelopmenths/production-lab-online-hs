<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatAnalisa extends Model
{
    use HasUuids;

    protected $table = 'riwayat_analisa';

    protected $fillable = [
        'tanggal',
        'tipe',
        'item_label',
        'batas_minimum',
        'target_stock',
        'status',
        'qty_order',
        'dicatat_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'batas_minimum' => 'decimal:2',
        'target_stock' => 'decimal:2',
        'qty_order' => 'decimal:2',
    ];

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
