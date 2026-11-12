<?php

namespace App\Models;

use App\Traits\AuditableOperation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasUuids, AuditableOperation;

    protected $fillable = [
        'no_po',
        'no_invoice',
        'no_referensi_supplier',
        'supplier_id',
        'gudang_id',
        'tanggal',
        'eta',
        'eta_supplier',
        'sumber_dana',
        'status',
        'stage_aktif',
        'revisi_ke',
        'alasan_batal',
        'dibatalkan_oleh',
        'dari_analisa',
        'created_by',
        'skema_bayar',
        'tanggal_tempo',
        'subtotal_produk',
        'diskon_total',
        'ppn_nominal',
        'ongkos_kirim',
        'adjustment',
        'grand_total',
        'status_pembayaran',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'eta' => 'date',
        'eta_supplier' => 'date',
        'tanggal_tempo' => 'date',
        'dari_analisa' => 'boolean',
        'revisi_ke' => 'integer',
        'subtotal_produk' => 'decimal:2',
        'diskon_total' => 'decimal:2',
        'ppn_nominal' => 'decimal:2',
        'ongkos_kirim' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public const STATUS = ['draft', 'berjalan', 'diajukan', 'disetujui', 'dikirim_ke_gudang', 'selesai', 'dibatalkan'];
    public const SKEMA_BAYAR = ['tempo', 'termin', 'pelunasan', 'cash'];
    public const STATUS_PEMBAYARAN = ['belum_lunas', 'parsial', 'lunas', 'overdue', 'batal'];

    public function stages(): HasMany
    {
        return $this->hasMany(PoStage::class, 'po_id')->orderBy('urutan')->orderBy('putaran');
    }

    public function pengiriman(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PoPengiriman::class, 'po_id');
    }

    public function revisis(): HasMany
    {
        return $this->hasMany(PoRevisi::class, 'po_id')->orderBy('revisi_ke');
    }

    public function logPerubahan(): HasMany
    {
        return $this->hasMany(PoLogPerubahan::class, 'po_id')->orderByDesc('diubah_pada');
    }

    public function leadTime(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PoLeadTime::class, 'po_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function currentStageRow(): ?PoStage
    {
        return $this->stages()
            ->where('stage', $this->stage_aktif)
            ->where('status', 'berjalan')
            ->latest('putaran')
            ->first();
    }

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

        if (array_key_exists('total_nilai', $this->attributes)) {
            return (float) ($this->attributes['total_nilai'] ?? 0);
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
        if ($this->status === 'dibatalkan') {
            return 0.0;
        }

        return max(0, $this->totalNilai() - $this->totalDibayar());
    }

    public function isLunas(): bool
    {
        return $this->status !== 'dibatalkan'
            && $this->status_pembayaran !== 'batal'
            && $this->sisaTagihan() <= 0
            && $this->totalNilai() > 0;
    }

    /**
     * Cek apakah PO memiliki usulan revisi komitmen yang masih menunggu keputusan.
     */
    public function hasPendingRevision(): bool
    {
        if (array_key_exists('has_pending_revisi', $this->attributes)) {
            return (bool) $this->attributes['has_pending_revisi'];
        }

        if ($this->relationLoaded('revisis')) {
            return $this->revisis->where('status', PoRevisi::STATUS_MENUNGGU)->isNotEmpty();
        }

        return $this->revisis()->where('status', PoRevisi::STATUS_MENUNGGU)->exists();
    }

    /**
     * Cek apakah PO berstatus dibatalkan.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'dibatalkan';
    }

    /**
     * Cek apakah PO sudah memasuki status final (selesai atau dibatalkan).
     */
    public function isFinal(): bool
    {
        return in_array($this->status, ['selesai', 'dibatalkan'], true);
    }

    public function refreshStatusPembayaran(): void
    {
        if ($this->isCancelled()) {
            $this->status_pembayaran = 'batal';

            return;
        }

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

            // 2. Cek apakah PO tempo memiliki tanggal_tempo atau ETA yang sudah lewat hari ini
            if (! $hasOverdue && $this->skema_bayar === 'tempo') {
                if ($this->tanggal_tempo && $this->tanggal_tempo->isPast() && ! $this->tanggal_tempo->isToday()) {
                    $hasOverdue = true;
                } elseif (! $this->tanggal_tempo && $this->eta && $this->eta->isPast() && ! $this->eta->isToday()) {
                    $hasOverdue = true;
                }
            } elseif (! $hasOverdue && $this->eta && $this->eta->isPast() && ! $this->eta->isToday()) {
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
