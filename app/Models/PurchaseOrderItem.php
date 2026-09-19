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
        'qty_satuan_beli',
        'satuan_beli',
        'faktor_konversi',
        'harga_total',
        'diskon',
        'ppn',
        'ongkir',
        'adjustment',
        'hpp_per_satuan',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'qty_satuan_beli' => 'decimal:2',
        'faktor_konversi' => 'decimal:4',
        'harga_total' => 'decimal:2',
        'diskon' => 'decimal:2',
        'ppn' => 'decimal:2',
        'ongkir' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'hpp_per_satuan' => 'decimal:4',
    ];

    public function satuanBeliLabel(): string
    {
        return $this->satuan_beli ?: ($this->produk?->satuan ?? 'pcs');
    }

    public function faktorKonversi(): float
    {
        return (float) ($this->faktor_konversi ?: 1.0000);
    }

    public function displayQtyPurchased(): string
    {
        $baseQtyFormatted = rtrim(rtrim(number_format((float) $this->qty, 2, ',', '.'), '0'), ',');
        $baseSatuan = $this->produk?->satuan ?? '';

        if ($this->qty_satuan_beli && $this->satuan_beli && strtolower($this->satuan_beli) !== strtolower($baseSatuan)) {
            $beliQtyFormatted = rtrim(rtrim(number_format((float) $this->qty_satuan_beli, 2, ',', '.'), '0'), ',');
            return "{$beliQtyFormatted} {$this->satuan_beli} ({$baseQtyFormatted} {$baseSatuan})";
        }

        return "{$baseQtyFormatted} {$baseSatuan}";
    }

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
     * Format nilai HPP per satuan dengan presisi desimal maksimal 2 digit:
     * - Bulat murni: Rp 15.000
     * - Pecahan (maksimal 2 desimal): Rp 526,35 atau Rp 1.000,5
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

        // Maksimal 2 digit desimal, hilangkan trailing zero
        $valRounded = round($val, 2);
        $formatted = number_format($valRounded, 2, ',', '.');

        return 'Rp ' . rtrim(rtrim($formatted, '0'), ',');
    }

    public function qtyDiterima(): float
    {
        if ($this->relationLoaded('bardatItems')) {
            return (float) $this->bardatItems->sum('qty_diterima');
        }

        return (float) $this->bardatItems()->sum('qty_diterima');
    }
}
