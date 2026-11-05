<?php

namespace App\Models;

use App\Traits\AuditableOperation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockRequest extends Model
{
    use HasUuids, AuditableOperation;

    protected $table = 'requests';

    protected $fillable = [
        'no_request',
        'jenis',
        'referensi_batch_id',
        'gudang_asal_id',
        'gudang_tujuan_id',
        'status',
        'catatan',
        'alasan_tolak',
        'alasan_batal',
        'disetujui_oleh',
        'disetujui_pada',
        'created_by',
    ];

    protected $casts = [
        'disetujui_pada' => 'datetime',
    ];

    public const JENIS = ['request_bahan', 'request_produk_jadi'];
    public const STATUS = ['draft', 'diajukan', 'disetujui', 'diproses', 'selesai', 'ditolak', 'dibatalkan'];

    public const JENIS_LABELS = [
        'request_bahan' => 'Request Bahan Baku',
        'request_produk_jadi' => 'Request Produk Jadi',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class, 'request_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchProduksi::class, 'referensi_batch_id');
    }

    public function gudangAsal(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function gudangPemohon(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function gudangPenyedia(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(Transfer::class, 'request_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'request_id');
    }

    public function activeTransfer(): HasOne
    {
        return $this->hasOne(Transfer::class, 'request_id')
            ->where('status', '!=', 'dibatalkan');
    }
}
