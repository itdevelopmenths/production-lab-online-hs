<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisaImpor extends Model
{
    protected $table = 'analisa_impor';

    protected $fillable = [
        'produk_id',
        'supplier_terpilih_id',
        'sumber_lt',
        'out',
        'periode_mulai',
        'periode_akhir',
        'jumlah_hari_periode',
        'adu_base',
        'adu_eta',
        'lead_time',
        'review_period',
        'klasifikasi_abc',
        'klasifikasi_abc_id',
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
        'periode_mulai' => 'date',
        'periode_akhir' => 'date',
        'jumlah_hari_periode' => 'integer',
        'klasifikasi_abc_id' => 'integer',
        'adu_base' => 'decimal:4',
        'adu_eta' => 'decimal:4',
        'lead_time' => 'float',
        'review_period' => 'integer',
        'tambahan_buffer_hari' => 'float',
        'buffer_days' => 'float',
        'safety_stock' => 'float',
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

    public function supplierTerpilih(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_terpilih_id');
    }

    public function klasifikasiAbc(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiAbc::class, 'klasifikasi_abc_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function tambahanHariAbc(): int
    {
        if ($this->klasifikasiAbc) {
            return (int) $this->klasifikasiAbc->tambahan_buffer_hari;
        }

        return self::ABC_BUFFER[strtolower((string) $this->klasifikasi_abc)] ?? 0;
    }
}
