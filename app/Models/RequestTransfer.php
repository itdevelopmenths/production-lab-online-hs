<?php

namespace App\Models;

use App\Traits\AuditableOperation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestTransfer extends Model
{
    use HasUuids, AuditableOperation;

    protected $fillable = [
        'no_transaksi',
        'jenis',
        'gudang_asal_id',
        'gudang_tujuan_id',
        'referensi_batch_id',
        'status',
        'catatan',
        'alasan_batal',
        'created_by',
    ];

    public const JENIS = [
        'req_bahan', 'retur_bahan', 'kirim_produk_jadi', 'antar_fulfillment', 'retur_produk_jadi',
    ];

    public const JENIS_MATRIX = [
        'req_bahan' => [
            'label' => 'Request Bahan Baku',
            'asal_tipe' => ['bahan_baku'],
            'tujuan_tipe' => ['operasional'],
            'allowed_initiator' => 'either', // Lab pemohon atau Gudang Bahan Baku
        ],
        'retur_bahan' => [
            'label' => 'Retur Bahan Baku',
            'asal_tipe' => ['operasional'],
            'tujuan_tipe' => ['bahan_baku'],
            'allowed_initiator' => 'asal', // Lab pemegang sisa bahan
        ],
        'kirim_produk_jadi' => [
            'label' => 'Kirim Produk Jadi',
            'asal_tipe' => ['operasional'],
            'tujuan_tipe' => ['fulfillment'],
            'allowed_initiator' => 'asal', // Lab yang menyelesaikan produksi
        ],
        'antar_fulfillment' => [
            'label' => 'Transfer Antar Fulfillment',
            'asal_tipe' => ['fulfillment'],
            'tujuan_tipe' => ['fulfillment'],
            'allowed_initiator' => 'asal', // Petugas fulfillment asal
        ],
        'retur_produk_jadi' => [
            'label' => 'Retur Produk Jadi',
            'asal_tipe' => ['fulfillment'],
            'tujuan_tipe' => ['operasional'],
            'allowed_initiator' => 'asal', // Petugas fulfillment pengirim retur
        ],
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
