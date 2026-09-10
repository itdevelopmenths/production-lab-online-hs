<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchProduksi extends Model
{
    use HasUuids;

    protected $table = 'batch_produksi';

    protected $fillable = [
        'no_batch',
        'produk_id',
        'qty_rencana',
        'qty_baik',
        'qty_rusak',
        'gudang_operasional_id',
        'gudang_tujuan_rencana_id',
        'status',
        'tanggal',
        'created_by',
    ];

    protected $casts = [
        'qty_rencana' => 'decimal:2',
        'qty_baik' => 'decimal:2',
        'qty_rusak' => 'decimal:2',
        'tanggal' => 'date',
    ];

    public const STATUS = ['rencana', 'release', 'selesai', 'dibatalkan'];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function gudangOperasional(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_operasional_id');
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_rencana_id');
    }

    public function alokasi(): HasMany
    {
        return $this->hasMany(AlokasiBahan::class, 'batch_id');
    }

    public function opname(): HasMany
    {
        return $this->hasMany(StockOpname::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Yield (%) = qty_baik / qty_rencana * 100. */
    public function yield(): ?float
    {
        if ($this->qty_baik === null || (float) $this->qty_rencana <= 0) {
            return null;
        }
        return (float) $this->qty_baik / (float) $this->qty_rencana * 100;
    }

    public function defectRate(): ?float
    {
        $total = (float) ($this->qty_baik ?? 0) + (float) ($this->qty_rusak ?? 0);
        if ($this->qty_rusak === null || $total <= 0) {
            return null;
        }
        return (float) $this->qty_rusak / $total * 100;
    }
}
