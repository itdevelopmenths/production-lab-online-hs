<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderTermin extends Model
{
    use HasUuids;

    protected $fillable = [
        'po_id',
        'termin_ke',
        'tanggal_tempo',
        'nominal_tagihan',
        'nominal_dibayar',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'termin_ke' => 'integer',
        'tanggal_tempo' => 'date',
        'nominal_tagihan' => 'decimal:2',
        'nominal_dibayar' => 'decimal:2',
    ];

    public const STATUS = ['belum_lunas', 'parsial', 'lunas', 'overdue'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function sisaNominal(): float
    {
        return max(0, (float) $this->nominal_tagihan - (float) $this->nominal_dibayar);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'lunas' && $this->tanggal_tempo->isPast() && ! $this->tanggal_tempo->isToday();
    }

    public function updateStatusAutomatically(): void
    {
        $tagihan = (float) $this->nominal_tagihan;
        $dibayar = (float) $this->nominal_dibayar;

        if ($dibayar >= $tagihan && $tagihan > 0) {
            $this->status = 'lunas';
        } elseif ($this->isOverdue()) {
            $this->status = 'overdue';
        } elseif ($dibayar > 0) {
            $this->status = 'parsial';
        } else {
            $this->status = 'belum_lunas';
        }
    }
}
