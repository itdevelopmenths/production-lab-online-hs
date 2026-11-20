<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadTimeStage extends Model
{
    protected $table = 'lead_time_stage';

    protected $fillable = [
        'produk_id',
        'skenario',
        'tahap',
        'jumlah_hari',
        'tambahan_buffer_hari',
    ];

    public const TAHAP = [
        // Urutan mengikuti alur PO: konfirmasi supplier sebelum approval. Pengemasan dihapus
        // (data lama pengemasan digabung ke pengiriman saat migrasi ke lead_time_lokal_stage).
        'perencanaan', 'supplier_confirm', 'approval', 'payment', 'po',
        'pengiriman', 'unloading', 'input',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
