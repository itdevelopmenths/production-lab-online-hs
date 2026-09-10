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
        'supplier_id',
        'tanggal',
        'eta',
        'sumber_dana',
        'status',
        'dari_analisa',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'eta' => 'date',
        'dari_analisa' => 'boolean',
    ];

    public const STATUS = ['draft', 'diajukan', 'disetujui', 'dikirim_ke_gudang', 'selesai', 'dibatalkan'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'po_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'po_id');
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
        if ($this->relationLoaded('items')) {
            return (float) $this->items->sum('harga_total');
        }

        return (float) $this->items()->sum('harga_total');
    }

    public function totalDibayar(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('nominal');
        }

        return (float) $this->payments()->sum('nominal');
    }

    public function sisaTagihan(): float
    {
        return $this->totalNilai() - $this->totalDibayar();
    }

    public function isLunas(): bool
    {
        return $this->sisaTagihan() <= 0 && $this->totalNilai() > 0;
    }
}
