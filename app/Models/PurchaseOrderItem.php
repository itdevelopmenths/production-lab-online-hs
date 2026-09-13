<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'po_id',
        'produk_id',
        'qty',
        'harga_total',
        'diskon',
        'ppn',
        'ongkir',
        'adjustment',
        'hpp_per_satuan',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'harga_total' => 'decimal:2',
        'diskon' => 'decimal:2',
        'ppn' => 'decimal:2',
        'ongkir' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'hpp_per_satuan' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function bardatItems(): HasMany
    {
        return $this->hasMany(BarangDatangItem::class, 'po_item_id');
    }

    /** Total bersih setelah diskon, ppn, ongkir, dan adjustment */
    public function netTotal(): float
    {
        return (float) $this->harga_total - (float) $this->diskon + (float) $this->ppn + (float) $this->ongkir + (float) $this->adjustment;
    }

    /** Harga per satuan (HPP) = netTotal / qty, dengan fallback ke produk harga_hpp jika ada. */
    public function hargaPerSatuan(): float
    {
        if ((float) $this->hpp_per_satuan > 0) {
            return (float) $this->hpp_per_satuan;
        }

        $qty = (float) $this->qty;
        if ($qty > 0) {
            $net = $this->netTotal();
            if ($net > 0) {
                return round($net / $qty, 4);
            }
            if ((float) $this->harga_total > 0) {
                return round((float) $this->harga_total / $qty, 4);
            }
        }

        return (float) ($this->produk?->harga_hpp ?? 0);
    }

    /**
     * Format nilai HPP per satuan dengan presisi desimal cerdas:
     * - Bulat murni: Rp 15.000
     * - Pecahan standar (<= 2 desimal): Rp 526,35
     * - Pecahan mikro (> 2 desimal, hingga 4 desimal): Rp 106.666,6667
     */
    public function formattedHpp(): string
    {
        $val = $this->hargaPerSatuan();
        if ($val <= 0) {
            return '—';
        }

        // Jika bilangan bulat murni tanpa pecahan
        if (abs($val - round($val)) < 0.00001) {
            return 'Rp ' . number_format(round($val), 0, ',', '.');
        }

        // Cek apakah ada digit pecahan signifikan setelah 2 desimal (hingga 4 desimal)
        $rounded2 = round($val, 2);
        if (abs($val - $rounded2) > 0.00001) {
            $formatted = number_format($val, 4, ',', '.');

            return 'Rp ' . rtrim(rtrim($formatted, '0'), ',');
        }

        // 1 atau 2 digit desimal standar (misal Rp 526,35 atau Rp 1.000,50)
        return 'Rp ' . number_format($val, 2, ',', '.');
    }

    public function qtyDiterima(): float
    {
        if ($this->relationLoaded('bardatItems')) {
            return (float) $this->bardatItems->sum('qty_diterima');
        }

        return (float) $this->bardatItems()->sum('qty_diterima');
    }
}
