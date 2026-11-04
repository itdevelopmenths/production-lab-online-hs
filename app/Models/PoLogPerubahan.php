<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoLogPerubahan extends Model
{
    protected $table = 'po_log_perubahan';

    protected $fillable = [
        'po_id',
        'nama_field',
        'nilai_lama',
        'nilai_baru',
        'diubah_oleh',
        'diubah_pada',
    ];

    protected $casts = [
        'diubah_pada' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
