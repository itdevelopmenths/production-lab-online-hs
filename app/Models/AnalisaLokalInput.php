<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalisaLokalInput extends Model
{
    protected $table = 'analisa_lokal_input';

    protected $fillable = [
        'produk_id',
        'terjual_rata_rata_4bulan',
        'review_period',
        'stok_saat_ini',
        'akan_datang',
        'harga_per_satuan',
    ];

    protected $casts = [
        'terjual_rata_rata_4bulan' => 'decimal:2',
        'stok_saat_ini' => 'decimal:2',
        'akan_datang' => 'decimal:2',
        'harga_per_satuan' => 'decimal:2',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(LeadTimeStage::class, 'produk_id', 'produk_id');
    }
}
