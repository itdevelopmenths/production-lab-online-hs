<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchProduksiOutput extends Model
{
    use HasUuids;

    protected $table = 'batch_produksi_outputs';

    protected $fillable = [
        'batch_id',
        'produk_id',
        'qty_rencana',
        'qty_baik',
        'qty_rusak',
        'catatan',
    ];

    protected $casts = [
        'qty_rencana' => 'decimal:2',
        'qty_baik' => 'decimal:2',
        'qty_rusak' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchProduksi::class, 'batch_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function yield(): ?float
    {
        if ($this->qty_baik === null || (float) $this->qty_rencana <= 0) {
            return null;
        }

        return (float) $this->qty_baik / (float) $this->qty_rencana * 100;
    }
}
