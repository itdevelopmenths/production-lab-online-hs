<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatAnalisa extends Model
{
    use HasUuids;

    protected $table = 'riwayat_analisa';

    protected $fillable = [
        'session_id',
        'supplier_id',
        'average_lt_dipakai',
        'max_lt_dipakai',
        'sumber_lt',
        'tanggal',
        'tipe',
        'item_label',
        'batas_minimum',
        'target_stock',
        'status',
        'qty_order',
        'dicatat_oleh',
        'is_locked',
        'detail_payload',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
        'average_lt_dipakai' => 'decimal:1',
        'max_lt_dipakai' => 'decimal:1',
        'tanggal' => 'date',
        'batas_minimum' => 'decimal:2',
        'target_stock' => 'decimal:2',
        'qty_order' => 'decimal:2',
        'is_locked' => 'boolean',
        'detail_payload' => 'array',
    ];

    public const TIPE_LABELS = [
        'bahan_lokal' => 'Bahan Baku Lokal',
        'bahan_impor' => 'Bahan Baku Impor',
        'produk_jadi_produksi' => 'Produk Jadi (Produksi)',
        'produk_jadi_fulfillment' => 'Produk Jadi (Fulfillment)',
    ];

    public static function labelTipe(?string $tipe): string
    {
        return self::TIPE_LABELS[$tipe] ?? ucwords(str_replace('_', ' ', (string) $tipe));
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
