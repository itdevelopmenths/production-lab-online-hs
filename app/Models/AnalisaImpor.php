<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisaImpor extends Model
{
    protected $table = 'analisa_impor';

    protected $fillable = [
        'produk_id',
        'out',
        'adu_base',
        'adu_eta',
        'lead_time',
        'review_period',
        'klasifikasi_abc',
        'tambahan_buffer_hari',
        'buffer_days',
        'safety_stock',
        'minimum_stock',
        'target_stock',
        'stok_saat_ini',
        'inbound_before_eta',
        'proyeksi',
        'qty_order',
        'po',
        'status',
        'harga_per_satuan',
        'total_nominal_order',
        'punya_varian',
        'varian_detail',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'out' => 'decimal:2',
        'adu_base' => 'decimal:4',
        'adu_eta' => 'decimal:4',
        'lead_time' => 'decimal:2',
        'review_period' => 'integer',
        'tambahan_buffer_hari' => 'integer',
        'buffer_days' => 'decimal:2',
        'safety_stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'target_stock' => 'decimal:2',
        'stok_saat_ini' => 'decimal:2',
        'inbound_before_eta' => 'decimal:2',
        'proyeksi' => 'decimal:2',
        'qty_order' => 'decimal:2',
        'po' => 'decimal:2',
        'harga_per_satuan' => 'decimal:2',
        'total_nominal_order' => 'decimal:2',
        'punya_varian' => 'boolean',
        'varian_detail' => 'array',
        'generated_at' => 'datetime',
    ];

    public const ABC_BUFFER = [
        'wajib_a' => 4,
        'a' => 4,
        'b' => 2,
        'c' => 0,
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function tambahanHariAbc(): int
    {
        return self::ABC_BUFFER[strtolower((string) $this->klasifikasi_abc)] ?? 0;
    }
}
