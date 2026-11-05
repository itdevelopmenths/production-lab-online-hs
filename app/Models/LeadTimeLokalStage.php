<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadTimeLokalStage extends Model
{
    protected $table = 'lead_time_lokal_stage';

    protected $fillable = [
        'produk_id',
        'skenario',
        'perencanaan',
        'approval',
        'supplier_confirm',
        'payment',
        'po',
        'pengemasan',
        'pengiriman',
        'unloading',
        'input',
    ];

    protected $casts = [
        'perencanaan' => 'float',
        'approval' => 'float',
        'supplier_confirm' => 'float',
        'payment' => 'float',
        'po' => 'float',
        'pengemasan' => 'float',
        'pengiriman' => 'float',
        'unloading' => 'float',
        'input' => 'float',
    ];

    public const STAGE_COLUMNS = [
        'perencanaan',
        'approval',
        'supplier_confirm',
        'payment',
        'po',
        'pengemasan',
        'pengiriman',
        'unloading',
        'input',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    /**
     * Hitung total hari dari 9 tahap (toleransi 1 angka desimal half-up)
     */
    public function totalHari(): float
    {
        $sum = (float) $this->perencanaan +
            (float) $this->approval +
            (float) $this->supplier_confirm +
            (float) $this->payment +
            (float) $this->po +
            (float) $this->pengemasan +
            (float) $this->pengiriman +
            (float) $this->unloading +
            (float) $this->input;

        return round($sum, 1, PHP_ROUND_HALF_UP);
    }
}
