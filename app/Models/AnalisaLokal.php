<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisaLokal extends Model
{
    protected $table = 'analisa_lokal';

    protected $fillable = [
        'produk_id',
        'total_average_lead_time',
        'safety_stock',
        'terjual_rata_rata_4bulan',
        'adu',
        'review_period',
        'batas_minimum',
        'target_stock',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'total_average_lead_time' => 'integer',
        'safety_stock' => 'integer',
        'terjual_rata_rata_4bulan' => 'decimal:2',
        'adu' => 'decimal:4',
        'review_period' => 'integer',
        'batas_minimum' => 'decimal:2',
        'target_stock' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
