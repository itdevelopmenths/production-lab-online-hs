<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProdukLtSummary extends Model
{
    protected $table = 'supplier_produk_lt_summary';
    public $timestamps = false;

    protected $fillable = [
        'supplier_id',
        'produk_id',
        'jumlah_transaksi',
        'total_lt_menit',
        'average_lt_hari',
        'max_lt_hari',
        'diperbarui_pada',
    ];

    protected $casts = [
        'jumlah_transaksi' => 'integer',
        'total_lt_menit' => 'integer',
        'average_lt_hari' => 'decimal:1',
        'max_lt_hari' => 'decimal:1',
        'diperbarui_pada' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
