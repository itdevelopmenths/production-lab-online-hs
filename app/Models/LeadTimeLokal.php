<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadTimeLokal extends Model
{
    protected $table = 'lead_time_lokal';

    protected $fillable = [
        'produk_id',
        'total_average_lead_time',
        'total_max_lead_time',
        'tambahan_buffer_hari',
        'safety_stock',
    ];

    protected $casts = [
        'total_average_lead_time' => 'float',
        'total_max_lead_time' => 'float',
        'tambahan_buffer_hari' => 'float',
        'safety_stock' => 'float',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    /**
     * Hitung ulang safety stock: (total_max - total_avg) + tambahan_buffer_hari
     */
    public function recalculateSafetyStock(): float
    {
        $buffer = (float) ($this->tambahan_buffer_hari ?? 0);
        $diff = max(0, (float) $this->total_max_lead_time - (float) $this->total_average_lead_time);
        $this->safety_stock = round($diff + $buffer, 1, PHP_ROUND_HALF_UP);
        return (float) $this->safety_stock;
    }
}
