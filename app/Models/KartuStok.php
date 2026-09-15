<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KartuStok extends Model
{
    use HasUuids;

    protected $table = 'kartu_stok';

    protected $fillable = [
        'tanggal',
        'produk_id',
        'gudang_id',
        'tipe',
        'qty',
        'saldo_setelah',
        'referensi_tipe',
        'referensi_id',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'qty' => 'decimal:2',
        'saldo_setelah' => 'decimal:2',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
