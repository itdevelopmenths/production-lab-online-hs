<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlokasiBahan extends Model
{
    use HasUuids;

    protected $table = 'alokasi_bahan';

    protected $fillable = [
        'batch_id',
        'bahan_id',
        'qty_dialokasikan',
        'status',
    ];

    protected $casts = [
        'qty_dialokasikan' => 'decimal:2',
    ];

    public const STATUS = ['aktif', 'dilepas', 'dibatalkan'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchProduksi::class, 'batch_id');
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'bahan_id');
    }
}
