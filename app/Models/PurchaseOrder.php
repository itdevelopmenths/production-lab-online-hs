<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'no_po',
        'no_invoice',
        'supplier_id',
        'gudang_id',
        'tanggal',
        'eta',
        'sumber_dana',
        'status',
        'dari_analisa',
        'created_by',
        'skema_bayar',
        'subtotal_produk',
        'diskon_total',
        'ppn_nominal',
        'ongkos_kirim',
        'adjustment',
        'grand_total',
        'status_pembayaran',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'eta' => 'date',
        'dari_analisa' => 'boolean',
        'subtotal_produk' => 'decimal:2',
        'diskon_total' => 'decimal:2',
        'ppn_nominal' => 'decimal:2',
        'ongkos_kirim' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public const STATUS = ['draft', 'diajukan', 'disetujui', 'dikirim_ke_gudang', 'selesai', 'dibatalkan'];
    public const SKEMA_BAYAR = ['cash', 'tempo', 'termin'];
    public const STATUS_PEMBAYARAN = ['belum_lunas', 'parsial', 'lunas', 'overdue'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'po_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'po_id');
    }

    public function termins(): HasMany
    {
        return $this->hasMany(PurchaseOrderTermin::class, 'po_id')->orderBy('termin_ke');
    }

    public function barangDatang(): HasMany
    {
        return $this->hasMany(BarangDatang::class, 'po_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalNilai(): float
    {
        if ((float) $this->grand_total > 0) {
            return (float) $this->grand_total;
        }

        if (array_key_exists('total_nilai', $this->attributes) && $this->attributes['total_nilai'] !== null) {
            return (float) $this->attributes['total_nilai'];
        }

        if ($this->relationLoaded('items')) {
            return (float) $this->items->sum('harga_total');
        }

        return (float) $this->items()->sum('harga_total');
    }

    public function totalDibayar(): float
    {
        if (array_key_exists('total_dibayar', $this->attributes)) {
            return (float) ($this->attributes['total_dibayar'] ?? 0);
        }

        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('nominal');
        }

        return (float) $this->payments()->sum('nominal');
    }

    public function sisaTagihan(): float
    {
        return max(0, $this->totalNilai() - $this->totalDibayar());
    }

    public function isLunas(): bool
    {
        return $this->sisaTagihan() <= 0 && $this->totalNilai() > 0;
    }

    public function refreshStatusPembayaran(): void
    {
        $total = $this->totalNilai();
        $dibayar = $this->totalDibayar();

        if ($dibayar >= $total && $total > 0) {
            $this->status_pembayaran = 'lunas';
        } else {
            // 1. Cek apakah ada termin yang overdue (baik dari status kolom atau tanggal_tempo lewat)
            $hasOverdue = false;
            if ($this->relationLoaded('termins') && $this->termins->count() > 0) {
                $hasOverdue = $this->termins->where('status', '!=', 'lunas')
                    ->contains(fn ($tm) => $tm->status === 'overdue' || ($tm->tanggal_tempo && $tm->tanggal_tempo->isPast() && ! $tm->tanggal_tempo->isToday()));
            } else {
                $hasOverdue = $this->termins()
                    ->where('status', '!=', 'lunas')
                    ->where(function ($q) {
                        $q->where('status', 'overdue')
                          ->orWhereDate('tanggal_tempo', '<', now()->toDateString());
                    })
                    ->exists();
            }

            // 2. Cek apakah PO tempo memiliki ETA yang sudah lewat hari ini
            if (! $hasOverdue && $this->eta && $this->eta->isPast() && ! $this->eta->isToday()) {
                $hasOverdue = true;
            }

            if ($hasOverdue) {
                $this->status_pembayaran = 'overdue';
            } elseif ($dibayar > 0) {
                $this->status_pembayaran = 'parsial';
            } else {
                $this->status_pembayaran = 'belum_lunas';
            }
        }
    }
}
