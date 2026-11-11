<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use \App\Traits\AuditableMasterData;

    protected $table = 'supplier';

    protected $fillable = [
        'nama',
        'kategori',
        'kontak',
        'alamat',
        'termin_default',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Supplier $supplier) {
            $hasActivePo = $supplier->purchaseOrders()
                ->whereIn('status', ['draft', 'diajukan', 'disetujui', 'diproses_supplier', 'dikirim_ke_gudang', 'parsial', 'berjalan'])
                ->exists();

            if ($hasActivePo) {
                throw new \DomainException("Supplier '{$supplier->nama}' tidak dapat dihapus karena masih terikat pada Purchase Order aktif.");
            }
        });
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'supplier_id');
    }

    public function produks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Produk::class, 'produk_supplier', 'supplier_id', 'produk_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    /** Produk bahan/kemas aktif yang memiliki relasi pemasokan berstatus aktif. */
    public function activeProduks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->produks()
            ->wherePivot('status', 'aktif')
            ->suppliable();
    }

    public function produkSuppliers(): HasMany
    {
        return $this->hasMany(ProdukSupplier::class, 'supplier_id');
    }

    public function stageSummaries(): HasMany
    {
        return $this->hasMany(SupplierLtStageSummary::class, 'supplier_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
