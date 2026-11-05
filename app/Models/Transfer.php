<?php

namespace App\Models;

use App\Traits\AuditableOperation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    use HasUuids, AuditableOperation;

    protected $table = 'transfers';

    protected $fillable = [
        'no_transaksi',
        'jenis',
        'gudang_asal_id',
        'gudang_tujuan_id',
        'request_id',
        'referensi_batch_id',
        'waktu_dikirim',
        'waktu_diterima',
        'diterima_oleh',
        'status',
        'catatan',
        'alasan_batal',
        'created_by',
    ];

    protected $casts = [
        'waktu_dikirim' => 'datetime',
        'waktu_diterima' => 'datetime',
    ];

    public const JENIS = [
        'pemenuhan_request_bahan',
        'retur_bahan',
        'kirim_produk_jadi',
        'antar_fulfillment',
        'retur_produk_jadi',
    ];

    public const STATUS = ['draft', 'dikirim', 'selesai', 'dibatalkan'];

    public const JENIS_LABELS = [
        'pemenuhan_request_bahan' => 'Pemenuhan Request Bahan Baku',
        'retur_bahan' => 'Retur Bahan Baku',
        'kirim_produk_jadi' => 'Kirim Produk Jadi',
        'antar_fulfillment' => 'Transfer Antar Fulfillment',
        'retur_produk_jadi' => 'Retur Produk Jadi',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class, 'transfer_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'request_id');
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'request_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchProduksi::class, 'referensi_batch_id');
    }

    public function gudangAsal(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    public function totalQtyDikirim(): float
    {
        return (float) $this->items->sum('qty_dikirim');
    }

    public function totalQtyBaik(): float
    {
        return (float) $this->items->sum('qty_baik');
    }

    public function totalQtyRusak(): float
    {
        return (float) $this->items->sum('qty_rusak');
    }

    public function getNoTransferAttribute(): ?string
    {
        return $this->no_transaksi;
    }

    public function setNoTransferAttribute($value): void
    {
        $this->attributes['no_transaksi'] = $value;
    }

    public function getTanggalTransferAttribute()
    {
        return $this->waktu_dikirim ?? $this->created_at;
    }
}

