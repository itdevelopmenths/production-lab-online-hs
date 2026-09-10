<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalisaImporMeta extends Model
{
    protected $table = 'analisa_impor_meta';

    protected $fillable = [
        'produk_id',
        'punya_varian',
        'out_total_4bulan',
        'lead_time_average',
        'lead_time_max',
        'klasifikasi_abc',
        'review_period',
        'harga_per_satuan',
    ];

    protected $casts = [
        'punya_varian' => 'boolean',
        'out_total_4bulan' => 'decimal:2',
        'lead_time_average' => 'decimal:2',
        'lead_time_max' => 'decimal:2',
        'harga_per_satuan' => 'decimal:2',
    ];

    /** Tambahan hari buffer per klasifikasi ABC. */
    public const ABC_BUFFER = ['wajib_a' => 4, 'a' => 4, 'b' => 2, 'c' => 0];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function varian(): HasMany
    {
        return $this->hasMany(AnalisaImporVarian::class, 'analisa_impor_meta_id');
    }

    public function tambahanHariAbc(): int
    {
        return self::ABC_BUFFER[$this->klasifikasi_abc] ?? 0;
    }
}
