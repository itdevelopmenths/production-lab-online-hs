<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoRevisi extends Model
{
    protected $table = 'po_revisi';

    protected $fillable = [
        'po_id',
        'revisi_ke',
        'stage_saat_ajukan',
        'status',
        'alasan',
        'data_lama',
        'data_usulan',
        'diajukan_oleh',
        'diajukan_pada',
        'diputuskan_oleh',
        'diputuskan_pada',
        'catatan_keputusan',
    ];

    protected $casts = [
        'revisi_ke' => 'integer',
        'data_lama' => 'array',
        'data_usulan' => 'array',
        'diajukan_pada' => 'datetime',
        'diputuskan_pada' => 'datetime',
    ];

    public const STATUS_MENUNGGU = 'menunggu';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }
}

