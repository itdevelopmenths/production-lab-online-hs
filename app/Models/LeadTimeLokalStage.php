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
        'perencanaan' => 'integer',
        'approval' => 'integer',
        'supplier_confirm' => 'integer',
        'payment' => 'integer',
        'po' => 'integer',
        'pengemasan' => 'integer',
        'pengiriman' => 'integer',
        'unloading' => 'integer',
        'input' => 'integer',
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
     * Hitung total hari dari 9 tahap
     */
    public function totalHari(): int
    {
        return (int) (
            $this->perencanaan +
            $this->approval +
            $this->supplier_confirm +
            $this->payment +
            $this->po +
            $this->pengemasan +
            $this->pengiriman +
            $this->unloading +
            $this->input
        );
    }
}
