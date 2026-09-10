<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestTransfer extends Model
{
    protected $fillable = [
        'no_transaksi',
        'jenis',
        'gudang_asal_id',
        'gudang_tujuan_id',
        'referensi_batch_id',
        'status',
        'catatan',
        'created_by',
    ];

    public const JENIS = [
        'req_bahan', 'retur_bahan', 'kirim_produk_jadi', 'antar_fulfillment', 'retur_produk_jadi',
    ];

    /** Jenis yang memakai pipeline 5-tahap dengan approval. */
    public const JENIS_APPROVAL = ['req_bahan'];

    public function items(): HasMany
    {
        return $this->hasMany(RequestTransferItem::class, 'request_transfer_id');
    }

    public function gudangAsal(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchProduksi::class, 'referensi_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Pipeline dengan approval (Request Bahan) atau transfer langsung. */
    public function pakaiApproval(): bool
    {
        return in_array($this->jenis, self::JENIS_APPROVAL, true);
    }
}
