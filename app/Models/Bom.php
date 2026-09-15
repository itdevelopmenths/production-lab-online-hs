<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bom extends Model
{
    use \App\Traits\AuditableMasterData;

    protected $table = 'bom';

    protected $fillable = [
        'produk_jadi_id',
        'bahan_id',
        'qty_per_unit',
    ];

    protected $casts = [
        'qty_per_unit' => 'decimal:4',
    ];

    public function produkJadi(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_jadi_id');
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'bahan_id');
    }
}
