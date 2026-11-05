<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarangDatang extends Model
{
    use HasUuids;

    protected $table = 'barang_datang';

    protected $fillable = [
        'po_id',
        'tanggal_terima',
        'waktu_tiba',
        'kondisi',
        'status_posting',
        'diposting_oleh',
        'diposting_pada',
        'created_by',
    ];

    protected $casts = [
        'tanggal_terima' => 'date',
        'waktu_tiba' => 'datetime',
        'diposting_pada' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BarangDatangItem::class, 'bardat_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diposting_oleh');
    }
}
