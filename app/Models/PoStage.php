<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoStage extends Model
{
    protected $table = 'po_stage';

    protected $fillable = [
        'po_id',
        'stage',
        'urutan',
        'putaran',
        'status',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_menit',
        'pic_user_id',
        'dicatat_oleh',
        'dicatat_pada',
        'catatan',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'putaran' => 'integer',
        'durasi_menit' => 'integer',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'dicatat_pada' => 'datetime',
    ];

    public const STAGE_1 = 'perencanaan';
    public const STAGE_2 = 'approval';
    public const STAGE_3 = 'konfirmasi_supplier';
    public const STAGE_4 = 'pembayaran';
    public const STAGE_5 = 'po_terbit';
    public const STAGE_6 = 'pengiriman';
    public const STAGE_7 = 'unloading';
    public const STAGE_8 = 'input_sistem';

    // Legacy stage key for historic PO compatibility
    public const LEGACY_STAGE_PENGEMASAN = 'pengemasan';

    public const STAGES = [
        1 => self::STAGE_1,
        2 => self::STAGE_2,
        3 => self::STAGE_3,
        4 => self::STAGE_4,
        5 => self::STAGE_5,
        6 => self::STAGE_6,
        7 => self::STAGE_7,
        8 => self::STAGE_8,
    ];

    public const STAGE_LABELS = [
        self::STAGE_1 => 'Perencanaan & Pengajuan PO',
        self::STAGE_2 => 'Persetujuan Manager',
        self::STAGE_3 => 'Konfirmasi Supplier',
        self::STAGE_4 => 'Pembayaran / DP',
        self::STAGE_5 => 'Penerbitan PO Resmi',
        self::STAGE_6 => 'Pengiriman & Logistik',
        self::STAGE_7 => 'Unloading di Gudang',
        self::STAGE_8 => 'Input Sistem & Posting Stok',
        self::LEGACY_STAGE_PENGEMASAN => 'Pengemasan & Kesiapan Kirim (Arsip)',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}

