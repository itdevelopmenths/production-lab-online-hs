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
        'total_average_lead_time' => 'integer',
        'total_max_lead_time' => 'integer',
        'tambahan_buffer_hari' => 'integer',
        'safety_stock' => 'integer',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    /**
     * Hitung ulang safety stock: (total_max - total_avg) + tambahan_buffer_hari
     */
    public function recalculateSafetyStock(): int
    {
        $buffer = (int) ($this->tambahan_buffer_hari ?? 0);
        $diff = max(0, (int) $this->total_max_lead_time - (int) $this->total_average_lead_time);
        $this->safety_stock = $diff + $buffer;
        return $this->safety_stock;
    }
}
