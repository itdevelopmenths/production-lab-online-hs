<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadTimeImpor extends Model
{
    protected $table = 'lead_time_impor';

    protected $fillable = [
        'produk_id',
        'lead_time_average',
        'lead_time_max',
    ];

    protected $casts = [
        'lead_time_average' => 'float',
        'lead_time_max' => 'float',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
