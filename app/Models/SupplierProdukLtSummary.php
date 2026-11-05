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
        'total_po_selesai',
        'average_lt_days',
        'max_lt_days',
    ];

    protected $casts = [
        'jumlah_transaksi' => 'integer',
        'total_lt_menit' => 'integer',
        'average_lt_hari' => 'decimal:1',
        'max_lt_hari' => 'decimal:1',
        'diperbarui_pada' => 'datetime',
    ];

    public function getTotalPoSelesaiAttribute(): int
    {
        return (int) ($this->jumlah_transaksi ?? 0);
    }

    public function setTotalPoSelesaiAttribute($value): void
    {
        $this->attributes['jumlah_transaksi'] = $value;
    }

    public function getAverageLtDaysAttribute(): float
    {
        return (float) ($this->average_lt_hari ?? 0);
    }

    public function setAverageLtDaysAttribute($value): void
    {
        $this->attributes['average_lt_hari'] = $value;
    }

    public function getMaxLtDaysAttribute(): float
    {
        return (float) ($this->max_lt_hari ?? 0);
    }

    public function setMaxLtDaysAttribute($value): void
    {
        $this->attributes['max_lt_hari'] = $value;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}

